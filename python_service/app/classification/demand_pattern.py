"""
classification/demand_pattern.py — Klasifikasi pola permintaan Syntetos-Boylan-Croston (SBC).

Referensi:
  Syntetos, A.A., Boylan, J.E., Croston, J.D. (2005).
  "On the Categorisation of Demand Patterns." JORS 56(5), 495-503.

Definisi:
  ADI  = jumlah_periode / jumlah_periode_dengan_demand_positif
         (Average Demand Interval)
  CV²  = (std / mean)² dihitung HANYA pada nilai demand NON-NOL
         (Squared Coefficient of Variation)

Threshold (dari config, default Syntetos-Boylan):
  ADI_threshold = 1.32
  CV2_threshold = 0.49

Kuadran:
  ADI < 1.32  AND CV² < 0.49  → smooth
  ADI >= 1.32 AND CV² < 0.49  → intermittent
  ADI < 1.32  AND CV² >= 0.49 → erratic
  ADI >= 1.32 AND CV² >= 0.49 → lumpy

Perhatian:
  - Minimal 30 hari histori agar klasifikasi dipercaya (is_reliable = True).
  - CV² dihitung HANYA pada nilai non-nol (bukan termasuk hari tanpa demand).
  - ADI menggunakan seluruh periode (termasuk nol).
"""
from __future__ import annotations

import logging
from dataclasses import dataclass
from typing import Optional

import numpy as np

from app.config import get_settings

logger = logging.getLogger(__name__)
cfg = get_settings()


@dataclass
class DemandPatternResult:
    item_id: int
    demand_pattern: str          # 'smooth' | 'intermittent' | 'erratic' | 'lumpy'
    adi: Optional[float]         # Average Demand Interval
    cv2: Optional[float]         # Squared CV pada nilai non-nol
    history_days: int            # Jumlah hari data yang dipakai
    is_reliable: bool            # True jika history_days >= min_history_days_ml


def classify_demand_pattern(
    item_id: int,
    demand_series: list[float],  # demand_clean harian (sudah termasuk imputasi, exclude NULL)
) -> DemandPatternResult:
    """
    Klasifikasi pola permintaan berdasarkan ADI dan CV².

    Args:
        item_id       : ID item
        demand_series : List demand_clean harian. Null/None sudah difilter sebelum masuk.

    Returns:
        DemandPatternResult
    """
    n = len(demand_series)
    is_reliable = n >= cfg.min_history_days_ml

    if n == 0:
        return DemandPatternResult(
            item_id=item_id,
            demand_pattern="smooth",
            adi=None,
            cv2=None,
            history_days=0,
            is_reliable=False,
        )

    arr = np.array(demand_series, dtype=float)

    # Periode dengan demand > 0 (tidak menggunakan tolerance, demand sudah diimputasi)
    nonzero_mask = arr > 0
    n_nonzero = int(nonzero_mask.sum())

    # ── ADI ──────────────────────────────────────────────────────────────────
    # ADI = total_periods / periods_with_positive_demand
    if n_nonzero == 0:
        # Tidak ada demand sama sekali → ADI sangat besar, pola lumpy
        adi = float("inf")
    else:
        adi = n / n_nonzero

    # ── CV² ──────────────────────────────────────────────────────────────────
    # Dihitung HANYA pada nilai NON-NOL
    nonzero_vals = arr[nonzero_mask]

    if len(nonzero_vals) < 2:
        # Tidak cukup data non-nol untuk hitung variasi → assume lumpy
        cv2 = float("inf")
    else:
        mean_nz = float(np.mean(nonzero_vals))
        std_nz = float(np.std(nonzero_vals, ddof=1))
        if mean_nz <= 0:
            cv2 = float("inf")
        else:
            cv2 = (std_nz / mean_nz) ** 2

    # ── Kuadran Syntetos-Boylan ───────────────────────────────────────────────
    adi_t = cfg.adi_threshold  # default 1.32
    cv2_t = cfg.cv2_threshold  # default 0.49

    # Handle inf (semua demand nol atau hanya 1 nilai non-nol)
    adi_finite = adi if np.isfinite(adi) else adi_t * 100
    cv2_finite = cv2 if np.isfinite(cv2) else cv2_t * 100

    if adi_finite < adi_t and cv2_finite < cv2_t:
        pattern = "smooth"
    elif adi_finite >= adi_t and cv2_finite < cv2_t:
        pattern = "intermittent"
    elif adi_finite < adi_t and cv2_finite >= cv2_t:
        pattern = "erratic"
    else:
        pattern = "lumpy"

    return DemandPatternResult(
        item_id=item_id,
        demand_pattern=pattern,
        adi=round(float(adi), 4) if np.isfinite(adi) else None,
        cv2=round(float(cv2), 4) if np.isfinite(cv2) else None,
        history_days=n,
        is_reliable=is_reliable,
    )
