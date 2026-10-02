"""
api/classification.py — POST /api/v1/classification/batch

Menjalankan klasifikasi ABC-XYZ + demand pattern untuk batch SKU.
UPSERT hasil ke tabel item_classifications.
Dijalankan oleh Laravel Scheduler setiap minggu.
"""
from __future__ import annotations

import logging
from datetime import date, timedelta

from fastapi import APIRouter, Depends
from sqlalchemy import text

from app.api.deps import verify_api_key
from app.config import get_settings
from app.database import get_db
from app.models.item import ItemORM
from app.models.item_classification import ItemClassificationORM
from app.models.daily_demand import DailyDemandORM
from app.classification.abc_xyz import classify_abc, classify_xyz
from app.classification.demand_pattern import classify_demand_pattern
from app.pipeline.imputation import get_training_series
from app.schemas.classification import (
    ClassificationBatchRequest,
    ClassificationBatchResponse,
    ClassificationItemResult,
)

router = APIRouter(prefix="/api/v1/classification", tags=["Classification"])
logger = logging.getLogger(__name__)
cfg = get_settings()


def _load_demand_series(session, item_id: int) -> list[float]:
    """Load demand_clean dari daily_demand, filter NULL."""
    sql = text("""
        SELECT demand_clean
        FROM daily_demand
        WHERE item_id = :item_id
          AND demand_clean IS NOT NULL
        ORDER BY date ASC
    """)
    rows = session.execute(sql, {"item_id": item_id}).fetchall()
    return [float(r[0]) for r in rows if r[0] is not None]


def _load_demand_365d(session, item_id: int) -> float:
    """Total demand_clean 365 hari terakhir (untuk ABC annual_value)."""
    cutoff = date.today() - timedelta(days=365)
    sql = text("""
        SELECT COALESCE(SUM(demand_clean), 0)
        FROM daily_demand
        WHERE item_id = :item_id
          AND demand_clean IS NOT NULL
          AND date >= :cutoff
    """)
    result = session.execute(sql, {"item_id": item_id, "cutoff": cutoff}).scalar()
    return float(result) if result else 0.0


@router.post("/batch", response_model=ClassificationBatchResponse)
async def classification_batch(
    payload: ClassificationBatchRequest,
    _: str = Depends(verify_api_key),
):
    """
    Klasifikasi ABC-XYZ + demand_pattern untuk batch SKU.
    UPSERT ke item_classifications (kunci: item_id).
    """
    results: list[ClassificationItemResult] = []

    with get_db() as session:
        # ── 1. Kumpulkan data untuk ABC (butuh semua SKU sekaligus) ───────────
        items_demand: dict[int, tuple[float, float]] = {}
        for item_id in payload.item_ids:
            item = session.get(ItemORM, item_id)
            if item is None:
                logger.warning(f"Item {item_id} tidak ditemukan, skip.")
                continue
            demand_365 = _load_demand_365d(session, item_id)
            unit_cost = float(item.unit_cost) if item.unit_cost else 0.0
            items_demand[item_id] = (demand_365, unit_cost)

        abc_results = classify_abc(items_demand)

        # ── 2. Klasifikasi per item ────────────────────────────────────────────
        for item_id in payload.item_ids:
            if item_id not in items_demand:
                continue

            demand_series = _load_demand_series(session, item_id)

            # XYZ
            xyz_result = classify_xyz(item_id, demand_series)

            # Demand pattern (ADI/CV²)
            pattern_result = classify_demand_pattern(item_id, demand_series)

            # ABC
            abc = abc_results.get(item_id)
            abc_class = abc.abc_class if abc else "C"
            annual_value = abc.annual_value if abc else 0.0

            # ── UPSERT ke item_classifications ─────────────────────────────────
            _upsert_classification(
                session,
                item_id=item_id,
                abc_class=abc_class,
                annual_value=annual_value,
                xyz_class=xyz_result.xyz_class,
                adi=pattern_result.adi,
                cv2=pattern_result.cv2,
                demand_pattern=pattern_result.demand_pattern,
                history_days=pattern_result.history_days,
                is_reliable=pattern_result.is_reliable,
            )

            results.append(ClassificationItemResult(
                item_id=item_id,
                abc_class=abc_class,
                annual_value=annual_value,
                xyz_class=xyz_result.xyz_class,
                demand_pattern=pattern_result.demand_pattern,
                adi=pattern_result.adi,
                cv2=pattern_result.cv2,
                history_days=pattern_result.history_days,
                is_reliable=pattern_result.is_reliable,
            ))

    return ClassificationBatchResponse(
        total_items=len(payload.item_ids),
        classified=len(results),
        results=results,
    )


def _upsert_classification(
    session,
    item_id: int,
    abc_class: str,
    annual_value: float,
    xyz_class: str,
    adi: float | None,
    cv2: float | None,
    demand_pattern: str,
    history_days: int,
    is_reliable: bool,
):
    """UPSERT ke item_classifications (kunci unik: item_id)."""
    sql = text("""
        INSERT INTO item_classifications
            (item_id, computed_at, abc_class, annual_value, xyz_class,
             adi, cv2, demand_pattern, history_days, is_reliable,
             created_at, updated_at)
        VALUES
            (:item_id, NOW(), :abc_class, :annual_value, :xyz_class,
             :adi, :cv2, :demand_pattern, :history_days, :is_reliable,
             NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            computed_at     = NOW(),
            abc_class       = VALUES(abc_class),
            annual_value    = VALUES(annual_value),
            xyz_class       = VALUES(xyz_class),
            adi             = VALUES(adi),
            cv2             = VALUES(cv2),
            demand_pattern  = VALUES(demand_pattern),
            history_days    = VALUES(history_days),
            is_reliable     = VALUES(is_reliable),
            updated_at      = NOW()
    """)
    session.execute(sql, {
        "item_id": item_id,
        "abc_class": abc_class,
        "annual_value": annual_value,
        "xyz_class": xyz_class,
        "adi": adi,
        "cv2": cv2,
        "demand_pattern": demand_pattern,
        "history_days": history_days,
        "is_reliable": is_reliable,
    })
