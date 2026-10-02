"""
classification/abc_xyz.py — Klasifikasi ABC dan XYZ per SKU.

Sumber:
  ABC: Wild (1997), Pareto principle pada annual_value.
  XYZ: Variabilitas CV = std/mean dari demand bulanan.

ABC:
  annual_value = SUM(demand_clean 365 hari) × unit_cost
  Urutkan semua SKU descending → hitung cumulative share:
    A = 0-80%, B = 80-95%, C = 95-100%
  (threshold dari config, bukan hardcode)

XYZ:
  Per SKU, hitung demand bulanan (aggregasi demand_clean per bulan).
  CV = std(demand_bulanan) / mean(demand_bulanan)
    X: CV < xyz_x_upper  (0.5)
    Y: xyz_x_upper <= CV < xyz_y_upper  (0.5-1.0)
    Z: CV >= xyz_y_upper  (1.0)
  Jika mean = 0 atau hanya 1 bulan data: default Z.
"""
from __future__ import annotations

import logging
from dataclasses import dataclass
from typing import Optional

import numpy as np
import pandas as pd

from app.config import get_settings

logger = logging.getLogger(__name__)
cfg = get_settings()


@dataclass
class AbcResult:
    item_id: int
    abc_class: str  # 'A' | 'B' | 'C'
    annual_value: float


@dataclass
class XyzResult:
    item_id: int
    xyz_class: str  # 'X' | 'Y' | 'Z'
    monthly_cv: Optional[float]


def classify_abc(items_demand: dict[int, tuple[float, float]]) -> dict[int, AbcResult]:
    """
    Klasifikasi ABC untuk sekumpulan SKU.

    Args:
        items_demand: {item_id: (total_demand_clean_365d, unit_cost)}

    Returns:
        {item_id: AbcResult}

    Catatan:
        Nilai annual_value = total_demand_clean × unit_cost (proxy untuk nilai tahunan).
        Threshold diambil dari config (a_upper=0.80, b_upper=0.95).
    """
    if not items_demand:
        return {}

    records = [
        {
            "item_id": item_id,
            "annual_value": float(total_demand) * float(unit_cost),
        }
        for item_id, (total_demand, unit_cost) in items_demand.items()
    ]

    df = pd.DataFrame(records)
    df = df.sort_values("annual_value", ascending=False).reset_index(drop=True)
    total = df["annual_value"].sum()

    results: dict[int, AbcResult] = {}

    if total <= 0:
        # Semua nilai nol: semua masuk kelas C
        for _, row in df.iterrows():
            results[int(row["item_id"])] = AbcResult(
                item_id=int(row["item_id"]),
                abc_class="C",
                annual_value=float(row["annual_value"]),
            )
        return results

    df["cum_share"] = df["annual_value"].cumsum() / total

    for _, row in df.iterrows():
        cum_share = float(row["cum_share"])

        if cum_share <= cfg.abc_a_upper:
            abc_class = "A"
        elif cum_share <= cfg.abc_b_upper:
            abc_class = "B"
        else:
            abc_class = "C"

        results[int(row["item_id"])] = AbcResult(
            item_id=int(row["item_id"]),
            abc_class=abc_class,
            annual_value=float(row["annual_value"]),
        )

    return results


def classify_xyz(item_id: int, daily_clean: list[float]) -> XyzResult:
    """
    Klasifikasi XYZ untuk satu SKU berdasarkan demand_clean harian.

    CV = std(monthly_demand) / mean(monthly_demand)
    Jika data tidak cukup → default Z.

    Args:
        item_id    : ID item
        daily_clean: List demand_clean harian (sudah diimputasi)
    """
    if not daily_clean or len(daily_clean) < 2:
        return XyzResult(item_id=item_id, xyz_class="Z", monthly_cv=None)

    # Agregasi ke bulan: tidak perlu tanggal aktual, cukup bucket 30 hari
    arr = np.array(daily_clean, dtype=float)
    n_months = max(1, len(arr) // 30)
    monthly = [arr[i * 30 : (i + 1) * 30].sum() for i in range(n_months)]

    if len(monthly) < 2:
        return XyzResult(item_id=item_id, xyz_class="Z", monthly_cv=None)

    mean_m = float(np.mean(monthly))
    if mean_m <= 0:
        return XyzResult(item_id=item_id, xyz_class="Z", monthly_cv=None)

    cv = float(np.std(monthly, ddof=1)) / mean_m

    if cv < cfg.xyz_x_upper:
        xyz_class = "X"
    elif cv < cfg.xyz_y_upper:
        xyz_class = "Y"
    else:
        xyz_class = "Z"

    return XyzResult(item_id=item_id, xyz_class=xyz_class, monthly_cv=round(cv, 6))
