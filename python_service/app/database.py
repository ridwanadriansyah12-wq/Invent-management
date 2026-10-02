"""
database.py — SQLAlchemy engine dan session factory.

Hak akses Python DB user (dibuat manual oleh admin):
  GRANT SELECT ON {db}.stock_movements   TO 'rop_python'@'localhost';
  GRANT SELECT ON {db}.items             TO 'rop_python'@'localhost';
  GRANT SELECT ON {db}.categories        TO 'rop_python'@'localhost';
  GRANT SELECT ON {db}.category_defaults TO 'rop_python'@'localhost';
  GRANT INSERT, UPDATE ON {db}.daily_demand        TO 'rop_python'@'localhost';
  GRANT INSERT, UPDATE ON {db}.item_classifications TO 'rop_python'@'localhost';
  GRANT INSERT, UPDATE ON {db}.forecast_runs       TO 'rop_python'@'localhost';
"""
from contextlib import contextmanager

from sqlalchemy import create_engine, text
from sqlalchemy.orm import DeclarativeBase, sessionmaker

from app.config import get_settings


def _build_engine():
    cfg = get_settings()
    return create_engine(
        cfg.database_url,
        pool_pre_ping=True,
        pool_recycle=3600,
        echo=False,
    )


engine = _build_engine()
SessionLocal = sessionmaker(bind=engine, autoflush=False, autocommit=False)


class Base(DeclarativeBase):
    pass


@contextmanager
def get_db():
    """Context manager untuk sesi DB. Otomatis rollback saat exception."""
    session = SessionLocal()
    try:
        yield session
        session.commit()
    except Exception:
        session.rollback()
        raise
    finally:
        session.close()


def ping_db() -> bool:
    """Health check koneksi DB."""
    try:
        with engine.connect() as conn:
            conn.execute(text("SELECT 1"))
        return True
    except Exception:
        return False
