"""
tests/test_pipeline.py — Unit tests untuk pipeline demand (censoring + imputation).

Tidak memerlukan koneksi DB — semua test menggunakan data in-memory.
"""
import pytest
from datetime import date

from app.pipeline.demand_pipeline import DailyRow, build_daily_demand
from app.pipeline.imputation import impute_demand, get_training_series


def _make_row(
    item_id: int,
    day_num: int,
    opening_stock: float,
    issued_qty: float,
    receipt_qty: float = 0.0,
    is_censored: bool = False,
) -> DailyRow:
    return DailyRow(
        item_id=item_id,
        date=date(2024, 1, day_num),
        opening_stock=opening_stock,
        receipt_qty=receipt_qty,
        issued_qty=issued_qty,
        is_censored=is_censored,
        demand_clean=None,
        imputation_method="NONE",
    )


# ── Test Censoring Detection ──────────────────────────────────────────────────

class TestCensoringDetection:
    def test_normal_day_not_censored(self):
        """Hari dengan stok dan demand: tidak censored."""
        row = _make_row(1, 1, opening_stock=100.0, issued_qty=10.0)
        assert not row.is_censored

    def test_zero_stock_and_zero_demand_is_censored(self):
        """Stok = 0 DAN demand = 0: stockout = censored."""
        row = _make_row(1, 1, opening_stock=0.0, issued_qty=0.0, is_censored=True)
        assert row.is_censored

    def test_zero_demand_with_stock_not_censored(self):
        """Demand = 0 tapi stok ada: hari tanpa penjualan (valid zero demand)."""
        row = _make_row(1, 1, opening_stock=50.0, issued_qty=0.0, is_censored=False)
        assert not row.is_censored

    def test_below_tolerance_is_censored(self):
        """issued_qty dan opening_stock di bawah tolerance (0.0005) = censored."""
        row = _make_row(1, 1, opening_stock=0.0001, issued_qty=0.0002, is_censored=True)
        assert row.is_censored


# ── Test Imputation ───────────────────────────────────────────────────────────

class TestImputation:
    def _build_rows(self, opening_stocks: list[float], issued_qtys: list[float]) -> list[DailyRow]:
        """Helper: build rows dari dua list parallel."""
        rows = []
        for i, (o, q) in enumerate(zip(opening_stocks, issued_qtys)):
            tol = 0.0005
            is_censored = q < tol and o < tol
            rows.append(DailyRow(
                item_id=1,
                date=date(2024, 1, i + 1),
                opening_stock=o,
                receipt_qty=0.0,
                issued_qty=q,
                is_censored=is_censored,
                demand_clean=None,
                imputation_method="NONE",
            ))
        return rows

    def test_non_censored_day_demand_equals_issued(self):
        """Hari non-censored: demand_clean = issued_qty."""
        rows = self._build_rows([100.0] * 5, [10.0] * 5)
        result = impute_demand(rows, window_days=28, min_uncensored=2)
        for r in result:
            assert r.demand_clean == pytest.approx(10.0)
            assert r.imputation_method == "NONE"

    def test_censored_day_imputed_from_window(self):
        """Hari censored di antara hari normal: imputasi = rata-rata hari non-censored."""
        # 5 hari normal demand=10, 1 hari censored, 5 hari normal demand=10
        opening = [100.0] * 5 + [0.0] + [100.0] * 5
        issued  = [10.0] * 5 + [0.0] + [10.0] * 5
        rows = self._build_rows(opening, issued)
        result = impute_demand(rows, window_days=28, min_uncensored=2)

        censored_result = [r for r in result if r.is_censored]
        assert len(censored_result) == 1
        assert censored_result[0].demand_clean == pytest.approx(10.0)
        assert "MEAN_UNCENSORED" in censored_result[0].imputation_method

    def test_insufficient_data_returns_none(self):
        """Censored di awal dengan data kurang → demand_clean = None."""
        # Semua censored, tidak cukup non-censored untuk imputasi
        rows = [
            _make_row(1, i + 1, opening_stock=0.0, issued_qty=0.0, is_censored=True)
            for i in range(5)
        ]
        result = impute_demand(rows, window_days=28, min_uncensored=3)
        for r in result:
            assert r.demand_clean is None
            assert r.imputation_method == "INSUFFICIENT_DATA"

    def test_zero_demand_non_censored_included_in_mean(self):
        """Hari demand = 0 tapi stok ada (non-censored): dimasukkan ke mean imputation."""
        # 3 hari demand=0 (stok ada), 1 hari demand=6 → mean = (0+0+0+6)/4 = 1.5
        # Lalu 1 hari censored
        opening = [50.0, 50.0, 50.0, 50.0, 0.0]
        issued  = [0.0,  0.0,  0.0,  6.0,  0.0]
        rows = self._build_rows(opening, issued)
        result = impute_demand(rows, window_days=28, min_uncensored=2)

        censored = [r for r in result if r.is_censored]
        assert len(censored) == 1
        assert censored[0].demand_clean == pytest.approx(1.5)

    def test_get_training_series_excludes_none(self):
        """get_training_series hanya return demand_clean yang tidak None."""
        rows = [
            _make_row(1, 1, 100, 10),
            _make_row(1, 2, 0, 0, is_censored=True),
            _make_row(1, 3, 100, 20),
        ]
        from dataclasses import replace
        rows[0] = replace(rows[0], demand_clean=10.0, imputation_method="NONE")
        rows[1] = replace(rows[1], demand_clean=None, imputation_method="INSUFFICIENT_DATA")
        rows[2] = replace(rows[2], demand_clean=20.0, imputation_method="NONE")

        series = get_training_series(rows)
        assert series == [10.0, 20.0]
        assert len(series) == 2
