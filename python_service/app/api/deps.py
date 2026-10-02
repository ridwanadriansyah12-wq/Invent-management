"""
api/deps.py — Dependency injection untuk FastAPI.

Autentikasi: API Key via header X-API-Key.
Harus sama dengan nilai FASTAPI_API_KEY di Laravel .env.
"""
from fastapi import HTTPException, Security, status
from fastapi.security import APIKeyHeader

from app.config import get_settings

_api_key_header = APIKeyHeader(name="X-API-Key", auto_error=True)


async def verify_api_key(api_key: str = Security(_api_key_header)) -> str:
    """Verifikasi API Key. Raise 403 jika salah."""
    cfg = get_settings()
    if not cfg.api_key:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="API_KEY tidak dikonfigurasi di server.",
        )
    if api_key != cfg.api_key:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="API Key tidak valid.",
        )
    return api_key
