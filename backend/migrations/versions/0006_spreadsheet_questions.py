"""Editable RFP spreadsheet question-to-answer mappings.

Revision ID: 0006_spreadsheet_questions
Revises: 0005_session_security
"""
from alembic import op
import sqlalchemy as sa

revision = "0006_spreadsheet_questions"
down_revision = "0005_session_security"
branch_labels = None
depends_on = None

def upgrade():
    op.create_table(
        "spreadsheet_questions",
        sa.Column("id", sa.String(36), primary_key=True),
        sa.Column("workspace_id", sa.String(36), sa.ForeignKey("workspaces.id"), nullable=False),
        sa.Column("project_id", sa.String(36), sa.ForeignKey("projects.id"), nullable=False),
        sa.Column("document_id", sa.String(36), sa.ForeignKey("documents.id"), nullable=False),
        sa.Column("requirement_id", sa.String(36), sa.ForeignKey("requirements.id"), nullable=False),
        sa.Column("sheet_name", sa.String(31), nullable=False),
        sa.Column("row_number", sa.Integer(), nullable=False),
        sa.Column("question_column", sa.String(3), nullable=False),
        sa.Column("answer_column", sa.String(3), nullable=False),
        sa.UniqueConstraint("document_id", "sheet_name", "row_number", "answer_column"),
    )
    for key in ("workspace_id", "project_id", "document_id", "requirement_id"):
        op.create_index(f"ix_spreadsheet_questions_{key}", "spreadsheet_questions", [key], unique=key=="requirement_id")

def downgrade():
    for key in ("requirement_id", "document_id", "project_id", "workspace_id"):
        op.drop_index(f"ix_spreadsheet_questions_{key}", table_name="spreadsheet_questions")
    op.drop_table("spreadsheet_questions")
