"""Add stable, non-credential session IDs and session creation timestamps.

Revision ID: 0005_session_security
Revises: 0004_account_security
"""
from datetime import timedelta, timezone
from uuid import uuid4

from alembic import op
import sqlalchemy as sa

revision = "0005_session_security"
down_revision = "0004_account_security"
branch_labels = None
depends_on = None


def upgrade():
    conn = op.get_bind()
    inspector = sa.inspect(conn)
    columns = {col["name"] for col in inspector.get_columns("sessions")}
    # V1 builds a fresh database using current ORM metadata, while an older
    # deployed V4 lacks these columns. Support both without dropping sessions.
    if "id" not in columns:
        op.add_column("sessions", sa.Column("id", sa.String(36), nullable=True))
    if "created_at" not in columns:
        op.add_column("sessions", sa.Column("created_at", sa.DateTime(timezone=True), nullable=True))

    for digest, expires, session_id, created_at in conn.execute(sa.text(
        "SELECT token_hash, expires_at, id, created_at FROM sessions"
    )).all():
        if session_id is not None and created_at is not None:
            continue
        # Existing logins have only an expiry. Estimate their creation from
        # the previous seven-day maximum session duration.
        estimated = expires - timedelta(days=7)
        if estimated.tzinfo is None:
            estimated = estimated.replace(tzinfo=timezone.utc)
        conn.execute(sa.text(
            "UPDATE sessions SET id=:id, created_at=:start WHERE token_hash=:digest"
        ), {
            "id": session_id or str(uuid4()),
            "start": created_at or estimated,
            "digest": digest,
        })

    if "id" not in columns:
        op.alter_column("sessions", "id", existing_type=sa.String(36), nullable=False)
    if "created_at" not in columns:
        op.alter_column("sessions", "created_at",
                        existing_type=sa.DateTime(timezone=True), nullable=False)
    indexes = {index["name"] for index in sa.inspect(conn).get_indexes("sessions")}
    if "ix_sessions_id" not in indexes:
        op.create_index("ix_sessions_id", "sessions", ["id"], unique=True)


def downgrade():
    # Downgrade is not intended as a production rollback: all session
    # introspection metadata is removed. Prefer forward migrations.
    indexes = {index["name"] for index in sa.inspect(op.get_bind()).get_indexes("sessions")}
    if "ix_sessions_id" in indexes:
        op.drop_index("ix_sessions_id", table_name="sessions")
    op.drop_column("sessions", "created_at")
    op.drop_column("sessions", "id")
