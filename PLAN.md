# Trumonit — high-value B2B product roadmap

Core promise: reduce time spent reading procurement packs, coordinating
responses and checking evidence. This is not TechBool financial forecasting,
MZI general data analytics, or Netiker website monitoring.

## Implemented in independent Python rewrite

- FastAPI + Next.js monorepo
- New, isolated PostgreSQL schema and Compose configuration
- Tenant-scoped accounts, saved tenders and document retrieval
- PDF / DOCX parsing with source-linked review candidates
- Human review status and notes, audit trail, CSV
- Automated API test suite and frontend CI checks

## Before enabling customer billing

1. Run Python + Next.js CI and real PostgreSQL two-tenant security tests.
2. Verified account emails, account recovery, members/invitations, rate limits.
3. Document scanning, encrypted storage, signed URLs, data retention/deletion.
4. Safe OCR and background workers, retry policies and cost controls.
5. Grounded document intelligence / RAG with evidence and manual approval.
6. Response drafting, approval workflows and procurement-specific templates.
7. Stripe billing with actual plan limits, cancel/refund/dunning policy.
8. Secure deployment with SSL, monitoring, backups and restore drills.
9. Validate €249–€499 willingness to pay with real procurement teams.

Do not imply a fully automated tender compliance judgment or guarantee wins.
