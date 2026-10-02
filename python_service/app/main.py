"""
main.py — FastAPI application entry point.

Endpoints:
  GET  /health              → Health check DB + versi service
  POST /api/v1/forecast/batch       → Forecast batch SKU (Laravel daily scheduler)
  POST /api/v1/classification/batch → Klasifikasi ABC-XYZ (Laravel weekly scheduler)

Keamanan: X-API-Key header di semua endpoint /api/v1/*
"""
import logging
from contextlib import asynccontextmanager

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from app.api import forecast as forecast_router
from app.api import classification as classification_router
from app.database import ping_db

# ── Logging ───────────────────────────────────────────────────────────────────
import os

logging.basicConfig(
    level=os.environ.get("LOG_LEVEL", "INFO"),
    format="%(asctime)s | %(levelname)-8s | %(name)s | %(message)s",
)
logger = logging.getLogger(__name__)


# ── Lifespan ──────────────────────────────────────────────────────────────────
@asynccontextmanager
async def lifespan(app: FastAPI):
    logger.info("🚀 ML Inventory Engine starting...")
    if ping_db():
        logger.info("✅ Database connection OK.")
    else:
        logger.error("❌ Database connection FAILED. Periksa .env DB_* settings.")
    yield
    logger.info("🛑 ML Inventory Engine shutting down.")


# ── Application ────────────────────────────────────────────────────────────────
app = FastAPI(
    title="ML Inventory Engine",
    description=(
        "Python FastAPI microservice untuk pipeline demand, "
        "klasifikasi ABC-XYZ, dan forecasting SS/ROP/MAX. "
        "Digunakan oleh sistem Laravel RoP."
    ),
    version="1.0.0",
    lifespan=lifespan,
    docs_url="/docs",
    redoc_url="/redoc",
)

# CORS: hanya izinkan request dari Laravel (atau localhost saat dev)
app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://localhost", "http://127.0.0.1"],
    allow_credentials=False,
    allow_methods=["GET", "POST"],
    allow_headers=["X-API-Key", "Content-Type"],
)

# ── Routers ────────────────────────────────────────────────────────────────────
app.include_router(forecast_router.router)
app.include_router(classification_router.router)


# ── Health Check ───────────────────────────────────────────────────────────────
@app.get("/health", tags=["Health"])
async def health():
    """
    Health check endpoint. Tidak butuh autentikasi.
    Laravel ForecastApiClient memanggil endpoint ini sebelum batch request.
    """
    db_ok = ping_db()
    return {
        "status": "healthy" if db_ok else "degraded",
        "database": "connected" if db_ok else "disconnected",
        "version": "1.0.0",
    }
