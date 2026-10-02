"""daily_demand.py — SQLAlchemy ORM untuk tabel daily_demand (INSERT/UPDATE dari Python)."""
from datetime import date
from decimal import Decimal

from sqlalchemy import Boolean, Date, Enum, Integer, Numeric, String, ForeignKey, func
from sqlalchemy.orm import Mapped, mapped_column

from app.database import Base


class DailyDemandORM(Base):
    __tablename__ = "daily_demand"

    id: Mapped[int] = mapped_column(Integer, primary_key=True, autoincrement=True)
    item_id: Mapped[int] = mapped_column(Integer, ForeignKey("items.id"))
    date: Mapped[date] = mapped_column(Date)
    opening_stock: Mapped[Decimal] = mapped_column(Numeric(12, 3), default=0)
    receipt_qty: Mapped[Decimal] = mapped_column(Numeric(12, 3), default=0)
    issued_qty: Mapped[Decimal] = mapped_column(Numeric(12, 3), default=0)
    is_censored: Mapped[bool] = mapped_column(Boolean, default=False)
    demand_clean: Mapped[Decimal | None] = mapped_column(Numeric(12, 3), nullable=True)
    imputation_method: Mapped[str] = mapped_column(
        Enum("NONE", "MEAN_UNCENSORED_28D", "MEAN_UNCENSORED_FULL", "INSUFFICIENT_DATA"),
        default="NONE",
    )
    created_at: Mapped[date | None] = mapped_column(server_default=func.now())
    updated_at: Mapped[date | None] = mapped_column(
        server_default=func.now(), onupdate=func.now()
    )
