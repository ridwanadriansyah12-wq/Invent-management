"""conftest.py — Konfigurasi pytest global untuk python_service."""
import os
import sys

# Pastikan root python_service ada di PYTHONPATH
sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

# Override settings untuk test (tidak perlu .env)
os.environ.setdefault("API_KEY", "test-api-key")
os.environ.setdefault("DB_HOST", "127.0.0.1")
os.environ.setdefault("DB_USER", "test")
os.environ.setdefault("DB_PASSWORD", "test")
os.environ.setdefault("DB_NAME", "test")
os.environ.setdefault("MIN_HISTORY_DAYS_ML", "30")
os.environ.setdefault("MIN_UNCENSORED_DAYS", "2")
os.environ.setdefault("IMPUTATION_WINDOW_DAYS", "28")
os.environ.setdefault("CENSORED_TOLERANCE", "0.0005")
os.environ.setdefault("BACKTEST_MIN_FOLDS", "3")
os.environ.setdefault("LUMPY_SIGMA_FACTOR", "1.25")
os.environ.setdefault("ADI_THRESHOLD", "1.32")
os.environ.setdefault("CV2_THRESHOLD", "0.49")
