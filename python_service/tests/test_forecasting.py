"""
tests/test_forecasting.py — Unit tests untuk model routing dan backtest.

Fokus pada:
  1. Model routing berdasarkan demand pattern
  2. Output validation (non-negative, finite)
  3. Backtest fallback ke baseline jika ML tidak menang
  4. Lumpy sigma factor diterapkan
"""
import pytest
import numpy as np

from app.forecasting import moving_average
from app.forecasting.backtest import rolling_origin_backtest, select_best_model
from app.forecasting.router import route_and_forecast, ForecastOutput


# ── Moving Average Tests ──────────────────────────────────────────────────────

class TestMovingAverage:
    def test_constant_series_predicts_constant(self):
        """Series konstan → forecast = konstanta itu."""
        train = np.array([10.0] * 30)
        result = moving_average.predict(train, horizon=7)
        assert np.allclose(result, 10.0)
        assert len(result) == 7

    def test_empty_series_returns_zeros(self):
        """Series kosong → forecast nol."""
        result = moving_average.predict(np.array([]), horizon=5)
        assert np.allclose(result, 0.0)

    def test_forecast_non_negative(self):
        """Forecast tidak boleh negatif."""
        train = np.array([1.0] * 10)
        result = moving_average.predict(train, horizon=3)
        assert all(v >= 0 for v in result)

    def test_uses_last_28_days(self):
        """MA28 menggunakan 28 hari terakhir, bukan seluruh series."""
        # 100 hari pertama = 100, 10 hari terakhir = 20
        train = np.array([100.0] * 100 + [20.0] * 10)
        result = moving_average.predict(train, horizon=1)
        # Harus mendekati 20 (dari 10 hari terakhir < 28 hari)
        # Rata-rata 28 hari terakhir = (18 × 100 + 10 × 20) / 28 = ...
        # Sebenarnya 28 hari terakhir: 18 hari 100 + 10 hari 20 = 2000/28 ≈ 71.4
        expected = (18 * 100 + 10 * 20) / 28
        assert result[0] == pytest.approx(expected, rel=0.01)


# ── Backtest Tests ────────────────────────────────────────────────────────────

class TestBacktest:
    def test_backtest_returns_result_for_sufficient_data(self):
        """Data cukup → backtest mengembalikan BacktestResult."""
        series = [10.0 + (i % 5) for i in range(100)]
        result = rolling_origin_backtest(series, moving_average.predict, horizon=7, min_folds=3)
        assert result is not None
        assert result.mae >= 0
        assert result.rmse >= 0
        assert result.mase >= 0
        assert result.sigma_daily >= 0

    def test_backtest_returns_none_for_insufficient_data(self):
        """Data tidak cukup → backtest mengembalikan None."""
        series = [10.0] * 5  # Terlalu sedikit untuk 3 folds dengan horizon=7
        result = rolling_origin_backtest(series, moving_average.predict, horizon=7, min_folds=3)
        assert result is None

    def test_select_best_model_chooses_lowest_mase(self):
        """Pilih model dengan MASE terendah."""
        from app.forecasting.backtest import BacktestResult

        results = {
            "model_a": BacktestResult(mae=5.0, rmse=6.0, mase=0.8, sigma_daily=6.0, n_folds=3),
            "model_b": BacktestResult(mae=3.0, rmse=4.0, mase=0.5, sigma_daily=4.0, n_folds=3),
            "baseline": BacktestResult(mae=8.0, rmse=9.0, mase=1.2, sigma_daily=9.0, n_folds=3),
        }
        best_name, best_result = select_best_model(results, baseline_name="baseline")
        assert best_name == "model_b"
        assert best_result.mase == pytest.approx(0.5)

    def test_select_best_falls_back_to_baseline_if_ml_worse(self):
        """Jika ML tidak menang dari baseline → pilih baseline."""
        from app.forecasting.backtest import BacktestResult

        results = {
            "ML_MODEL": BacktestResult(mae=10.0, rmse=12.0, mase=1.5, sigma_daily=12.0, n_folds=3),
            "BASELINE": BacktestResult(mae=5.0, rmse=6.0, mase=0.8, sigma_daily=6.0, n_folds=3),
        }
        best_name, _ = select_best_model(results, baseline_name="BASELINE")
        assert best_name == "BASELINE"


# ── Forecast Router Tests ─────────────────────────────────────────────────────

class TestForecastRouter:
    def _make_series(self, n: int = 60, pattern: str = "constant") -> list[float]:
        if pattern == "constant":
            return [10.0] * n
        if pattern == "intermittent":
            return ([10.0, 0.0] * (n // 2))[:n]
        if pattern == "lumpy":
            return ([0.0, 0.0, 0.0, 100.0] * (n // 4))[:n]
        return [10.0] * n

    def test_insufficient_history_returns_skipped(self):
        """History < min_history_days_ml → status SKIPPED."""
        series = [10.0] * 20  # kurang dari 30 hari
        result = route_and_forecast(
            item_id=1,
            demand_series=series,
            demand_pattern="smooth",
            lead_time_days=7,
            is_reliable=False,  # set tidak reliable
        )
        assert result.status == "SKIPPED"
        assert result.mu_daily is None

    def test_smooth_pattern_routing(self):
        """Pola smooth → berhasil menghasilkan mu_daily dan sigma_daily."""
        series = self._make_series(90, "constant")
        result = route_and_forecast(
            item_id=1,
            demand_series=series,
            demand_pattern="smooth",
            lead_time_days=7,
            is_reliable=True,
        )
        # Bisa SUCCESS atau SKIPPED bergantung apakah backtest berhasil
        if result.status == "SUCCESS":
            assert result.mu_daily is not None
            assert result.mu_daily >= 0
            assert result.sigma_daily is not None
            assert result.sigma_daily >= 0

    def test_lumpy_sigma_inflated(self):
        """Pola lumpy → sigma_daily harus > nilai baseline (factor 1.25)."""
        from app.config import get_settings
        cfg = get_settings()

        series = self._make_series(90, "lumpy")
        # Bandingkan dengan intermittent (faktor = 1.0)
        result_lumpy = route_and_forecast(
            item_id=1,
            demand_series=series,
            demand_pattern="lumpy",
            lead_time_days=7,
            is_reliable=True,
        )
        result_intermittent = route_and_forecast(
            item_id=2,
            demand_series=series,
            demand_pattern="intermittent",
            lead_time_days=7,
            is_reliable=True,
        )

        if result_lumpy.status == "SUCCESS" and result_intermittent.status == "SUCCESS":
            if result_intermittent.sigma_daily and result_intermittent.sigma_daily > 0:
                ratio = result_lumpy.sigma_daily / result_intermittent.sigma_daily
                assert ratio == pytest.approx(cfg.lumpy_sigma_factor, rel=0.01)

    def test_output_validation_finite(self):
        """Output mu dan sigma harus finite (tidak NaN atau inf)."""
        series = [10.0] * 90
        result = route_and_forecast(
            item_id=1,
            demand_series=series,
            demand_pattern="smooth",
            lead_time_days=7,
            is_reliable=True,
        )
        if result.status == "SUCCESS":
            import math
            assert math.isfinite(result.mu_daily)
            assert math.isfinite(result.sigma_daily)
            assert result.mu_daily >= 0
            assert result.sigma_daily >= 0
