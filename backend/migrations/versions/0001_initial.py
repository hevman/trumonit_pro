"""Create Trumonit-only schema in a NEW database.

No legacy Laravel data or schema are read or migrated.
Revision ID: 0001_initial
Revises:
"""
from alembic import op
from trumonit.db import Base
from trumonit import models  # noqa: F401

revision = "0001_initial"
down_revision = None
branch_labels = None
depends_on = None


# Freeze the original schema. New models belong to later revisions.
V1_TABLES = (
    "users", "workspaces", "memberships", "sessions", "login_attempts",
    "projects", "documents", "requirements", "audit_events",
)


def upgrade():
    # Baseline-only migration. Later releases must use explicit op.* migrations,
    # not mutate this baseline after deployment.
    tables = [Base.metadata.tables[name] for name in V1_TABLES]
    Base.metadata.create_all(bind=op.get_bind(), tables=tables)


def downgrade():
    tables = [Base.metadata.tables[name] for name in V1_TABLES]
    Base.metadata.drop_all(bind=op.get_bind(), tables=tables)
