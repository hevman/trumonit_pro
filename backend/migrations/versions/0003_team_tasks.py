"""Team memberships and requirement accountability; no legacy data touched.

Revision ID: 0003_team_tasks
Revises: 0002_bid_response_library
"""
from alembic import op
import sqlalchemy as sa

revision = "0003_team_tasks"
down_revision = "0002_bid_response_library"
branch_labels = None
depends_on = None


def upgrade():
    op.create_table("team_invitations",
        sa.Column("id", sa.String(36), primary_key=True),
        sa.Column("workspace_id", sa.String(36), sa.ForeignKey("workspaces.id"), nullable=False),
        sa.Column("email", sa.String(254), nullable=False),
        sa.Column("role", sa.String(20), nullable=False),
        sa.Column("token_hash", sa.String(64), nullable=False, unique=True),
        sa.Column("invited_by", sa.String(36), sa.ForeignKey("users.id"), nullable=False),
        sa.Column("expires_at", sa.DateTime(timezone=True), nullable=False),
        sa.Column("accepted_at", sa.DateTime(timezone=True), nullable=True),
        sa.Column("revoked_at", sa.DateTime(timezone=True), nullable=True),
        sa.Column("created_at", sa.DateTime(timezone=True), nullable=False),
    )
    op.create_index("ix_team_invitations_workspace_id", "team_invitations", ["workspace_id"])
    op.create_table("requirement_tasks",
        sa.Column("id", sa.String(36), primary_key=True),
        sa.Column("workspace_id", sa.String(36), sa.ForeignKey("workspaces.id"), nullable=False),
        sa.Column("project_id", sa.String(36), sa.ForeignKey("projects.id"), nullable=False),
        sa.Column("requirement_id", sa.String(36), sa.ForeignKey("requirements.id"), nullable=False, unique=True),
        sa.Column("assignee_id", sa.String(36), sa.ForeignKey("users.id"), nullable=True),
        sa.Column("due_date", sa.String(10), nullable=True),
        sa.Column("status", sa.String(20), nullable=False),
        sa.Column("note", sa.Text(), nullable=False),
        sa.Column("updated_by", sa.String(36), sa.ForeignKey("users.id"), nullable=False),
        sa.Column("updated_at", sa.DateTime(timezone=True), nullable=False),
    )
    op.create_index("ix_requirement_tasks_workspace_id", "requirement_tasks", ["workspace_id"])
    op.create_index("ix_requirement_tasks_project_id", "requirement_tasks", ["project_id"])


def downgrade():
    op.drop_index("ix_requirement_tasks_project_id", table_name="requirement_tasks")
    op.drop_index("ix_requirement_tasks_workspace_id", table_name="requirement_tasks")
    op.drop_table("requirement_tasks")
    op.drop_index("ix_team_invitations_workspace_id", table_name="team_invitations")
    op.drop_table("team_invitations")
