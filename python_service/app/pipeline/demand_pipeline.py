"""
pipeline/demand_pipeline.py — Tahap 1: Rekonstruksi daily_demand dari stock_movements.

Algoritma rekonstruksi opening_stock:
  opening_stock[t] = closing_stock[t-1] + receipt_qty[t]
  closing_stock[t] = opening_stock[t] - issued_qty[t]

Aturan censored:
  is_censored = issued_qty < TOLERANCE AND opening_stock < TOLERANCE
  (toleransi 0.0005 untuk menghindari galat floating point DECIMAL MySQL)

Saldo awal:
  Hari pertama menggunakan movement dengan reason=OPENING_BALANCE sebagai receipt.

Barang datang sore hari dianggap tersedia sepanjang hari (batasan yang diterima).
"""
from __future__ import annotations

import logging
from dataclasses import dataclass
from datetime import date
from typing import Optional

import pandas as pd
from sqlalchemy.orm import Session
from sqlalchemy import text

from app.config import get_settings
from app.models.daily_demand import DailyDemandORM
from app.pipeline.imputation import impute_demand

logger = logging.getLogger(__name__)
cfg = get_settings()


@dataclass
class DailyRow:
    item_id: int
    date: date
    opening_stock: float
    receipt_qty: float
    issued_qty: float
    is_censored: bool
    demand_clean: Optional[float]
    imputation_method: str


def _load_movements(session: Session, item_id: int) -> pd.DataFrame:
    """
    Baca semua gerakan stok untuk satu item dari stock_movements.
    Hanya kolom yang diperlukan untuk rekonstruksi.
    Filter hanya reason: ISSUE, RECEIPT, RETURN, ADJUSTMENT, OPENING_BALANCE.
    """
    sql = text("""
        SELECT
            movement_date,
            reason,
            qty
        FROM stock_movements
        WHERE item_id = :item_id
        ORDER BY movement_date ASC, id ASC
    """)
    rows = session.execute(sql, {"item_id": item_id}).fetchall()
    if not rows:
        return pd.DataFrame(columns=["movement_date", "reason", "qty"])

    df = pd.DataFrame(rows, columns=["movement_date", "reason", "qty"])
    df["movement_date"] = pd.to_datetime(df["movement_date"])
    df["qty"] = df["qty"].astype(float)
    return df


def _load_item_meta(session: Session, item_id: int) -> dict:
    """Ambil first_movement_date dari items."""
    sql = text("""
        SELECT first_movement_date
        FROM items
        WHERE id = :item_id
    """)
    row = session.execute(sql, {"item_id": item_id}).fetchone()
    return {
        "first_movement_date": row[0] if row and row[0] else None,
    }


