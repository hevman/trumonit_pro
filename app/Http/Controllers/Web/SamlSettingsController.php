<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantSsoProvider;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SamlSettingsController extends Controller
{
    public function edit(Request $request)
    {
        $tenant = $request->user()?->tenant;
        abort_if(! $tenant, 400, 'User has no tenant.');

        $provider = TenantSsoProvider::query()->firstOrNew(
            [
                'tenant_id' => (int) $tenant->id,
                'provider' => 'saml',
            ],
            [
                'is_enabled' => false,
                'email_claim' => 'email',
                'name_claim' => 'name',
                'subject_claim' => 'NameID',
                'auto_provision' => false,
                'auto_provision_role' => 'member',
                'enforce_verified_email' => false,
                'metadata' => [
                    'nameid_format' => (string) config('wids.sso.saml.nameid_format_default', 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress'),
                    'enforce_signed_assertion' => (bool) config('wids.sso.saml.require_signed_assertion_default', true),
                    'enforce_signed_logout_request' => (bool) config('wids.sso.saml.require_signed_logout_request_default', true),
                    'enforce_signed_logout_response' => (bool) config('wids.sso.saml.require_signed_logout_response_default', false),
                    'foundation_mode' => false,
                ],
            ]
        );

        $metadata = is_array($provider->metadata) ? $provider->metadata : [];
        $domainAllowlist = is_array($provider->domain_allowlist) ? array_values($provider->domain_allowlist) : [];

        return view('app.sso.saml', [
            'provider' => $provider,
            'domainAllowlistText' => implode(PHP_EOL, $domainAllowlist),
            'samlGlobalEnabled' => (bool) config('wids.sso.saml.enabled', false),
            'metadataUrl' => route('sso.saml.metadata', ['tenantSlug' => (string) $tenant->slug]),
            'acsUrl' => route('sso.saml.acs', ['tenantSlug' => (string) $tenant->slug]),
            'loginStartUrl' => route('sso.saml.redirect', ['tenant' => (string) $tenant->slug]),
            'nameIdFormat' => is_string($metadata['nameid_format'] ?? null)
                ? (string) $metadata['nameid_format']
                : (string) config('wids.sso.saml.nameid_format_default', 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress'),
            'enforceSignedAssertion' => filter_var(
                $metadata['enforce_signed_assertion'] ?? config('wids.sso.saml.require_signed_assertion_default', true),
                FILTER_VALIDATE_BOOL
            ),
            'enforceSignedLogoutRequest' => filter_var(
                $metadata['enforce_signed_logout_request'] ?? config('wids.sso.saml.require_signed_logout_request_default', true),
                FILTER_VALIDATE_BOOL
            ),
            'enforceSignedLogoutResponse' => filter_var(
                $metadata['enforce_signed_logout_response'] ?? config('wids.sso.saml.require_signed_logout_response_default', false),
                FILTER_VALIDATE_BOOL
            ),
            'spPrivateKeySet' => is_string($provider->private_key) && trim($provider->private_key) !== '',
        ]);
    }

    public function update(Request $request)
    {
        $tenant = $request->user()?->tenant;
        abort_if(! $tenant, 400, 'User has no tenant.');

        $data = $request->validate([
            'saml_enabled' => ['nullable', 'boolean'],
            'idp_entity_id' => ['nullable', 'string', 'max:2048'],
            'sso_url' => ['nullable', 'url', 'max:2048'],
            'slo_url' => ['nullable', 'url', 'max:2048'],
            'metadata_url' => ['nullable', 'url', 'max:2048'],
            'x509_certificate' => ['nullable', 'string', 'max:20000'],
            'sp_private_key' => ['nullable', 'string', 'max:30000'],
            'sp_entity_id' => ['nullable', 'string', 'max:2048'],
            'email_attribute' => ['nullable', 'string', 'max:128'],
            'name_attribute' => ['nullable', 'string', 'max:128'],
            'subject_attribute' => ['nullable', 'string', 'max:128'],
            'nameid_format' => ['nullable', 'string', 'max:255'],
            'domain_allowlist' => ['nullable', 'string', 'max:5000'],
            'auto_provision' => ['nullable', 'boolean'],
            'auto_provision_role' => ['nullable', 'in:member,owner'],
            'enforce_signed_assertion' => ['nullable', 'boolean'],
            'enforce_signed_logout_request' => ['nullable', 'boolean'],
            'enforce_signed_logout_response' => ['nullable', 'boolean'],
        ]);

        $provider = TenantSsoProvider::query()->firstOrNew([
            'tenant_id' => (int) $tenant->id,
            'provider' => 'saml',
        ]);

        $before = $this->auditPayload($provider);
        $samlEnabled = (bool) ($data['saml_enabled'] ?? false);
        $idpEntityId = $this->normalizeOptionalString($data['idp_entity_id'] ?? null);
        $ssoUrl = $this->normalizeOptionalString($data['sso_url'] ?? null);
        $sloUrl = $this->normalizeOptionalString($data['slo_url'] ?? null);
        $metadataUrl = $this->normalizeOptionalString($data['metadata_url'] ?? null);
        $x509Certificate = $this->normalizeOptionalString($data['x509_certificate'] ?? null);
        $spPrivateKey = $this->normalizeOptionalString($data['sp_private_key'] ?? null);
        $spEntityId = $this->normalizeOptionalString($data['sp_entity_id'] ?? null);
        $emailAttribute = $this->normalizeOptionalString($data['email_attribute'] ?? null);
        $nameAttribute = $this->normalizeOptionalString($data['name_attribute'] ?? null);
        $subjectAttribute = $this->normalizeOptionalString($data['subject_attribute'] ?? null);
        $nameIdFormat = $this->normalizeOptionalString($data['nameid_format'] ?? null);
        $domainAllowlist = $this->parseDomainAllowlist((string) ($data['domain_allowlist'] ?? ''));
        $autoProvision = (bool) ($data['auto_provision'] ?? false);
        $autoProvisionRole = $autoProvision ? (string) ($data['auto_provision_role'] ?? 'member') : 'member';
        $enforceSignedAssertion = (bool) ($data['enforce_signed_assertion'] ?? false);
        $enforceSignedLogoutRequest = (bool) ($data['enforce_signed_logout_request'] ?? true);
        $enforceSignedLogoutResponse = (bool) ($data['enforce_signed_logout_response'] ?? false);

        $provider->is_enabled = $samlEnabled;
        $provider->issuer_url = $idpEntityId !== '' ? $idpEntityId : null;
        $provider->authorization_endpoint = $ssoUrl !== '' ? $ssoUrl : null;
        $provider->token_endpoint = $sloUrl !== '' ? $sloUrl : null;
        $provider->jwks_uri = $metadataUrl !== '' ? $metadataUrl : null;
        $provider->client_id = $spEntityId !== '' ? $spEntityId : null;
        $provider->email_claim = $emailAttribute !== '' ? $emailAttribute : 'email';
        $provider->name_claim = $nameAttribute !== '' ? $nameAttribute : 'name';
        $provider->subject_claim = $subjectAttribute !== '' ? $subjectAttribute : 'NameID';
        $provider->domain_allowlist = $domainAllowlist;
        $provider->auto_provision = $autoProvision;
        $provider->auto_provision_role = $autoProvisionRole === 'owner' ? 'owner' : 'member';
        $provider->enforce_verified_email = false;
        $provider->last_error = null;

        $metadata = is_array($provider->metadata) ? $provider->metadata : [];
        $metadata['nameid_format'] = $nameIdFormat !== ''
            ? $nameIdFormat
            : (string) config('wids.sso.saml.nameid_format_default', 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress');
        $metadata['enforce_signed_assertion'] = $enforceSignedAssertion;
        $metadata['enforce_signed_logout_request'] = $enforceSignedLogoutRequest;
        $metadata['enforce_signed_logout_response'] = $enforceSignedLogoutResponse;
        $metadata['foundation_mode'] = false;
        $provider->metadata = $metadata;

        if ($x509Certificate !== '') {
            $provider->client_secret = $x509Certificate;
        }

        if ($spPrivateKey !== '') {
            $provider->private_key = $spPrivateKey;
        }

        if ($samlEnabled) {
            $this->validateRequiredForEnabledProvider($provider);
        }

        $provider->save();

        app(AuditLogService::class)->log(
            tenantId: (int) $tenant->id,
            actorUserId: $request->user()?->id,
            action: 'tenant.sso.saml_updated',
            auditableType: Tenant::class,
            auditableId: (int) $tenant->id,
            data: [
                'before' => $before,
                'after' => $this->auditPayload($provider),
            ],
            request: $request,
        );

        return redirect()
            ->route('sso.saml.edit')
            ->with('status', 'SAML settings updated.');
    }

    private function validateRequiredForEnabledProvider(TenantSsoProvider $provider): void
    {
        $missing = [];
        $metadata = is_array($provider->metadata) ? $provider->metadata : [];
        $enforceSigned = filter_var(
            $metadata['enforce_signed_assertion'] ?? config('wids.sso.saml.require_signed_assertion_default', true),
            FILTER_VALIDATE_BOOL
        );

        if (! is_string($provider->issuer_url) || trim($provider->issuer_url) === '') {
            $missing[] = 'idp_entity_id';
        }

        if (! is_string($provider->authorization_endpoint) || trim((string) $provider->authorization_endpoint) === '') {
            $missing[] = 'sso_url';
        }

        if ($enforceSigned && (! is_string($provider->client_secret) || trim((string) $provider->client_secret) === '')) {
            $missing[] = 'x509_certificate';
        }

        if ($missing !== []) {
            $messages = [];

            foreach ($missing as $field) {
                $messages[$field] = ['This field is required when SAML SSO is enabled.'];
            }

            throw ValidationException::withMessages($messages);
        }
    }

    /**
     * @return list<string>
     */
    private function parseDomainAllowlist(string $input): array
    {
        $domains = [];
        $parts = preg_split('/[\r\n,;]+/', $input) ?: [];

        foreach ($parts as $part) {
            $domain = strtolower(trim((string) $part));
            $domain = ltrim($domain, '@');

            if ($domain === '') {
                continue;
            }

            if (preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) !== 1) {
                throw ValidationException::withMessages([
                    'domain_allowlist' => ['Invalid domain: '.$domain],
                ]);
            }

            $domains[$domain] = true;
        }

        return array_values(array_keys($domains));
    }

    /**
     * @return array{
     *   is_enabled: bool,
     *   idp_entity_id: ?string,
     *   sso_url: ?string,
     *   slo_url: ?string,
     *   metadata_url: ?string,
     *   x509_certificate_set: bool,
     *   sp_private_key_set: bool,
     *   email_attribute: ?string,
     *   name_attribute: ?string,
     *   subject_attribute: ?string,
     *   domain_allowlist: list<string>,
     *   auto_provision: bool,
     *   auto_provision_role: string,
     *   metadata: array<string, mixed>,
     *   last_error: ?string
     * }
     */
    private function auditPayload(TenantSsoProvider $provider): array
    {
        return [
            'is_enabled' => (bool) $provider->is_enabled,
            'idp_entity_id' => $provider->issuer_url,
            'sso_url' => $provider->authorization_endpoint,
            'slo_url' => $provider->token_endpoint,
            'metadata_url' => $provider->jwks_uri,
            'x509_certificate_set' => is_string($provider->client_secret) && trim($provider->client_secret) !== '',
            'sp_private_key_set' => is_string($provider->private_key) && trim($provider->private_key) !== '',
            'email_attribute' => $provider->email_claim,
            'name_attribute' => $provider->name_claim,
            'subject_attribute' => $provider->subject_claim,
            'domain_allowlist' => is_array($provider->domain_allowlist) ? array_values($provider->domain_allowlist) : [],
            'auto_provision' => (bool) $provider->auto_provision,
            'auto_provision_role' => is_string($provider->auto_provision_role) ? $provider->auto_provision_role : 'member',
            'metadata' => is_array($provider->metadata) ? $provider->metadata : [],
            'last_error' => is_string($provider->last_error) ? $provider->last_error : null,
        ];
    }

    private function normalizeOptionalString(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return trim($value);
    }
}
