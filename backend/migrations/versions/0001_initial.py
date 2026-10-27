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


def upgrade():
    # Baseline-only migration. Later releases must use explicit op.* migrations,
    # not mutate this baseline after deployment.
    Base.metadata.create_all(bind=op.get_bind())


def downgrade():
    Base.metadata.drop_all(bind=op.get_bind())
