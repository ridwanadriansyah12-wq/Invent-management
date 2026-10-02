"""item_classification.py — SQLAlchemy ORM untuk tabel item_classifications (UPSERT dari Python)."""
from datetime import datetime
from decimal import Decimal

from sqlalchemy import Boolean, Enum, Float, Integer, Numeric, SmallInteger, ForeignKey, func
from sqlalchemy.orm import Mapped, mapped_column

from app.database import Base


class ItemClassificationORM(Base):
    __tablename__ = "item_classifications"

    id: Mapped[int] = mapped_column(Integer, primary_key=True, autoincrement=True)
    item_id: Mapped[int] = mapped_column(Integer, ForeignKey("items.id"), unique=True)
    computed_at: Mapped[datetime] = mapped_column(server_default=func.now())
    abc_class: Mapped[str] = mapped_column(Enum("A", "B", "C"), default="C")
    annual_value: Mapped[Decimal] = mapped_column(Numeric(18, 4), default=0)
    xyz_class: Mapped[str] = mapped_column(Enum("X", "Y", "Z"), default="Z")
    adi: Mapped[float | None] = mapped_column(Float, nullable=True)
    cv2: Mapped[float | None] = mapped_column(Float, nullable=True)
    demand_pattern: Mapped[str] = mapped_column(
        Enum("smooth", "intermittent", "erratic", "lumpy"),
        default="smooth",
    )
    history_days: Mapped[int] = mapped_column(SmallInteger, default=0)
    is_reliable: Mapped[bool] = mapped_column(Boolean, default=False)
    created_at: Mapped[datetime | None] = mapped_column(server_default=func.now())
    updated_at: Mapped[datetime | None] = mapped_column(
        server_default=func.now(), onupdate=func.now()
    )
