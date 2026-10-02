"""item.py — SQLAlchemy ORM untuk tabel items (READ-ONLY dari Python)."""
from datetime import date
from decimal import Decimal

from sqlalchemy import Boolean, Date, Integer, Numeric, String, ForeignKey
from sqlalchemy.orm import Mapped, mapped_column

from app.database import Base


class ItemORM(Base):
    __tablename__ = "items"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    sku: Mapped[str] = mapped_column(String(50))
    name: Mapped[str] = mapped_column(String(200))
    category_id: Mapped[int] = mapped_column(Integer, ForeignKey("categories.id"))
    unit_cost: Mapped[Decimal] = mapped_column(Numeric(15, 4), default=0)
    lead_time_days: Mapped[int] = mapped_column(Integer, default=15)
    lead_time_std_days: Mapped[Decimal] = mapped_column(Numeric(5, 2), default=0)
    first_movement_date: Mapped[date | None] = mapped_column(Date, nullable=True)
    is_active: Mapped[bool] = mapped_column(Boolean, default=True)