def build_daily_demand(session: Session, item_id: int, as_of_date: date) -> list[DailyRow]:
    """
    Rekonstruksi daily_demand untuk satu item hingga as_of_date.

    Returns:
        List[DailyRow] — satu row per hari kalender sejak first_movement_date.
    """
    meta = _load_item_meta(session, item_id)
    movements_df = _load_movements(session, item_id)

    if movements_df.empty:
        logger.warning(f"Item {item_id}: tidak ada movement ditemukan.")
        return []

    # Tentukan batas awal histori
    first_date = (
        pd.Timestamp(meta["first_movement_date"])
        if meta["first_movement_date"]
        else movements_df["movement_date"].min()
    )

    # Buat rentang tanggal lengkap (setiap hari kalender)
    date_range = pd.date_range(start=first_date, end=pd.Timestamp(as_of_date), freq="D")
    if len(date_range) == 0:
        return []

    # Pivot gerakan per hari
    # RECEIPT dan OPENING_BALANCE = masuk (memperbesar stok, tidak dihitung demand)
    # RETURN = kembali ke gudang (memperbesar stok)
    # ISSUE = keluar karena pemakaian (demand pelanggan)
    # ADJUSTMENT = koreksi stok (mempengaruhi saldo, bukan demand)
    daily_receipts = (
        movements_df[movements_df["reason"].isin(["RECEIPT", "OPENING_BALANCE", "RETURN"])]
        .groupby("movement_date")["qty"]
        .sum()
        .reindex(date_range, fill_value=0.0)
    )
    daily_issues = (
        movements_df[movements_df["reason"] == "ISSUE"]
        .groupby("movement_date")["qty"]
        .sum()
        .reindex(date_range, fill_value=0.0)
    )
    daily_adjustments = (
        movements_df[movements_df["reason"] == "ADJUSTMENT"]
        .groupby("movement_date")["qty"]
        .sum()
        .reindex(date_range, fill_value=0.0)
    )

    tol = cfg.censored_tolerance
    rows: list[DailyRow] = []
    closing_stock = 0.0

    for day in date_range:
        receipt_qty = float(daily_receipts.loc[day])
        issued_qty = float(daily_issues.loc[day])
        adjustment = float(daily_adjustments.loc[day])

        # opening_stock = closing_stock kemarin + penerimaan hari ini
        opening_stock = closing_stock + receipt_qty

        # Saldo tidak boleh negatif (data quality guard)
        if opening_stock < 0:
            logger.warning(
                f"Item {item_id} date {day.date()}: opening_stock negatif ({opening_stock:.3f}), diset 0."
            )
            opening_stock = 0.0

        # closing_stock setelah pengeluaran dan adjustment
        closing_stock = opening_stock - issued_qty + adjustment
        if closing_stock < 0:
            logger.warning(
                f"Item {item_id} date {day.date()}: closing_stock negatif ({closing_stock:.3f}), diset 0."
            )
            closing_stock = 0.0

        # ── Deteksi Censored ──────────────────────────────────────────────────
        # issued_qty = 0 DAN opening_stock = 0: stockout, bukan true zero demand
        is_censored = issued_qty < tol and opening_stock < tol

        rows.append(DailyRow(
            item_id=item_id,
            date=day.date(),
            opening_stock=round(opening_stock, 3),
            receipt_qty=round(receipt_qty, 3),
            issued_qty=round(issued_qty, 3),
            is_censored=is_censored,
            demand_clean=None,  # diisi oleh imputation setelah loop
            imputation_method="NONE",
        ))

    # ── Imputasi demand_clean ─────────────────────────────────────────────────
    rows = impute_demand(rows, cfg.imputation_window_days, cfg.min_uncensored_days)

    return rows


def upsert_daily_demand(session: Session, rows: list[DailyRow]) -> int:
    """
    UPSERT rows ke tabel daily_demand.
    Kunci: (item_id, date). Idempotent — run ulang tidak menggandakan data.
    TIDAK menimpa issued_qty asli (hanya demand_clean dan is_censored yang di-update).
    """
    if not rows:
        return 0

    # MySQL INSERT ... ON DUPLICATE KEY UPDATE
    sql = text("""
        INSERT INTO daily_demand
            (item_id, date, opening_stock, receipt_qty, issued_qty,
             is_censored, demand_clean, imputation_method)
        VALUES
            (:item_id, :date, :opening_stock, :receipt_qty, :issued_qty,
             :is_censored, :demand_clean, :imputation_method)
        ON DUPLICATE KEY UPDATE
            opening_stock       = VALUES(opening_stock),
            receipt_qty         = VALUES(receipt_qty),
            is_censored         = VALUES(is_censored),
            demand_clean        = VALUES(demand_clean),
            imputation_method   = VALUES(imputation_method)
        -- issued_qty TIDAK di-update: nilai asli ledger harus preserved
    """)

    params = [
        {
            "item_id": r.item_id,
            "date": r.date,
            "opening_stock": r.opening_stock,
            "receipt_qty": r.receipt_qty,
            "issued_qty": r.issued_qty,
            "is_censored": r.is_censored,
            "demand_clean": r.demand_clean,
            "imputation_method": r.imputation_method,
        }
        for r in rows
    ]

    session.execute(sql, params)
    return len(rows)
