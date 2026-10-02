"""
pipeline/imputation.py — Imputasi demand_clean untuk hari-hari censored.

Algoritma:
  1. Hari NON-censored → demand_clean = issued_qty (langsung, imputation_method = NONE)
  2. Hari censored → demand_clean = rata-rata issued_qty hari NON-censored
     dalam window imputation_window_days hari sebelumnya.
     - Hari non-censored dengan issued = 0 TETAP ikut dihitung (nol yang sah).
     - Jika hari non-censored dalam window < min_uncensored_days:
         Perluas window ke seluruh histori yang ada.
         Jika masih kurang: demand_clean = None, imputation_method = INSUFFICIENT_DATA.
         Hari tersebut dikeluarkan dari training.
"""
from __future__ import annotations

from typing import Optional
from dataclasses import replace

from app.pipeline.demand_pipeline import DailyRow


def impute_demand(
    rows: list[DailyRow],
    window_days: int,
    min_uncensored: int,
) -> list[DailyRow]:
    """
    Isi demand_clean untuk setiap row. Modifies list in-place-style (returns new list).

    Args:
        rows          : List[DailyRow] berurutan dari tanggal terkecil ke terbesar
        window_days   : Ukuran window imputasi (default: 28 hari)
        min_uncensored: Minimum hari non-censored dalam window (default: 14)

    Returns:
        List[DailyRow] dengan demand_clean dan imputation_method terisi.
    """
    result: list[DailyRow] = []

    for i, row in enumerate(rows):
        if not row.is_censored:
            # Hari valid: demand_clean = issued_qty asli
            result.append(replace(
                row,
                demand_clean=row.issued_qty,
                imputation_method="NONE",
            ))
            continue

        # ── Hari Censored: cari nilai imputasi ───────────────────────────────
        # Kumpulkan hari non-censored sebelum hari ini
        past_rows = rows[:i]  # semua hari sebelum hari ini

        # Window pertama: imputation_window_days hari terakhir
        windowed = [r for r in past_rows[-window_days:] if not r.is_censored]
        method = "MEAN_UNCENSORED_28D"

        if len(windowed) < min_uncensored:
            # Window kurang: perluas ke seluruh histori
            windowed = [r for r in past_rows if not r.is_censored]
            method = "MEAN_UNCENSORED_FULL"

        if len(windowed) < min_uncensored:
            # Masih kurang: tidak cukup data, jangan imputasi
            result.append(replace(
                row,
                demand_clean=None,
                imputation_method="INSUFFICIENT_DATA",
            ))
            continue

        # Rata-rata issued_qty hari non-censored dalam window
        # Hari non-censored dengan issued_qty = 0 tetap dimasukkan (zero yang sah)
        mean_demand = sum(r.issued_qty for r in windowed) / len(windowed)

        result.append(replace(
            row,
            demand_clean=round(mean_demand, 3),
            imputation_method=method,
        ))

    return result


def get_training_series(rows: list[DailyRow]) -> list[float]:
    """
    Ekstrak demand_clean untuk training model.
    Hanya rows dengan demand_clean IS NOT NULL yang dimasukkan.
    Digunakan oleh modul forecasting.
    """
    return [r.demand_clean for r in rows if r.demand_clean is not None]
