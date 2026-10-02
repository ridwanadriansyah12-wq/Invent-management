"""stock_movement.py — SQLAlchemy ORM untuk tabel stock_movements (READ-ONLY)."""
from datetime import date
from decimal import Decimal

from sqlalchemy import Date, Enum, Integer, Numeric, String, ForeignKey
from sqlalchemy.orm import Mapped, mapped_column

from app.database import Base


class StockMovementORM(Base):
    __tablename__ = "stock_movements"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    item_id: Mapped[int] = mapped_column(Integer, ForeignKey("items.id"))
    movement_date: Mapped[date] = mapped_column(Date)
    type: Mapped[str] = mapped_column(String(10))
    reason: Mapped[str] = mapped_column(
        Enum("ISSUE", "RECEIPT", "RETURN", "ADJUSTMENT", "OPENING_BALANCE"),
        default="ISSUE",
    )
    qty: Mapped[Decimal] = mapped_column(Numeric(12, 3))
