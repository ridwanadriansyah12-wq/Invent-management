"""schemas/forecast.py — Pydantic request/response untuk endpoint forecast."""
from __future__ import annotations

from datetime import date
from typing import Optional
from pydantic import BaseModel, Field, field_validator


class ForecastBatchRequest(BaseModel):
    """Payload dari Laravel ke FastAPI untuk menjalankan forecast batch."""
    run_id: str = Field(..., description="UUID dari Laravel. Request ulang diabaikan (idempotent).")
    as_of_date: date = Field(..., description="Tanggal referensi pipeline (biasanya: today()).")
    item_ids: list[int] = Field(..., min_length=1, description="List ID item yang akan di-forecast.")

    @field_validator("item_ids")
    @classmethod
    def no_duplicates(cls, v: list[int]) -> list[int]:
        if len(v) != len(set(v)):
            raise ValueError("item_ids tidak boleh mengandung duplikasi.")
        return v


class ForecastItemResult(BaseModel):
    """Hasil forecast untuk satu SKU."""
    item_id: int
    status: str  # SUCCESS | FAILED | SKIPPED
    mu_daily: Optional[float] = None
    sigma_daily: Optional[float] = None
    model_used: Optional[str] = None
    demand_pattern_used: Optional[str] = None
    backtest_mae: Optional[float] = None
    backtest_rmse: Optional[float] = None
    backtest_mase: Optional[float] = None
    baseline_mase: Optional[float] = None
    error_message: Optional[str] = None


class ForecastBatchResponse(BaseModel):
    """Response dari FastAPI ke Laravel."""
    run_id: str
    as_of_date: date
    total_items: int
    succeeded: int
    skipped: int
    failed: int
    results: list[ForecastItemResult]
