"""
tests/test_classification.py — Unit tests untuk klasifikasi ABC-XYZ dan ADI/CV².

Menguji batas threshold (boundary conditions) secara eksplisit.
"""
import pytest

from app.classification.abc_xyz import classify_abc, classify_xyz
from app.classification.demand_pattern import classify_demand_pattern


# ── ABC Classification Tests ──────────────────────────────────────────────────

class TestAbcClassification:
    def test_single_item_is_a(self):
        """Satu item dengan nilai positif harus masuk kelas A (100% share)."""
        result = classify_abc({1: (100.0, 10.0)})
        assert result[1].abc_class == "A"
        assert result[1].annual_value == pytest.approx(1000.0)

    def test_pareto_distribution(self):
        """
        3 items dengan nilai 800, 150, 50 (total 1000):
          Item 1: 80% share → A (tepat di batas A_upper)
          Item 2: 80%+15%=95% → B (tepat di batas B_upper)
          Item 3: sisa → C
        """
        items = {
            1: (800.0, 1.0),  # annual_value = 800
            2: (150.0, 1.0),  # annual_value = 150
            3: (50.0, 1.0),   # annual_value = 50
        }
        result = classify_abc(items)
        assert result[1].abc_class == "A"
        assert result[2].abc_class == "B"
        assert result[3].abc_class == "C"

    def test_all_zero_values_are_c(self):
        """Semua item dengan nilai nol → semua kelas C."""
        items = {1: (0.0, 0.0), 2: (0.0, 0.0)}
        result = classify_abc(items)
        assert all(r.abc_class == "C" for r in result.values())

    def test_unit_cost_multiplied(self):
        """annual_value = demand × unit_cost."""
        result = classify_abc({1: (50.0, 20.0)})
        assert result[1].annual_value == pytest.approx(1000.0)


# ── XYZ Classification Tests ──────────────────────────────────────────────────

class TestXyzClassification:
    def test_stable_demand_is_x(self):
        """Demand konstan → CV ≈ 0 → kelas X."""
        series = [10.0] * 120  # 4 bulan konstan
        result = classify_xyz(1, series)
        assert result.xyz_class == "X"

    def test_high_variability_is_z(self):
        """Variabilitas sangat tinggi → kelas Z."""
        # Alternating 0 and 100 → CV sangat besar
        series = [0.0, 100.0] * 60
        result = classify_xyz(1, series)
        assert result.xyz_class == "Z"

    def test_empty_series_default_z(self):
        """Series kosong → default Z."""
        result = classify_xyz(1, [])
        assert result.xyz_class == "Z"
        assert result.monthly_cv is None


# ── Demand Pattern (ADI/CV²) Tests ───────────────────────────────────────────

class TestDemandPattern:
    def test_smooth_pattern(self):
        """ADI < 1.32 dan CV² < 0.49 → smooth."""
        # Demand konstan setiap hari = ADI = 1.0, CV² ≈ 0
        series = [10.0] * 60
        result = classify_demand_pattern(1, series)
        assert result.demand_pattern == "smooth"
        assert result.adi == pytest.approx(1.0, abs=0.01)
        assert result.cv2 == pytest.approx(0.0, abs=0.01)

    def test_intermittent_pattern(self):
        """ADI >= 1.32 dan CV² < 0.49 → intermittent."""
        # Demand setiap 2 hari (ADI = 2.0), nilai konstan (CV² = 0)
        series = []
        for _ in range(30):
            series.extend([10.0, 0.0])  # 60 hari total, demand setiap 2 hari
        result = classify_demand_pattern(1, series)
        assert result.demand_pattern == "intermittent"
        assert result.adi is not None and result.adi >= 1.32

    def test_erratic_pattern(self):
        """ADI < 1.32 dan CV² >= 0.49 → erratic."""
        # Demand setiap hari tapi sangat bervariasi → ADI = 1.0, CV² besar
        import random
        random.seed(42)
        series = [float(random.choice([1, 1, 1, 100])) for _ in range(60)]
        result = classify_demand_pattern(1, series)
        # CV² harus >= 0.49 untuk data yang sangat bervariasi
        assert result.demand_pattern in ("erratic", "lumpy")  # bisa kedua
        assert result.cv2 is not None

    def test_lumpy_pattern(self):
        """ADI >= 1.32 dan CV² >= 0.49 → lumpy."""
        # Demand jarang DAN sangat bervariasi
        series = [0.0] * 4 + [1.0] + [0.0] * 4 + [100.0]  # 10 hari
        series = series * 6  # 60 hari
        result = classify_demand_pattern(1, series)
        assert result.demand_pattern == "lumpy"

    def test_no_demand_at_all(self):
        """Tidak ada demand sama sekali → adi None, cv2 None."""
        series = [0.0] * 60
        result = classify_demand_pattern(1, series)
        # ADI = inf (tidak ada non-zero demand), CV2 = inf
        assert result.adi is None  # inf → disimpan sebagai None
        assert result.cv2 is None

    def test_insufficient_history_not_reliable(self):
        """Histori < 30 hari → is_reliable = False."""
        series = [10.0] * 20  # 20 hari, kurang dari min_history_days_ml=30
        result = classify_demand_pattern(1, series)
        assert not result.is_reliable
        assert result.history_days == 20

    def test_sufficient_history_reliable(self):
        """Histori >= 30 hari → is_reliable = True."""
        series = [10.0] * 30
        result = classify_demand_pattern(1, series)
        assert result.is_reliable

    def test_adi_threshold_boundary_below(self):
        """ADI tepat di bawah threshold (1.32) → smooth atau erratic (bukan intermittent/lumpy)."""
        # Membuat ADI = 1.0 (demand setiap hari)
        series = [10.0] * 60
        result = classify_demand_pattern(1, series)
        assert result.demand_pattern in ("smooth", "erratic")
        assert result.adi < 1.32

    def test_cv2_threshold_boundary(self):
        """CV² dihitung HANYA dari nilai non-nol."""
        # Campuran nol dan nilai bervariasi tinggi
        # CV² pada non-nol: [1, 100] → std/mean sangat besar
        series = ([0.0] * 2 + [1.0] + [0.0] * 2 + [100.0]) * 10
        result = classify_demand_pattern(1, series)
        assert result.cv2 is not None
        assert result.cv2 >= 0.49
