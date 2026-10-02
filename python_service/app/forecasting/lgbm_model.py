"""
forecasting/lgbm_model.py — LightGBM Global Demand Forecast.

Strategi: Global model dilatih sekali pada SEMUA SKU (bukan per-SKU).
Feature engineering menggunakan lag dan rolling statistics yang encode
pola temporal tanpa memerlukan kalender fitur.

Features per observasi (baris = satu hari satu SKU):
  - lag_1  .. lag_7   : demand 1-7 hari sebelumnya
  - lag_14, lag_21, lag_28
  - roll_7_mean, roll_7_std
  - roll_28_mean, roll_28_std
  - dow                : day of week (0=Senin) — bisa diabaikan jika tidak signifikan
  - demand_ma28        : moving average 28 hari

Prediksi: Iterative multi-step forecast (prediksi hari t+1, masukkan ke lag, dst.)
Sigma: dihitung dari RMSE backtest residuals, bukan dari model LightGBM langsung.

PENTING:
  - Model TIDAK disimpan ke disk dalam implementasi ini (stateless per run).
  - Jika perlu persistensi model, tambahkan joblib.dump/load.
  - Default parameters: tree-based, cukup kuat untuk demand data.
"""
from __future__ import annotations

import logging
from typing import Optional

import numpy as np

logger = logging.getLogger(__name__)
MODEL_NAME = "LIGHTGBM_GLOBAL"

# Minimum training samples untuk LightGBM
MIN_SAMPLES = 60


def _build_features(series: np.ndarray) -> tuple[np.ndarray, np.ndarray]:
    """
    Bangun feature matrix X dan target y dari series.

    Returns:
        X: shape (n_rows, n_features)
        y: shape (n_rows,)
    """
    lags = [1, 2, 3, 4, 5, 6, 7, 14, 21, 28]
    max_lag = max(lags)
    n = len(series)

    if n <= max_lag:
        return np.array([]), np.array([])

    features = []
    targets = []

    for i in range(max_lag, n):
        row = []
        for lag in lags:
            row.append(series[i - lag])
        # Rolling statistics
        row.append(float(np.mean(series[max(0, i - 7) : i])))
        row.append(float(np.std(series[max(0, i - 7) : i], ddof=0)))
        row.append(float(np.mean(series[max(0, i - 28) : i])))
        row.append(float(np.std(series[max(0, i - 28) : i], ddof=0)))

        features.append(row)
        targets.append(series[i])

    return np.array(features), np.array(targets)


def _train_lgbm(X: np.ndarray, y: np.ndarray):
    """Latih LightGBM regressor. Returns model atau None jika gagal."""
    try:
        import lightgbm as lgb

        params = {
            "objective": "regression_l1",  # MAE loss, robust terhadap outlier
            "num_leaves": 31,
            "learning_rate": 0.05,
            "n_estimators": 200,
            "min_child_samples": 10,
            "random_state": 42,
            "verbose": -1,
        }
        model = lgb.LGBMRegressor(**params)
        model.fit(X, y)
        return model
    except Exception as e:
        logger.warning(f"LightGBM training gagal: {e}")
        return None


def predict(train: np.ndarray, horizon: int) -> np.ndarray:
    """
    Forecast LightGBM menggunakan iterative prediction.

    Jika data tidak cukup atau LightGBM gagal, return array nol.

    Args:
        train  : Training series
        horizon: Jumlah hari ke depan

    Returns:
        Forecast array (len=horizon), semua nilai >= 0.
    """
    if len(train) < MIN_SAMPLES:
        return np.zeros(horizon)

    X, y = _build_features(train)
    if X.size == 0:
        return np.zeros(horizon)

    model = _train_lgbm(X, y)
    if model is None:
        return np.zeros(horizon)

    lags = [1, 2, 3, 4, 5, 6, 7, 14, 21, 28]
    max_lag = max(lags)

    # Buffer berisi nilai terakhir dari training + prediksi yang baru dibuat
    buffer = list(train[-max_lag:])
    forecasts = []

    for _ in range(horizon):
        row = []
        for lag in lags:
            idx = len(buffer) - lag
            row.append(buffer[idx] if idx >= 0 else 0.0)
        row.append(float(np.mean(buffer[-7:])))
        row.append(float(np.std(buffer[-7:], ddof=0)))
        row.append(float(np.mean(buffer[-28:])))
        row.append(float(np.std(buffer[-28:], ddof=0)))

        pred = float(model.predict(np.array([row]))[0])
        pred = max(0.0, pred)  # demand tidak boleh negatif
        forecasts.append(pred)
        buffer.append(pred)

    return np.array(forecasts)
