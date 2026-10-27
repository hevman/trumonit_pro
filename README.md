# Trumonit.pro — independent tender / RFP response workspace

**Completely new product**, separate from Netiker, TechBool, MZI and the
former Laravel website. Python + Next.js B2B service for source-linked tender
document review and proposal preparation.

**Status: development MVP, NOT deployed to the live domain and NOT ready for
paid or confidential customer workloads.** The old Laravel files have been
removed from the current repository tree. Git history is retained for recovery.
The previous website's database and customer content are NOT reused or migrated.

## Customer workflow

1. Register and verify a work email before accessing a new organization. Passwords are hashed with
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

- Work-email verification and single-use password recovery are implemented;
  production needs a validated SMTP configuration, deliverability review,
  abuse prevention and invite delivery. Self-registration is still a preview.
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
The owner sends each invite link securely; verification and recovery
emails are sent only when a mail transport is explicitly configured. A new member signs up at /join; an existing account signs
in there. Members can switch between workspaces via /team.

Editors can prepare bids, upload documents, change reviews, author company
answer drafts and assign requirement tasks. Viewers can only read.
Only owners approve bid answers, manage membership and read organization
audit events. Removed members lose sessions for this company immediately.
Existing projects/data remain tenant-scoped; no prior Laravel data or AI.

Do not invite real customer teams until verified invitations, security
review, backup/retention, enterprise SSO and deliverability are implemented.


## Verified email and password recovery (v0004)

New registrations and newly invited accounts do **not** receive a session until
the user confirms ownership of the email address via a one-use link, which
expires after 24 hours. The verification endpoint creates a new session.
Requesting a password reset always returns a generic answer for unknown users.
Reset tokens expire after 30 minutes; a completed reset invalidates **all**
active sessions for the account across organizations. Tokens are hashed in
PostgreSQL and are consumed once. Email sends are limited to three per
email/purpose per hour. Admin-visible audit entries record verification and
password resets without storing any token.

Set the following in .env for **local development only**:

    TRUMONIT_ENV=development
    TRUMONIT_MAIL_MODE=file
    TRUMONIT_PUBLIC_URL=http://localhost:3100

Start Compose, register, then read the verification message from the
container's private \`/home/appuser/outbox\` directory. Do not publish this
directory or its token-bearing messages. Visit the link inside the email.

Production defaults to TRUMONIT_ENV=production and disabled mail. It **refuses**
new registrations when mail is not configured. For SMTP over SSL set
TRUMONIT_MAIL_MODE=smtp_ssl, TRUMONIT_PUBLIC_URL=https://trumonit.pro,
TRUMONIT_SMTP_HOST, TRUMONIT_SMTP_PORT=465, TRUMONIT_SMTP_USER,
TRUMONIT_SMTP_PASSWORD and TRUMONIT_MAIL_FROM (a verified sender).
Production refuses HTTP origins and file outboxes. Configure real DNS/TLS,
SMTP credentials securely and validate delivery before customer onboarding.

Account UI: \`/account\` accepts verification links, sends a resend request and
handles password recovery. The token is placed in the URL fragment (not HTTP
request paths or query strings) and cleared from browser history on load.
Existing development accounts created before this migration are unverified
and must request email verification; the migration does not assume address
ownership. Existing account passwords and organization data are untouched.

**Remaining gates:** independently verified SMTP setup, rate limiting at
edge/IP and anti-enumeration latency, SSO/MFA, managed session policies,
malware scan, data retention/deletion and restore drills, GDPR review,
Stripe/webhooks and production security testing. No AI.


## Customer-controlled project deletion (data lifecycle milestone)

Workspace owners can inspect GET /api/projects/{id}/deletion-preview to see
document, requirement, answer and task counts, including stored document bytes.
POST /api/projects/{id}/delete with JSON {"confirm_project_name":"EXACT NAME"}
permanently deletes that project's documents, requirement evidence, response
drafts and assignments in one database transaction. The shared organization
answer library, other projects and all audit events remain untouched. Editors,
viewers and other organizations cannot invoke these endpoints. Deletion is
irreversible: operators must have working database backups before enabling
customer use. PostgreSQL physical storage, replicas and backups are subject to
separate retention/erasure controls; this endpoint alone does not guarantee
GDPR-complete erasure. No automatic deletion policy or retention schedule is
configured, and no AI processes customer data.
