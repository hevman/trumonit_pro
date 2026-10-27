from __future__ import annotations

import os
from collections.abc import Generator
from datetime import datetime, timezone
from uuid import uuid4

from sqlalchemy import create_engine
from sqlalchemy.engine import URL
from sqlalchemy.orm import DeclarativeBase, Session, sessionmaker


def new_id() -> str:
    return str(uuid4())


def now() -> datetime:
    return datetime.now(timezone.utc)


class Base(DeclarativeBase):
    pass


def database_url() -> str:
    value = os.environ.get("DATABASE_URL", "")
    if value:
        return value
    password = os.environ.get("TRUMONIT_DB_PASSWORD", "")
    if not password:
        raise RuntimeError("DATABASE_URL or TRUMONIT_DB_PASSWORD is required")
    return URL.create(
        drivername="postgresql+psycopg", username="trumonit",
        password=password, host="db", port=5432, database="trumonit",
    ).render_as_string(hide_password=False)


_engine = None
_session_factory = None


def get_db() -> Generator[Session, None, None]:
    global _engine, _session_factory
    if _session_factory is None:
        _engine = create_engine(database_url(), pool_pre_ping=True)
        _session_factory = sessionmaker(bind=_engine, expire_on_commit=False)
    with _session_factory() as db:
        yield db
