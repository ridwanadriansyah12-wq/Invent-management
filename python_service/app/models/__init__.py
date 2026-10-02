"""
models/__init__.py — SQLAlchemy ORM models untuk Python service.

Python HANYA membaca stock_movements, items, categories, category_defaults.
Python INSERT/UPDATE ke: daily_demand, item_classifications, forecast_runs.
Python TIDAK PERNAH menyentuh tabel lain.
"""
from app.models.item import ItemORM
from app.models.stock_movement import StockMovementORM
from app.models.daily_demand import DailyDemandORM
from app.models.item_classification import ItemClassificationORM
from app.models.forecast_run import ForecastRunORM

__all__ = [
    "ItemORM",
    "StockMovementORM",
    "DailyDemandORM",
    "ItemClassificationORM",
    "ForecastRunORM",
]
