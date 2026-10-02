"""
forecasting/sba_model.py — SBA (Syntetos-Boylan Approximation), Croston, dan TSB.

Digunakan untuk pola: intermittent dan lumpy.

Referensi:
  Croston, J.D. (1972). "Forecasting and Stock Control for Intermittent Demands."
  Operational Research Quarterly 23(3), 289-303.

  Syntetos, A.A. & Boylan, J.E. (2005). "The Accuracy of Intermittent Demand Estimates."
  International Journal of Forecasting 21(2), 303-314.

  Teunter, R., Syntetos, A.A., Zied Babai, M. (2011).
  "Intermittent demand: Linking forecasting to inventory obsolescence."
  European Journal of Operational Research 214(3), 606-615.

Implementasi:
  Menggunakan library statsforecast (Nixtla) yang sudah mencakup Croston, ADIDA, dan TSB.
  Fallback ke implementasi Python murni jika statsforecast tidak tersedia.
"""
from __future__ import annotations

import logging
from typing import Optional

import numpy as np

logger = logging.getLogger(__name__)

MODEL_NAME_SBA = "SBA"
MODEL_NAME_CROSTON = "CROSTON"
MODEL_NAME_TSB = "TSB"

# Smoothing parameter untuk Croston/SBA/TSB
ALPHA_DEMAND = 0.1
ALPHA_INTERVAL = 0.1


# ── Implementasi murni Croston (fallback tanpa statsforecast) ─────────────────

def _croston_pure(series: np.ndarray, horizon: int) -> np.ndarray:
    """
    Croston's method: estimasi demand per periode (termasuk periode nol).

    Algoritma:
      z[t]: smoothed demand size (hanya di-update saat demand > 0)
      p[t]: smoothed inter-demand interval (hanya di-update saat demand > 0)
      forecast = z / p
    """
    z = float(series[series > 0].mean()) if (series > 0).any() else 1.0
    p = float(len(series) / max(1, (series > 0).sum()))

    q = 0  # periode sejak terakhir demand positif

    for d in series:
        if d > 0:
            z = ALPHA_DEMAND * d + (1 - ALPHA_DEMAND) * z
            p = ALPHA_INTERVAL * (q + 1) + (1 - ALPHA_INTERVAL) * p
            q = 0
        else:
            q += 1

    forecast_val = max(0.0, z / max(p, 1e-9))
    return np.full(horizon, forecast_val)


def _sba_pure(series: np.ndarray, horizon: int) -> np.ndarray:
    """
    SBA (Syntetos-Boylan Approximation):
    forecast = (1 - alpha/2) × Croston_forecast
    Koreksi bias Croston.
    """
    croston_forecast = _croston_pure(series, horizon)
    correction = 1.0 - ALPHA_DEMAND / 2.0
    return croston_forecast * correction


def _tsb_pure(series: np.ndarray, horizon: int) -> np.ndarray:
    """
    TSB (Teunter-Syntetos-Babai):
    Forecast = p_hat × z_hat
    Dimana p_hat = probability of demand (bukan interval).
    Lebih baik untuk demand yang mungkin obsolete.
    """
    n = len(series)
    if n == 0:
        return np.zeros(horizon)

    # Inisialisasi
    p_hat = float((series > 0).sum()) / n  # probability demand > 0
    z_hat = float(series[series > 0].mean()) if (series > 0).any() else 0.0

    alpha_p = ALPHA_INTERVAL
    alpha_z = ALPHA_DEMAND

    for d in series:
        if d > 0:
            p_hat = (1 - alpha_p) * p_hat + alpha_p * 1.0
            z_hat = (1 - alpha_z) * z_hat + alpha_z * d
        else:
            p_hat = (1 - alpha_p) * p_hat

    forecast_val = max(0.0, p_hat * z_hat)
    return np.full(horizon, forecast_val)


# ── Wrapper publik ────────────────────────────────────────────────────────────

def predict_croston(train: np.ndarray, horizon: int) -> np.ndarray:
    try:
        from statsforecast.models import CrostonClassic
        from statsforecast import StatsForecast
        import pandas as pd

        df = pd.DataFrame({
            "unique_id": ["item"],
            "ds": pd.date_range("2020-01-01", periods=len(train), freq="D"),
            "y": train,
        })
        model = StatsForecast(models=[CrostonClassic()], freq="D")
        model.fit(df)
        fc = model.predict(h=horizon)
        return np.clip(fc["CrostonClassic"].values, 0, None)
    except Exception as e:
        logger.debug(f"statsforecast Croston gagal ({e}), pakai pure Python.")
        return _croston_pure(train, horizon)


def predict_sba(train: np.ndarray, horizon: int) -> np.ndarray:
    try:
        from statsforecast.models import CrostonSBA
        from statsforecast import StatsForecast
        import pandas as pd

        df = pd.DataFrame({
            "unique_id": ["item"],
            "ds": pd.date_range("2020-01-01", periods=len(train), freq="D"),
            "y": train,
        })
        model = StatsForecast(models=[CrostonSBA()], freq="D")
        model.fit(df)
        fc = model.predict(h=horizon)
        return np.clip(fc["CrostonSBA"].values, 0, None)
    except Exception as e:
        logger.debug(f"statsforecast SBA gagal ({e}), pakai pure Python.")
        return _sba_pure(train, horizon)


def predict_tsb(train: np.ndarray, horizon: int) -> np.ndarray:
    try:
        from statsforecast.models import TSB
        from statsforecast import StatsForecast
        import pandas as pd

        df = pd.DataFrame({
            "unique_id": ["item"],
            "ds": pd.date_range("2020-01-01", periods=len(train), freq="D"),
            "y": train,
        })
        model = StatsForecast(models=[TSB(alpha_d=ALPHA_DEMAND, alpha_p=ALPHA_INTERVAL)], freq="D")
        model.fit(df)
        fc = model.predict(h=horizon)
        return np.clip(fc["TSB"].values, 0, None)
    except Exception as e:
        logger.debug(f"statsforecast TSB gagal ({e}), pakai pure Python.")
        return _tsb_pure(train, horizon)
