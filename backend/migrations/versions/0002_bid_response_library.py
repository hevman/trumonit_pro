"""Company answer library and human-approved requirement responses.

Revision ID: 0002_bid_response_library
Revises: 0001_initial

Adds tables only; no existing customer or historical data imported.
"""
from alembic import op
import sqlalchemy as sa

revision = "0002_bid_response_library"
down_revision = "0001_initial"
branch_labels = None
depends_on = None


def upgrade():
    op.create_table("answer_library",
        sa.Column("id", sa.String(36), primary_key=True),
        sa.Column("workspace_id", sa.String(36),
                  sa.ForeignKey("workspaces.id"), nullable=False),
        sa.Column("title", sa.String(150), nullable=False),
        sa.Column("category", sa.String(40), nullable=False),
        sa.Column("body", sa.Text(), nullable=False),
        sa.Column("evidence_reference", sa.String(400), nullable=False),
        sa.Column("valid_until", sa.String(10), nullable=True),
        sa.Column("status", sa.String(20), nullable=False),
        sa.Column("approved_by", sa.String(36), sa.ForeignKey("users.id"), nullable=True),
        sa.Column("approved_at", sa.DateTime(timezone=True), nullable=True),
        sa.Column("created_at", sa.DateTime(timezone=True), nullable=False),
        sa.Column("updated_at", sa.DateTime(timezone=True), nullable=False),
    )
    op.create_index("ix_answer_library_workspace_id", "answer_library", ["workspace_id"])
    op.create_table("requirement_responses",
        sa.Column("id", sa.String(36), primary_key=True),
        sa.Column("workspace_id", sa.String(36),
                  sa.ForeignKey("workspaces.id"), nullable=False),
        sa.Column("project_id", sa.String(36),
                  sa.ForeignKey("projects.id"), nullable=False),
        sa.Column("requirement_id", sa.String(36),
                  sa.ForeignKey("requirements.id"), nullable=False, unique=True),
        sa.Column("library_entry_id", sa.String(36),
                  sa.ForeignKey("answer_library.id"), nullable=True),
        sa.Column("body", sa.Text(), nullable=False),
        sa.Column("status", sa.String(20), nullable=False),
        sa.Column("approved_by", sa.String(36), sa.ForeignKey("users.id"), nullable=True),
        sa.Column("approved_at", sa.DateTime(timezone=True), nullable=True),
        sa.Column("updated_at", sa.DateTime(timezone=True), nullable=False),
    )
    op.create_index("ix_requirement_responses_workspace_id",
                    "requirement_responses", ["workspace_id"])
    op.create_index("ix_requirement_responses_project_id",
                    "requirement_responses", ["project_id"])
    op.create_index("ix_requirement_responses_requirement_id",
                    "requirement_responses", ["requirement_id"], unique=True)


def downgrade():
    op.drop_index("ix_requirement_responses_requirement_id",
                  table_name="requirement_responses")
    op.drop_index("ix_requirement_responses_project_id",
                  table_name="requirement_responses")
    op.drop_index("ix_requirement_responses_workspace_id",
                  table_name="requirement_responses")
    op.drop_table("requirement_responses")
    op.drop_index("ix_answer_library_workspace_id", table_name="answer_library")
    op.drop_table("answer_library")
