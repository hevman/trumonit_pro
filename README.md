# Trumonit.pro — independent tender / RFP response workspace

**Completely new product**, separate from Netiker, TechBool, MZI and the
former Laravel website. Python + Next.js B2B service for source-linked tender
document review and proposal preparation.

**Status: development MVP, NOT deployed to the live domain and NOT ready for
paid or confidential customer workloads.** The old Laravel files have been
removed from the current repository tree. Git history is retained for recovery.
The previous website's database and customer content are NOT reused or migrated.

## Customer workflow

1. Register an account and a new organization. Passwords are hashed with
   scrypt; session tokens are random and hashed server-side. Browser cookies
   are HttpOnly, SameSite Strict, and protected by a private API gateway.
2. Create saved tender projects with a buyer, submission deadline and stage.
3. Upload searchable PDF or DOCX (maximum 8 MiB per file, 25 per project).
4. Inspect source-linked **potential requirements** and deadlines, including
   source page, paragraph or table row. Every finding is an unverified clue.
5. Mark requirements Met / Gap / Needs clarification / N/A, add reviewer notes,
   and track actor/action audit events.
6. Download source documents and export a CSV requirement register with
   spreadsheet formula injection protection.
7. Every project, document, requirement and export is scoped to the signed-in
   company's workspace in database queries.
8. Import the company's own answer base from UTF-8 CSV; store and manually
   approve reusable answer entries, each with provenance and an expiry date.
9. Copy an approved answer into a project draft, review and sign off a final
   working response, then export human-approved items to a DOCX working pack.
10. Owner-only organization audit log for traceable review activity.
11. Owner-issued, single-use editor/viewer invitations; multiple workspaces per account.
12. Requirement tasks: assignee, due date, progress and overdue status.

**No AI is used.** Extraction is deterministic and keyword-based.
It can miss clauses or select irrelevant text. OCR/scanned PDFs, invitations, subscriptions and payments
are NOT implemented yet. All bid answer text is written or imported by the company.

## Technology

- Frontend: Next.js 16, TypeScript, React 19.
- Backend: Python 3.13, FastAPI, Pydantic, SQLAlchemy 2.
- Document parsing: PyMuPDF and python-docx. Python gives us a strong path
  toward local, deterministic document processing and review.
- Database: new PostgreSQL 17, Alembic baseline, no legacy import.
- Local development: isolated Docker Compose stack at 127.0.0.1:3100.
- CI: Pytest/TestClient and Next.js typecheck/build.

## Start with a new database

1. Copy .env.example to .env.
2. Replace POSTGRES_PASSWORD and TRUMONIT_GATEWAY_TOKEN with different random
   40+ character secrets. Example Python command:
       python -c "import secrets; print(secrets.token_urlsafe(40))"
3. Run docker compose up --build in the repository root.
4. Visit http://localhost:3100/login?mode=register and create a workspace.
5. Create a tender, upload your own non-sensitive, searchable PDF or DOCX,
   verify the candidate requirements, add notes, and export a CSV.

The Compose project is named trumonit-new and initializes its **own** volume.
It does not connect to, read, modify or copy historical Laravel data.
PostgreSQL and Python API ports are internal only. The frontend is published
on localhost:3100, not the internet. The sample HTTPS reverse proxy in
deploy/Caddyfile.example is not automatically activated.

## Test

Backend (Python 3.13):
    cd backend
    python -m venv .venv
    . .venv/bin/activate
    pip install -e ".[test]"
    pytest -q

Frontend (Node 22):
    cd frontend
    npm install
    npm run typecheck
    npm run build

GitHub Actions also runs these checks on pushes and pull requests. Verify an
actual successful run before claiming compilation or deployment.

## Security and commercial launch gates

- Verify ownership of email addresses, implement password resets and verified invitation
  delivery. Self-registration is currently suitable for local preview only.
- Add edge IP throttling, malware scanning, hardened DOCX decompression limits,
  encrypted object storage, backups, retention/deletion and GDPR workflows.
- Add PostgreSQL-backed real two-tenant end-to-end tests and deployment checks.
- Improve source references for tables/layout, add OCR and measure precision.
- Introduce bounded document-processing workers and observed queue reliability.
- Validate end-to-end human approval flows, permission boundaries, content
  retention, export completeness and safe import limits.
- Introduce real Stripe payment processing and subscription entitlements.
  The €249/€499/custom prices shown are hypotheses, not active offers.
- Review TLS, production secrets, recovery procedures, logs and monitoring
  before taking confidential files or billing real customers.

## Endpoint overview

Next.js proxies private FastAPI endpoints through same-origin /api/.

- POST /api/auth/register, POST /api/auth/login
- GET /api/auth/me, POST /api/auth/logout
- GET/POST /api/projects, GET/PATCH /api/projects/{id}
- POST /api/projects/{id}/documents (multipart)
- GET /api/projects/{id}/documents/{document_id}
- PATCH /api/projects/{id}/requirements/{requirement_id}
- GET /api/projects/{id}/export (CSV)

This repository has a new Alembic baseline migration for fresh deployments,
not an import mechanism for the old PHP database. Future schema changes need
separate migration revisions; deployed baseline revisions must not be mutated.

## Team workflow (development preview)

Owners can invite editors and viewers using /team. Tokens are random,
stored only as SHA-256 digests, valid for three days and single-use.
The owner sends each link securely; email delivery and verification do
NOT exist yet. A new member signs up at /join; an existing account signs
in there. Members can switch between workspaces via /team.

Editors can prepare bids, upload documents, change reviews, author company
answer drafts and assign requirement tasks. Viewers can only read.
Only owners approve bid answers, manage membership and read organization
audit events. Removed members lose sessions for this company immediately.
Existing projects/data remain tenant-scoped; no prior Laravel data or AI.

Do not invite real customer teams until verified invitations, security
review, backup/retention, SSO and account recovery are implemented.
