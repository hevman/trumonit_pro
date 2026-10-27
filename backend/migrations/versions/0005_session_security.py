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
    op.add_column("sessions", sa.Column("id", sa.String(36), nullable=True))
    op.add_column("sessions", sa.Column("created_at", sa.DateTime(timezone=True), nullable=True))
    conn = op.get_bind()
    for token_digest, expires in conn.execute(sa.text(
        "SELECT token_hash, expires_at FROM sessions"
    )).all():
        # Existing sessions have no reliable login timestamp; reconstruct
        # an approximate start based on the historical seven-day lifetime.
        start = expires - timedelta(days=7)
        if start.tzinfo is None:
            start = start.replace(tzinfo=timezone.utc)
        conn.execute(sa.text(
            "UPDATE sessions SET id=:id, created_at=:start WHERE token_hash=:digest"
        ), {"id": str(uuid4()), "start": start, "digest": token_digest})
    op.alter_column("sessions", "id", existing_type=sa.String(36), nullable=False)
    op.alter_column("sessions", "created_at",
                    existing_type=sa.DateTime(timezone=True), nullable=False)
    op.create_index("ix_sessions_id", "sessions", ["id"], unique=True)


def downgrade():
    op.drop_index("ix_sessions_id", table_name="sessions")
    op.drop_column("sessions", "created_at")
    op.drop_column("sessions", "id")
