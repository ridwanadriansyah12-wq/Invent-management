"""schemas/classification.py — Pydantic request/response untuk endpoint classification."""
from __future__ import annotations

from typing import Optional
from pydantic import BaseModel, Field, field_validator


class ClassificationBatchRequest(BaseModel):
    """Payload dari Laravel untuk menjalankan klasifikasi ABC-XYZ per batch SKU."""
    item_ids: list[int] = Field(..., min_length=1)

    @field_validator("item_ids")
    @classmethod
    def no_duplicates(cls, v: list[int]) -> list[int]:
        if len(v) != len(set(v)):
            raise ValueError("item_ids tidak boleh mengandung duplikasi.")
        return v


class ClassificationItemResult(BaseModel):
    """Hasil klasifikasi untuk satu SKU."""
    item_id: int
    abc_class: str
    annual_value: float
    xyz_class: str
    demand_pattern: str
    adi: Optional[float] = None
    cv2: Optional[float] = None
    history_days: int
    is_reliable: bool


class ClassificationBatchResponse(BaseModel):
    total_items: int
    classified: int
    results: list[ClassificationItemResult]
