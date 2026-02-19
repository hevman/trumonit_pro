# ADR-0001: Tenant-scoped SSO OIDC foundation

- Status: Accepted (foundation)
- Date: 2026-02-12
- Scope: `wIDS` panel authentication (web)

## Kontekst

Potrzebujemy fundamentu SSO dla klientów enterprise bez blokowania istniejącego logowania email+hasło.
Kluczowe wymagania na ten etap:
- konfiguracja OIDC per tenant,
- bezpieczny start flow autoryzacji,
- mapowanie tożsamości użytkownika do workspace,
- brak twardego sprzężenia z konkretnym IdP.

## Decyzja

Wdrażamy tenant-scoped OIDC w modelu:
- tabela `tenant_sso_providers` (provider `oidc`, konfiguracja, claim mapping, allowlista domen, auto-provision),
- tabela `user_identities` (powiązanie `provider_subject` <-> użytkownik),
- owner UI: `/sso` do konfiguracji i zarządzania OIDC,
- login flow:
  - start: `GET /auth/oidc/redirect?tenant=<slug>`,
  - callback: `GET /auth/oidc/{tenantSlug}/callback`,
- discovery OIDC z `/.well-known/openid-configuration`,
- PKCE (`S256`) + `state`,
- logowanie audytowe `auth.sso.oidc_login`.

## Zasady bezpieczeństwa (foundation)

- OIDC wymaga HTTPS.
- URL issuera i endpointy przechodzą przez politykę URL safety (anti-SSRF).
- Opcjonalnie wymuszamy zgodność hosta endpointów z hostem issuera.
- Możliwe wymuszenie `email_verified`.
- Możliwa allowlista domen email per tenant.

## Konsekwencje

Pozytywne:
- szybki start enterprise SSO bez przebudowy całego auth stacku,
- gotowa baza pod kolejne etapy (SAML, SCIM, Just-In-Time provisioning policy).

Koszty/ograniczenia:
- to nie jest jeszcze pełne enterprise IAM,
- brak SAML i SCIM w tym etapie,
- brak polityk step-up/MFA enforcement po stronie aplikacji (delegowane do IdP),
- endpointy override wymagają ostrożnej konfiguracji przez ownera.

## Co dalej (kolejne ADR)

1. SAML 2.0 compatibility layer.
2. SCIM 2.0 provisioning/deprovisioning.
3. Global/org-level SSO policy + session governance.
4. Hardening: signed JWT validation path + klucze JWKS rotation policy.
