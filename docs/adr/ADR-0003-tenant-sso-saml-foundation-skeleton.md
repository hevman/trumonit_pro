# ADR-0003: Tenant-scoped SSO SAML foundation+

- Status: Accepted (foundation+, evolved from skeleton)
- Date: 2026-02-12
- Scope: enterprise auth extension for SAML-compatible IdPs

## Kontekst

Po wdrozeniu OIDC i SCIM potrzebowalismy wejscia pod klientow enterprise, ktorzy wymagaja SAML.
Pierwszy etap dostarczyl skeleton endpointow i konfiguracji tenantowej.
Nastepny etap domknal walidacje assertion oraz logowanie JIT.
Kolejny etap domknal SAML SLO:
- SP-initiated (logout request + callback validation),
- IdP-initiated (LogoutRequest + LogoutResponse).

## Decyzja

Wdraza sie foundation+ SAML:
- konfiguracja SAML per tenant (`tenant_sso_providers` z `provider=saml`),
- owner UI do zarzadzania parametrami IdP/SP i mapowaniem claimow,
- endpoint metadata SP,
- endpoint redirect do IdP z AuthnRequest,
- endpoint ACS callback z pelna walidacja assertion,
- obsluga `EncryptedAssertion` z decryption key per tenant,
- mapowanie atrybutow (`subject/email/name`) i login uzytkownika,
- JIT provisioning zgodny z polityka tenant (`auto_provision`, `domain_allowlist`),
- SP-initiated SLO:
  - `POST /logout` inicjuje `LogoutRequest` do IdP,
  - callback `POST/GET /auth/saml/{tenantSlug}/slo` waliduje `LogoutResponse` i zamyka sesje lokalna.
- IdP-initiated SLO:
  - callback `POST/GET /auth/saml/{tenantSlug}/slo` przyjmuje `LogoutRequest`,
  - waliduje issuer/destination/czas i podpis (XML lub Redirect signature),
  - odsyla `LogoutResponse` do IdP `slo_url`.

## Zasady bezpieczenstwa

- global feature flag `WIDS_SSO_SAML_ENABLED`,
- tenant-scoped enablement (`is_enabled`),
- throttle dla endpointow SAML auth (`saml-auth`),
- CSRF exception dla callbackow IdP (`auth/saml/*/acs`, `auth/saml/*/slo`),
- walidacje callback:
  - `RelayState` + `InResponseTo`,
  - status `Success`,
  - issuer,
  - destination/recipient,
  - czas (`NotBefore`, `NotOnOrAfter`, configurable clock skew),
  - audience restriction,
  - XML signature verification (Response/Assertion) po certyfikacie IdP.

## Konsekwencje

Pozytywne:
- SAML login jest gotowy do produkcyjnego onboardingu tenantow enterprise.
- Integracja jest audytowalna (identity link + claims snapshot + audit event).
- Mamy spojnosc modelu tozsamosci OIDC/SAML (`user_identities` + tenant policies).
- Logout miedzy SP i IdP jest przewidywalny (SLO dla sesji SAML z fallbackiem local logout).

Ograniczenia:
- brak federacji multi-IdP na tenant i brak org-level governance policy pack,
- brak back-channel SLO.

## Nastepne kroki

1. Domknac polityki sesji org-level (wymuszanie SSO/TTL/scope per role).
2. Domknac SCIM governance org-level.
3. Ujednolicic enterprise identity runbook (OIDC + SAML + SCIM).
