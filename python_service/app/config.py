"""
config.py — Konfigurasi terpusat dari environment variables.
Semua angka konfigurasi dibaca dari .env, tidak ada hardcode.
"""
from functools import lru_cache
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=".env", extra="ignore")

    # ── Database ──────────────────────────────────────────────────────────
    db_host: str = "127.0.0.1"
    db_port: int = 3306
    db_name: str = "rop"
    db_user: str = "rop_python"
    db_password: str = ""

    # ── API Security ──────────────────────────────────────────────────────
    api_key: str = ""

    # ── Pipeline / Censoring ──────────────────────────────────────────────
    imputation_window_days: int = 28
    min_uncensored_days: int = 14
    min_history_days_ml: int = 30
    censored_tolerance: float = 0.0005  # < tolerance dianggap nol

    # ── Forecasting ───────────────────────────────────────────────────────
    lumpy_sigma_factor: float = 1.25
    backtest_min_folds: int = 3
    backtest_horizon_days: int = 15  # fallback; per-item pakai lead_time_days

    # ── Klasifikasi ADI/CV² threshold (Syntetos-Boylan-Croston) ──────────
    adi_threshold: float = 1.32
    cv2_threshold: float = 0.49

    # ── Klasifikasi XYZ threshold ─────────────────────────────────────────
    xyz_x_upper: float = 0.5
    xyz_y_upper: float = 1.0

    # ── ABC threshold ─────────────────────────────────────────────────────
    abc_a_upper: float = 0.80  # 0-80% = A
    abc_b_upper: float = 0.95  # 80-95% = B

    # ── Service ───────────────────────────────────────────────────────────
    chunk_size: int = 100
    log_level: str = "INFO"

    @property
    def database_url(self) -> str:
        return (
            f"mysql+pymysql://{self.db_user}:{self.db_password}"
            f"@{self.db_host}:{self.db_port}/{self.db_name}"
            f"?charset=utf8mb4"
        )


@lru_cache
def get_settings() -> Settings:
    return Settings()
