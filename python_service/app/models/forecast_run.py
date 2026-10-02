"""forecast_run.py — SQLAlchemy ORM untuk tabel forecast_runs (INSERT dari Python, idempotent)."""
from datetime import datetime
from decimal import Decimal

from sqlalchemy import Enum, Float, Integer, Numeric, SmallInteger, String, Text, ForeignKey, func
from sqlalchemy.orm import Mapped, mapped_column

from app.database import Base


class ForecastRunORM(Base):
    __tablename__ = "forecast_runs"

    id: Mapped[int] = mapped_column(Integer, primary_key=True, autoincrement=True)
    run_id: Mapped[str] = mapped_column(String(36), index=True)  # UUID dari Laravel
    item_id: Mapped[int] = mapped_column(Integer, ForeignKey("items.id"))
    run_at: Mapped[datetime] = mapped_column(server_default=func.now())
    mu_daily: Mapped[float | None] = mapped_column(Float, nullable=True)
    sigma_daily: Mapped[float | None] = mapped_column(Float, nullable=True)
    model_used: Mapped[str | None] = mapped_column(String(50), nullable=True)
    demand_pattern_used: Mapped[str | None] = mapped_column(
        Enum("smooth", "intermittent", "erratic", "lumpy"), nullable=True
    )
    backtest_mae: Mapped[float | None] = mapped_column(Float, nullable=True)
    backtest_rmse: Mapped[float | None] = mapped_column(Float, nullable=True)
    backtest_mase: Mapped[float | None] = mapped_column(Float, nullable=True)
    baseline_mase: Mapped[float | None] = mapped_column(Float, nullable=True)
    status: Mapped[str] = mapped_column(
        Enum("SUCCESS", "FAILED", "SKIPPED"), default="SUCCESS"
    )
    error_message: Mapped[str | None] = mapped_column(Text, nullable=True)
    created_at: Mapped[datetime | None] = mapped_column(server_default=func.now())
    updated_at: Mapped[datetime | None] = mapped_column(
        server_default=func.now(), onupdate=func.now()
    )
