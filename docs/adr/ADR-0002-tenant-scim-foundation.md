# ADR-0002: Tenant-scoped SCIM API foundation+

- Status: Accepted (foundation+)
- Date: 2026-02-12
- Scope: provisioning API for enterprise IAM integrations

## Kontekst

Po wdrozeniu OIDC i SAML potrzebowalismy bezpiecznego SCIM:
- autoryzacja per-tenant,
- stabilny kontrakt endpointow SCIM v2,
- pelny lifecycle Users,
- Groups i Bulk do automatyzacji onboardingu.

## Decyzja

Wdrozone foundation+ SCIM obejmuje:
- model `tenant_scim_providers` (token, enablement, policy flags),
- middleware bearer token auth per tenant,
- endpointy `/api/scim/v2/*`:
  - `ServiceProviderConfig`,
  - `ResourceTypes`,
  - `Schemas`,
  - `Users` list/show/create/replace/patch/deprovision,
  - `Groups` list/show/create/replace/patch/delete,
  - `Bulk` (POST, PUT, PATCH, DELETE dla Users/Groups),
- CLI do zarzadzania tokenem:
  - `php artisan wids:scim-token <tenant_id> --enable --rotate`.

## Zasady bezpieczenstwa

- SCIM endpointy sa dostepne tylko z poprawnym bearer tokenem tenantowym.
- Token jest przechowywany tylko jako hash.
- Integracja moze byc globalnie wylaczona przez `WIDS_SCIM_ENABLED=false`.
- Bulk ma limity: `maxOperations` i `maxPayloadSize`.
- Deprovision `disable` odcina dostep (dezaktywacja konta, revokacja tokenow i sesji).

## Konsekwencje

Pozytywne:
- enterprise moze uruchomic provisioning/deprovisioning userow i grup przez SCIM,
- polityki per-tenant (`auto_provision`, `default_role`, `deprovision_mode`, `domain_allowlist`) sa egzekwowane,
- bulk onboarding jest gotowy na produkcyjne integracje IAM,
- mapowanie Groups -> role (`owner`/`member`) jest obslugiwane przez metadata policy.

Ograniczenia:
- brak mapowania Groups -> team/project RBAC na poziomie domenowym,
- brak ETag/version preconditions,
- brak dedykowanego compliance export dla operation logow SCIM.

## Nastepne kroki

1. Doliczyc mapowanie Groups do team/project RBAC.
2. Doliczyc replay protection + compliance export dla logow provisioning.
3. Domknac governance UI dla enterprise identity pack (OIDC + SAML + SCIM).
