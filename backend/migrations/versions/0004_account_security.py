"""Verified email and recoverable accounts (new data only; legacy unverified).

Revision ID: 0004_account_security
Revises: 0003_team_tasks
"""
from alembic import op
import sqlalchemy as sa

revision = "0004_account_security"
down_revision = "0003_team_tasks"
branch_labels = None
depends_on = None


def upgrade():
    op.add_column("users", sa.Column("email_verified_at", sa.DateTime(timezone=True), nullable=True))
    op.create_table("account_action_tokens",
        sa.Column("id", sa.String(36), primary_key=True),
        sa.Column("user_id", sa.String(36), sa.ForeignKey("users.id"), nullable=False),
        sa.Column("purpose", sa.String(16), nullable=False),
        sa.Column("digest", sa.String(64), nullable=False),
        sa.Column("expires_at", sa.DateTime(timezone=True), nullable=False),
        sa.Column("consumed_at", sa.DateTime(timezone=True), nullable=True),
        sa.Column("created_at", sa.DateTime(timezone=True), nullable=False),
    )
    op.create_index("ix_account_action_tokens_user_id", "account_action_tokens", ["user_id"])
    op.create_index("ix_account_action_tokens_digest", "account_action_tokens", ["digest"], unique=True)
    op.create_table("account_mail_throttles",
        sa.Column("identity_hash", sa.String(64), primary_key=True),
        sa.Column("purpose", sa.String(16), primary_key=True),
        sa.Column("sent_count", sa.Integer(), nullable=False),
        sa.Column("window_started_at", sa.DateTime(timezone=True), nullable=False),
    )


def downgrade():
    op.drop_table("account_mail_throttles")
    op.drop_index("ix_account_action_tokens_digest", table_name="account_action_tokens")
    op.drop_index("ix_account_action_tokens_user_id", table_name="account_action_tokens")
    op.drop_table("account_action_tokens")
    op.drop_column("users", "email_verified_at")
