"""
api/forecast.py — POST /api/v1/forecast/batch

Alur per request:
  1. Validasi API Key
  2. Cek idempotency: jika run_id sudah ada di forecast_runs untuk semua item → return cached
  3. Per item:
     a. Jalankan build_daily_demand + upsert ke daily_demand
     b. Klasifikasi demand_pattern (dari item_classifications atau hitung baru)
     c. Route ke model forecast yang sesuai
     d. Validasi output (NaN, inf, negatif → FAILED)
     e. INSERT ke forecast_runs
  4. Return ForecastBatchResponse
"""
from __future__ import annotations

import logging
from datetime import date

from fastapi import APIRouter, Depends, HTTPException, status

from app.api.deps import verify_api_key
from app.config import get_settings
from app.database import get_db
from app.pipeline.demand_pipeline import build_daily_demand, upsert_daily_demand
from app.pipeline.imputation import get_training_series
from app.classification.demand_pattern import classify_demand_pattern
from app.forecasting.router import route_and_forecast
from app.models.forecast_run import ForecastRunORM
from app.models.item import ItemORM
from app.models.item_classification import ItemClassificationORM
from app.schemas.forecast import (
    ForecastBatchRequest,
    ForecastBatchResponse,
    ForecastItemResult,
)

router = APIRouter(prefix="/api/v1/forecast", tags=["Forecast"])
logger = logging.getLogger(__name__)
cfg = get_settings()


def _is_valid_output(mu: float | None, sigma: float | None) -> bool:
    """Validasi output model: tidak boleh None, NaN, inf, atau negatif."""
    import math
    if mu is None or sigma is None:
        return False
    if not math.isfinite(mu) or not math.isfinite(sigma):
        return False
    if mu < 0 or sigma < 0:
        return False
    return True


def _run_was_already_processed(session, run_id: str, item_ids: list[int]) -> bool:
    """Cek apakah run_id sudah diproses untuk semua item (idempotency)."""
    existing = (
        session.query(ForecastRunORM)
        .filter(
            ForecastRunORM.run_id == run_id,
            ForecastRunORM.item_id.in_(item_ids),
        )
        .count()
    )
    return existing == len(item_ids)


@router.post("/batch", response_model=ForecastBatchResponse)
async def forecast_batch(
    payload: ForecastBatchRequest,
    _: str = Depends(verify_api_key),
):
    """
    Jalankan forecast pipeline untuk batch SKU.

    Idempotent: request ulang dengan run_id yang sama diabaikan
    (baris di forecast_runs tidak digandakan).
    """
    if len(payload.item_ids) > cfg.chunk_size:
        raise HTTPException(
            status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
            detail=f"Maksimum {cfg.chunk_size} item per request. Diterima: {len(payload.item_ids)}.",
        )

    results: list[ForecastItemResult] = []
    succeeded = 0
    skipped = 0
    failed = 0

    with get_db() as session:
        # Idempotency check global
        if _run_was_already_processed(session, payload.run_id, payload.item_ids):
            logger.info(f"run_id={payload.run_id} sudah diproses sebelumnya. Return cached.")
            cached = (
                session.query(ForecastRunORM)
                .filter(
                    ForecastRunORM.run_id == payload.run_id,
                    ForecastRunORM.item_id.in_(payload.item_ids),
                )
                .all()
            )
            for row in cached:
                results.append(ForecastItemResult(
                    item_id=row.item_id,
                    status=row.status,
                    mu_daily=row.mu_daily,
                    sigma_daily=row.sigma_daily,
                    model_used=row.model_used,
                    demand_pattern_used=row.demand_pattern_used,
                    backtest_mae=row.backtest_mae,
                    backtest_rmse=row.backtest_rmse,
                    backtest_mase=row.backtest_mase,
                    baseline_mase=row.baseline_mase,
                    error_message=row.error_message,
                ))
                if row.status == "SUCCESS":
                    succeeded += 1
                elif row.status == "SKIPPED":
                    skipped += 1
                else:
                    failed += 1

            return ForecastBatchResponse(
                run_id=payload.run_id,
                as_of_date=payload.as_of_date,
                total_items=len(payload.item_ids),
                succeeded=succeeded,
                skipped=skipped,
                failed=failed,
                results=results,
            )

        # ── Proses per item ────────────────────────────────────────────────────
        for item_id in payload.item_ids:

            # Skip item yang sudah diproses dalam run_id ini (partial idempotency)
            already = (
                session.query(ForecastRunORM)
                .filter(
                    ForecastRunORM.run_id == payload.run_id,
                    ForecastRunORM.item_id == item_id,
                )
                .first()
            )
            if already:
                result = ForecastItemResult(
                    item_id=item_id,
                    status=already.status,
                    mu_daily=already.mu_daily,
                    sigma_daily=already.sigma_daily,
                    model_used=already.model_used,
                )
                results.append(result)
                if already.status == "SUCCESS":
                    succeeded += 1
                elif already.status == "SKIPPED":
                    skipped += 1
                else:
                    failed += 1
                continue

            try:
                result = _process_one_item(
                    session, item_id, payload.run_id, payload.as_of_date
                )
            except Exception as e:
                logger.exception(f"Item {item_id}: unhandled error — {e}")
                result = ForecastItemResult(
                    item_id=item_id,
                    status="FAILED",
                    error_message=str(e),
                )
                # Tetap simpan ke DB agar run tidak di-retry untuk item ini
                _save_forecast_run(session, item_id, payload.run_id, result)

            results.append(result)
            if result.status == "SUCCESS":
                succeeded += 1
            elif result.status == "SKIPPED":
                skipped += 1
            else:
                failed += 1

    return ForecastBatchResponse(
        run_id=payload.run_id,
        as_of_date=payload.as_of_date,
        total_items=len(payload.item_ids),
        succeeded=succeeded,
        skipped=skipped,
        failed=failed,
        results=results,
    )


