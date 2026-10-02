"""
forecasting/backtest.py — Rolling-origin backtest untuk evaluasi model forecast.

Referensi:
  Hyndman, R.J. & Koehler, A.B. (2006). "Another look at measures of forecast accuracy."
  International Journal of Forecasting 22(4), 679-688.

Metode:
  Rolling-origin (time series cross-validation):
  - Minimal cfg.backtest_min_folds fold
  - Horizon = lead_time_days item
  - Setiap fold: training pada data[0:t], prediksi data[t : t+horizon]
  - MASE: MAE dibagi MAE naive (lag-1) pada training set
  - sigma_daily = RMSE residual harian dari backtest

Catatan:
  - MAE baseline naive: mean(|y[t] - y[t-1]|) pada training set
  - MASE < 1 artinya model lebih baik dari naive forecast
"""
from __future__ import annotations

import logging
from dataclasses import dataclass
from typing import Callable, Optional

import numpy as np

from app.config import get_settings

logger = logging.getLogger(__name__)
cfg = get_settings()


@dataclass
class BacktestResult:
    mae: float
    rmse: float
    mase: float
    sigma_daily: float   # RMSE residual harian (dipakai sebagai sigma di rumus SS)
    n_folds: int


def _naive_mae(train: np.ndarray) -> float:
    """MAE naive forecast (lag-1) pada training set. Dipakai sebagai denominator MASE."""
    if len(train) < 2:
        return 1.0  # Avoid division by zero
    errors = np.abs(np.diff(train))
    return float(np.mean(errors)) if len(errors) > 0 else 1.0


def rolling_origin_backtest(
    series: list[float],
    model_fn: Callable[[np.ndarray, int], np.ndarray],
    horizon: int,
    min_folds: int,
) -> Optional[BacktestResult]:
    """
    Backtest rolling-origin untuk satu model.

    Args:
        series   : Demand series harian (sudah clean, tanpa None)
        model_fn : Callable(train_array, horizon) -> forecast_array (len=horizon)
        horizon  : Jumlah hari ke depan yang diprediksi (= lead_time_days)
        min_folds: Minimum jumlah fold backtest

    Returns:
        BacktestResult atau None jika data tidak cukup.
    """
    arr = np.array(series, dtype=float)
    n = len(arr)

    # Minimum data: harus bisa membuat min_folds fold
    # Setiap fold butuh minimal 2 data untuk training + horizon untuk testing
    min_required = horizon * (min_folds + 1)
    if n < min_required:
        logger.warning(
            f"Backtest: data tidak cukup ({n} < {min_required}). Tidak bisa backtest."
        )
        return None

    all_errors: list[float] = []

    # Tentukan titik origin: mulai dari indeks yang memberi cukup ruang untuk min_folds fold
    # Distribusikan fold secara merata
    test_start = n - horizon * min_folds
    origins = [test_start + i * (horizon // min_folds) for i in range(min_folds)]
    # Pastikan tidak melebihi batas array
    origins = [o for o in origins if o + horizon <= n and o > 0]

    if len(origins) == 0:
        return None

    for origin in origins:
        train = arr[:origin]
        actual = arr[origin : origin + horizon]

        try:
            forecast = model_fn(train, horizon)
        except Exception as e:
            logger.warning(f"Model gagal pada fold origin={origin}: {e}")
            continue

        if forecast is None or len(forecast) == 0:
            continue

        # Potong sesuai panjang aktual (bisa lebih pendek jika ujung array)
        min_len = min(len(actual), len(forecast))
        errors = actual[:min_len] - forecast[:min_len]
        all_errors.extend(errors.tolist())

    if not all_errors:
        return None

    errors_arr = np.array(all_errors)
    mae = float(np.mean(np.abs(errors_arr)))
    rmse = float(np.sqrt(np.mean(errors_arr ** 2)))

    # MASE: MAE / MAE_naive(seluruh series training)
    naive_mae_val = _naive_mae(arr[: origins[0]])  # training set fold pertama
    mase = mae / max(naive_mae_val, 1e-9)

    return BacktestResult(
        mae=round(mae, 6),
        rmse=round(rmse, 6),
        mase=round(mase, 6),
        sigma_daily=round(rmse, 6),  # sigma = RMSE backtest
        n_folds=len(origins),
    )


def select_best_model(
    backtest_results: dict[str, Optional[BacktestResult]],
    baseline_name: str,
) -> tuple[str, Optional[BacktestResult]]:
    """
    Pilih model dengan MASE terendah.
    Jika model ML tidak mengalahkan baseline (MASE_ML >= MASE_baseline): pakai baseline.

    Args:
        backtest_results: {model_name: BacktestResult}
        baseline_name   : Nama model baseline untuk perbandingan

    Returns:
        (model_name_terpilih, BacktestResult_terpilih)
    """
    valid = {name: r for name, r in backtest_results.items() if r is not None}
    if not valid:
        return baseline_name, None

    # Urutkan berdasarkan MASE
    sorted_models = sorted(valid.items(), key=lambda x: x[1].mase)
    best_name, best_result = sorted_models[0]

    # Cek apakah model terpilih adalah ML (bukan baseline)
    ml_candidates = {n: r for n, r in sorted_models if n != baseline_name}
    if not ml_candidates:
        return best_name, best_result

    ml_best_name, ml_best_result = list(ml_candidates.items())[0]
    baseline_result = valid.get(baseline_name)

    if baseline_result and ml_best_result.mase >= baseline_result.mase:
        # ML tidak mengalahkan baseline → gunakan baseline
        logger.info(
            f"Model ML ({ml_best_name}, MASE={ml_best_result.mase:.4f}) tidak mengalahkan "
            f"baseline ({baseline_name}, MASE={baseline_result.mase:.4f}). Pakai baseline."
        )
        return baseline_name, baseline_result

    return best_name, best_result
