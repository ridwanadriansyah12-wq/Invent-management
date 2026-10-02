"""
forecasting/moving_average.py — Moving Average 28 hari (baseline untuk smooth/erratic).

MA28 dipilih sebagai baseline karena mudah diinterpretasikan dan sering kompetitif
pada data stok yang relatif stabil. Jika LightGBM / AutoETS tidak mengalahkan MA28,
maka MA28 dipakai sebagai model final.
"""
from __future__ import annotations

import numpy as np


MODEL_NAME = "MOVING_AVERAGE_28D"
WINDOW = 28


def predict(train: np.ndarray, horizon: int) -> np.ndarray:
    """
    Forecast dengan moving average 28 hari terakhir dari training set.

    Args:
        train  : Array demand training harian
        horizon: Jumlah hari ke depan

    Returns:
        Array forecast (konstan = rata-rata 28 hari terakhir)
    """
    if len(train) == 0:
        return np.zeros(horizon)

    window_data = train[-WINDOW:]
    mean_val = float(np.mean(window_data))
    return np.full(horizon, max(0.0, mean_val))


def estimate_mu_sigma(series: list[float]) -> tuple[float, float]:
    """
    Estimasi mu_daily dan sigma_daily dari MA28 tanpa backtest.
    Digunakan sebagai fallback cepat.
    """
    if not series:
        return 0.0, 0.0
    arr = np.array(series[-WINDOW:], dtype=float)
    mu = float(np.mean(arr))
    sigma = float(np.std(arr, ddof=1)) if len(arr) > 1 else 0.0
    return max(0.0, mu), max(0.0, sigma)
