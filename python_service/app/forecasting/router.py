"""
forecasting/router.py — Routing model forecast berdasarkan pola permintaan.

Logika routing:
  smooth    → [LightGBM, MA28, AutoETS] → pilih MASE terendah
  erratic   → [LightGBM, MA28, AutoETS] → pilih MASE terendah
  intermittent → [SBA, Croston, TSB]    → pilih MASE terendah (baseline = Croston)
  lumpy        → [SBA, Croston, TSB]    → pilih MASE terendah; sigma × lumpy_sigma_factor

Jika backtest gagal atau data tidak cukup untuk semua model:
  → ForecastRunStatus = SKIPPED atau FAILED (dikembalikan oleh runner)

Output per SKU:
  mu_daily     = mean forecast harian (rata-rata dari horizon h)
  sigma_daily  = RMSE backtest residuals (× lumpy_sigma_factor jika lumpy)
  model_used   = nama model terpilih
  backtest_mae/rmse/mase = dari BacktestResult
  baseline_mase = MASE baseline (MA28 atau Croston)
"""
from __future__ import annotations

import logging
from dataclasses import dataclass
from typing import Optional

import numpy as np

from app.config import get_settings
from app.forecasting import moving_average, lgbm_model, sba_model
from app.forecasting.backtest import rolling_origin_backtest, select_best_model, BacktestResult

logger = logging.getLogger(__name__)
cfg = get_settings()


@dataclass
class ForecastOutput:
    mu_daily: Optional[float]
    sigma_daily: Optional[float]
    model_used: Optional[str]
    demand_pattern_used: Optional[str]
    backtest_mae: Optional[float]
    backtest_rmse: Optional[float]
    backtest_mase: Optional[float]
    baseline_mase: Optional[float]
    status: str  # 'SUCCESS' | 'FAILED' | 'SKIPPED'
    error_message: Optional[str] = None


def _try_autoets(train: np.ndarray, horizon: int) -> np.ndarray:
    """AutoETS via statsforecast. Fallback ke MA28 jika gagal."""
    try:
        from statsforecast import StatsForecast
        from statsforecast.models import AutoETS
        import pandas as pd

        df = pd.DataFrame({
            "unique_id": ["item"],
            "ds": pd.date_range("2020-01-01", periods=len(train), freq="D"),
            "y": train,
        })
        sf = StatsForecast(models=[AutoETS(season_length=7)], freq="D")
        sf.fit(df)
        fc = sf.predict(h=horizon)
        return np.clip(fc["AutoETS"].values, 0, None)
    except Exception as e:
        logger.debug(f"AutoETS gagal ({e}), fallback MA28.")
        return moving_average.predict(train, horizon)


_AUTO_ETS_NAME = "AUTO_ETS"


def route_and_forecast(
    item_id: int,
    demand_series: list[float],
    demand_pattern: str,
    lead_time_days: int,
    is_reliable: bool,
) -> ForecastOutput:
    """
    Main entry point: pilih kandidat model, jalankan backtest, return output.

    Args:
        item_id        : untuk logging
        demand_series  : demand_clean harian (None sudah difilter)
        demand_pattern : 'smooth' | 'erratic' | 'intermittent' | 'lumpy'
        lead_time_days : horizon backtest (= LT item)
        is_reliable    : apakah klasifikasi dipercaya (history >= 30 hari)
    """
    if not is_reliable or len(demand_series) < cfg.min_history_days_ml:
        return ForecastOutput(
            mu_daily=None,
            sigma_daily=None,
            model_used=None,
            demand_pattern_used=demand_pattern,
            backtest_mae=None,
            backtest_rmse=None,
            backtest_mase=None,
            baseline_mase=None,
            status="SKIPPED",
            error_message=f"history_days={len(demand_series)} < min={cfg.min_history_days_ml}",
        )

    horizon = max(1, lead_time_days)
    arr = np.array(demand_series, dtype=float)

    try:
        if demand_pattern in ("smooth", "erratic"):
            return _route_smooth_erratic(item_id, arr, demand_pattern, horizon)
        else:
            return _route_intermittent_lumpy(item_id, arr, demand_pattern, horizon)
    except Exception as e:
        logger.exception(f"Item {item_id}: router error — {e}")
        return ForecastOutput(
            mu_daily=None,
            sigma_daily=None,
            model_used=None,
            demand_pattern_used=demand_pattern,
            backtest_mae=None,
            backtest_rmse=None,
            backtest_mase=None,
            baseline_mase=None,
            status="FAILED",
            error_message=str(e),
        )