def _process_one_item(
    session, item_id: int, run_id: str, as_of_date: date
) -> ForecastItemResult:
    """Pipeline penuh untuk satu item."""
    # ── 1. Load item metadata ──────────────────────────────────────────────
    item = session.get(ItemORM, item_id)
    if item is None:
        raise ValueError(f"Item {item_id} tidak ditemukan di DB.")

    lead_time = int(item.lead_time_days)

    # ── 2. Build daily_demand ──────────────────────────────────────────────
    daily_rows = build_daily_demand(session, item_id, as_of_date)
    if daily_rows:
        upsert_daily_demand(session, daily_rows)
        session.flush()  # pastikan upsert committed sebelum query lain

    training_series = get_training_series(daily_rows)

    # ── 3. Klasifikasi demand pattern ──────────────────────────────────────
    # Coba baca dari item_classifications jika sudah ada dan fresh
    clf = (
        session.query(ItemClassificationORM)
        .filter(ItemClassificationORM.item_id == item_id)
        .first()
    )

    if clf and clf.is_reliable:
        demand_pattern = clf.demand_pattern
        is_reliable = clf.is_reliable
        history_days = clf.history_days
    else:
        # Hitung fresh dari training series
        pattern_result = classify_demand_pattern(item_id, training_series)
        demand_pattern = pattern_result.demand_pattern
        is_reliable = pattern_result.is_reliable
        history_days = pattern_result.history_days

    # ── 4. Forecast ────────────────────────────────────────────────────────
    forecast_output = route_and_forecast(
        item_id=item_id,
        demand_series=training_series,
        demand_pattern=demand_pattern,
        lead_time_days=lead_time,
        is_reliable=is_reliable,
    )

    # ── 5. Validasi output ─────────────────────────────────────────────────
    if forecast_output.status == "SUCCESS":
        if not _is_valid_output(forecast_output.mu_daily, forecast_output.sigma_daily):
            forecast_output.status = "FAILED"
            forecast_output.error_message = (
                f"Output tidak valid: mu={forecast_output.mu_daily}, "
                f"sigma={forecast_output.sigma_daily}"
            )

    # ── 6. Simpan ke forecast_runs ─────────────────────────────────────────
    result = ForecastItemResult(
        item_id=item_id,
        status=forecast_output.status,
        mu_daily=forecast_output.mu_daily,
        sigma_daily=forecast_output.sigma_daily,
        model_used=forecast_output.model_used,
        demand_pattern_used=forecast_output.demand_pattern_used,
        backtest_mae=forecast_output.backtest_mae,
        backtest_rmse=forecast_output.backtest_rmse,
        backtest_mase=forecast_output.backtest_mase,
        baseline_mase=forecast_output.baseline_mase,
        error_message=forecast_output.error_message,
    )
    _save_forecast_run(session, item_id, run_id, result)

    return result


def _save_forecast_run(session, item_id: int, run_id: str, result: ForecastItemResult):
    """INSERT baris baru ke forecast_runs (append-only, tidak update baris lama)."""
    row = ForecastRunORM(
        run_id=run_id,
        item_id=item_id,
        mu_daily=result.mu_daily,
        sigma_daily=result.sigma_daily,
        model_used=result.model_used,
        demand_pattern_used=result.demand_pattern_used,
        backtest_mae=result.backtest_mae,
        backtest_rmse=result.backtest_rmse,
        backtest_mase=result.backtest_mase,
        baseline_mase=result.baseline_mase,
        status=result.status,
        error_message=result.error_message,
    )
    session.add(row)
    session.flush()