def _route_smooth_erratic(
    item_id: int, arr: np.ndarray, pattern: str, horizon: int
) -> ForecastOutput:
    """Kandidat: LightGBM, MA28, AutoETS. Baseline: MA28."""
    candidates = {
        lgbm_model.MODEL_NAME: lgbm_model.predict,
        moving_average.MODEL_NAME: moving_average.predict,
        _AUTO_ETS_NAME: _try_autoets,
    }
    baseline_name = moving_average.MODEL_NAME

    results: dict[str, Optional[BacktestResult]] = {}
    for name, fn in candidates.items():
        results[name] = rolling_origin_backtest(
            list(arr), fn, horizon, cfg.backtest_min_folds
        )

    best_name, best_result = select_best_model(results, baseline_name)
    baseline_result = results.get(baseline_name)

    # Hitung mu_daily dari seluruh training series (rata-rata forecast horizon)
    forecast = candidates[best_name](arr, horizon)
    mu_daily = float(np.mean(forecast)) if len(forecast) > 0 else 0.0
    mu_daily = max(0.0, mu_daily)

    sigma_daily = best_result.sigma_daily if best_result else _fallback_sigma(arr)

    return ForecastOutput(
        mu_daily=round(mu_daily, 6),
        sigma_daily=round(sigma_daily, 6),
        model_used=best_name,
        demand_pattern_used=pattern,
        backtest_mae=best_result.mae if best_result else None,
        backtest_rmse=best_result.rmse if best_result else None,
        backtest_mase=best_result.mase if best_result else None,
        baseline_mase=baseline_result.mase if baseline_result else None,
        status="SUCCESS",
    )


def _route_intermittent_lumpy(
    item_id: int, arr: np.ndarray, pattern: str, horizon: int
) -> ForecastOutput:
    """Kandidat: SBA, Croston, TSB. Baseline: Croston."""
    candidates = {
        sba_model.MODEL_NAME_SBA: sba_model.predict_sba,
        sba_model.MODEL_NAME_CROSTON: sba_model.predict_croston,
        sba_model.MODEL_NAME_TSB: sba_model.predict_tsb,
    }
    baseline_name = sba_model.MODEL_NAME_CROSTON

    results: dict[str, Optional[BacktestResult]] = {}
    for name, fn in candidates.items():
        results[name] = rolling_origin_backtest(
            list(arr), fn, horizon, cfg.backtest_min_folds
        )

    best_name, best_result = select_best_model(results, baseline_name)
    baseline_result = results.get(baseline_name)

    forecast = candidates[best_name](arr, horizon)
    mu_daily = float(np.mean(forecast)) if len(forecast) > 0 else 0.0
    mu_daily = max(0.0, mu_daily)

    sigma_daily = best_result.sigma_daily if best_result else _fallback_sigma(arr)

    # Pola lumpy: kalikan sigma dengan faktor dari config (default 1.25)
    if pattern == "lumpy":
        sigma_daily *= cfg.lumpy_sigma_factor

    return ForecastOutput(
        mu_daily=round(mu_daily, 6),
        sigma_daily=round(sigma_daily, 6),
        model_used=best_name,
        demand_pattern_used=pattern,
        backtest_mae=best_result.mae if best_result else None,
        backtest_rmse=best_result.rmse if best_result else None,
        backtest_mase=best_result.mase if best_result else None,
        baseline_mase=baseline_result.mase if baseline_result else None,
        status="SUCCESS",
    )


def _fallback_sigma(arr: np.ndarray) -> float:
    """Sigma fallback jika backtest tidak bisa jalan: std deviasi series."""
    if len(arr) < 2:
        return 0.0
    return max(0.0, float(np.std(arr[-28:], ddof=1)))
