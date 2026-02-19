<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantSsoProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RobRichards\XMLSecLibs\XMLSecEnc;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use Tests\TestCase;

class SamlSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_saml_redirect_builds_authn_request(): void
    {
        config()->set('wids.sso.saml.enabled', true);

        [$privateKeyPem, $certificatePem] = $this->generateCertificatePair();

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $this->seedSamlProvider($tenant, $certificatePem, false);

        $response = $this->get(route('sso.saml.redirect', [
            'tenant' => $tenant->slug,
        ]));

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('https://idp.example.com/sso?', $location);

        $query = [];
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertNotSame('', (string) ($query['SAMLRequest'] ?? ''));
        $this->assertNotSame('', (string) ($query['RelayState'] ?? ''));

        $decoded = base64_decode((string) $query['SAMLRequest'], true);
        $this->assertIsString($decoded);
        $xml = gzinflate($decoded);
        $this->assertIsString($xml);
        $this->assertStringContainsString('<samlp:AuthnRequest', $xml);
        $this->assertStringContainsString('AssertionConsumerServiceURL="'.route('sso.saml.acs', ['tenantSlug' => $tenant->slug]).'"', $xml);
        $this->assertStringContainsString('<saml:Issuer>https://sp.example.com/'.$tenant->slug.'</saml:Issuer>', $xml);
    }

    public function test_saml_login_links_identity_to_existing_user(): void
    {
        config()->set('wids.sso.saml.enabled', true);

        [$privateKeyPem, $certificatePem] = $this->generateCertificatePair();

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'member',
            'email' => 'john@acme.com',
            'is_active' => true,
        ]);

        $provider = $this->seedSamlProvider($tenant, $certificatePem, false);
        $flow = $this->startSamlAndExtractFlow($tenant->slug);

        $samlResponse = $this->buildSignedSamlResponse(
            privateKeyPem: $privateKeyPem,
            certificatePem: $certificatePem,
            issuer: (string) $provider->issuer_url,
            audience: (string) $provider->client_id,
            recipient: route('sso.saml.acs', ['tenantSlug' => $tenant->slug]),
            inResponseTo: $flow['request_id'],
            nameId: 'saml-subject-john',
            email: 'john@acme.com',
            displayName: 'John Acme',
        );

        $response = $this->post(route('sso.saml.acs', [
            'tenantSlug' => $tenant->slug,
        ]), [
            'RelayState' => $flow['relay_state'],
            'SAMLResponse' => $samlResponse,
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user->fresh());

        $this->assertDatabaseHas('user_identities', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'provider' => 'saml',
            'provider_subject' => 'saml-subject-john',
            'email' => 'john@acme.com',
        ]);
    }

    public function test_saml_login_redirect_is_blocked_when_provider_is_disallowed_by_policy(): void
    {
        config()->set('wids.sso.saml.enabled', true);

        [$privateKeyPem, $certificatePem] = $this->generateCertificatePair();
        $this->assertNotSame('', $privateKeyPem);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'identity_governance' => [
                'enforce_sso_for' => 'off',
                'allow_password_login' => true,
                'allowed_sso_providers' => ['oidc'],
                'session_max_minutes' => 0,
            ],
        ]);

        $this->seedSamlProvider($tenant, $certificatePem, false);

        $response = $this->from(route('login'))->get(route('sso.saml.redirect', [
            'tenant' => $tenant->slug,
        ]));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
    }

    public function test_saml_login_auto_provisions_user_when_enabled(): void
    {
        config()->set('wids.sso.saml.enabled', true);

        [$privateKeyPem, $certificatePem] = $this->generateCertificatePair();

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $provider = $this->seedSamlProvider($tenant, $certificatePem, true);
        $flow = $this->startSamlAndExtractFlow($tenant->slug);

        $samlResponse = $this->buildSignedSamlResponse(
            privateKeyPem: $privateKeyPem,
            certificatePem: $certificatePem,
            issuer: (string) $provider->issuer_url,
            audience: (string) $provider->client_id,
            recipient: route('sso.saml.acs', ['tenantSlug' => $tenant->slug]),
            inResponseTo: $flow['request_id'],
            nameId: 'new-user-subject',
            email: 'new.user@acme.com',
            displayName: 'New User',
        );

        $response = $this->post(route('sso.saml.acs', [
            'tenantSlug' => $tenant->slug,
        ]), [
            'RelayState' => $flow['relay_state'],
            'SAMLResponse' => $samlResponse,
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();

        $user = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('email', 'new.user@acme.com')
            ->firstOrFail();

        $this->assertSame('member', $user->role);
        $this->assertTrue((bool) $user->is_active);

        $this->assertDatabaseHas('user_identities', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'provider' => 'saml',
            'provider_subject' => 'new-user-subject',
            'email' => 'new.user@acme.com',
        ]);
    }

    public function test_saml_login_rejects_invalid_signature(): void
    {
        config()->set('wids.sso.saml.enabled', true);

        [$privateKeyPem, $certificatePem] = $this->generateCertificatePair();

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $provider = $this->seedSamlProvider($tenant, $certificatePem, true);
        $flow = $this->startSamlAndExtractFlow($tenant->slug);

        $samlResponse = $this->buildSignedSamlResponse(
            privateKeyPem: $privateKeyPem,
            certificatePem: $certificatePem,
            issuer: (string) $provider->issuer_url,
            audience: (string) $provider->client_id,
            recipient: route('sso.saml.acs', ['tenantSlug' => $tenant->slug]),
            inResponseTo: $flow['request_id'],
            nameId: 'tampered-subject',
            email: 'tampered@acme.com',
            displayName: 'Tampered User',
        );

        $decoded = base64_decode($samlResponse, true);
        $this->assertIsString($decoded);
        $tampered = str_replace('Tampered User', 'Tampered User X', $decoded);
        $this->assertIsString($tampered);

        $response = $this->post(route('sso.saml.acs', [
            'tenantSlug' => $tenant->slug,
        ]), [
            'RelayState' => $flow['relay_state'],
            'SAMLResponse' => base64_encode($tampered),
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_saml_login_rejects_audience_mismatch(): void
    {
        config()->set('wids.sso.saml.enabled', true);

        [$privateKeyPem, $certificatePem] = $this->generateCertificatePair();

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $provider = $this->seedSamlProvider($tenant, $certificatePem, true);
        $flow = $this->startSamlAndExtractFlow($tenant->slug);

        $samlResponse = $this->buildSignedSamlResponse(
            privateKeyPem: $privateKeyPem,
            certificatePem: $certificatePem,
            issuer: (string) $provider->issuer_url,
            audience: 'https://wrong-sp.example.com',
            recipient: route('sso.saml.acs', ['tenantSlug' => $tenant->slug]),
            inResponseTo: $flow['request_id'],
            nameId: 'new-user-subject',
            email: 'new.user@acme.com',
            displayName: 'New User',
        );

        $response = $this->post(route('sso.saml.acs', [
            'tenantSlug' => $tenant->slug,
        ]), [
            'RelayState' => $flow['relay_state'],
            'SAMLResponse' => $samlResponse,
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_saml_login_rejects_unsigned_assertion_when_enforced(): void
    {
        config()->set('wids.sso.saml.enabled', true);

        [$privateKeyPem, $certificatePem] = $this->generateCertificatePair();

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $provider = $this->seedSamlProvider($tenant, $certificatePem, true);
        $flow = $this->startSamlAndExtractFlow($tenant->slug);

        $samlResponse = $this->buildSignedSamlResponse(
            privateKeyPem: $privateKeyPem,
            certificatePem: $certificatePem,
            issuer: (string) $provider->issuer_url,
            audience: (string) $provider->client_id,
            recipient: route('sso.saml.acs', ['tenantSlug' => $tenant->slug]),
            inResponseTo: $flow['request_id'],
            nameId: 'unsigned-user',
            email: 'unsigned@acme.com',
            displayName: 'Unsigned User',
            signAssertion: false,
        );

        $response = $this->post(route('sso.saml.acs', [
            'tenantSlug' => $tenant->slug,
        ]), [
            'RelayState' => $flow['relay_state'],
            'SAMLResponse' => $samlResponse,
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_saml_login_maps_custom_subject_attribute_to_identity(): void
    {
        config()->set('wids.sso.saml.enabled', true);

        [$privateKeyPem, $certificatePem] = $this->generateCertificatePair();

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $provider = $this->seedSamlProvider($tenant, $certificatePem, true);
        $provider->subject_claim = 'employeeId';
        $provider->name_claim = 'cn';
        $provider->save();

        $flow = $this->startSamlAndExtractFlow($tenant->slug);

        $samlResponse = $this->buildSignedSamlResponse(
            privateKeyPem: $privateKeyPem,
            certificatePem: $certificatePem,
            issuer: (string) $provider->issuer_url,
            audience: (string) $provider->client_id,
            recipient: route('sso.saml.acs', ['tenantSlug' => $tenant->slug]),
            inResponseTo: $flow['request_id'],
            nameId: 'fallback-nameid',
            email: 'custom.subject@acme.com',
            displayName: 'Ignored display name',
            attributes: [
                'employeeId' => 'EMP-12345',
                'cn' => 'Custom Subject User',
            ],
        );

        $response = $this->post(route('sso.saml.acs', [
            'tenantSlug' => $tenant->slug,
        ]), [
            'RelayState' => $flow['relay_state'],
            'SAMLResponse' => $samlResponse,
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();

        $user = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('email', 'custom.subject@acme.com')
            ->firstOrFail();

        $this->assertSame('Custom Subject User', (string) $user->name);

        $this->assertDatabaseHas('user_identities', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'provider' => 'saml',
            'provider_subject' => 'EMP-12345',
            'email' => 'custom.subject@acme.com',
            'name' => 'Custom Subject User',
        ]);
    }

    public function test_saml_login_accepts_encrypted_assertion_when_sp_key_is_configured(): void
    {
        config()->set('wids.sso.saml.enabled', true);

        [$idpPrivateKeyPem, $idpCertificatePem] = $this->generateCertificatePair();
        [$spPrivateKeyPem, $spCertificatePem] = $this->generateCertificatePair();

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $provider = $this->seedSamlProvider($tenant, $idpCertificatePem, true);
        $provider->private_key = $spPrivateKeyPem;
        $provider->save();

        $flow = $this->startSamlAndExtractFlow($tenant->slug);

        $samlResponse = $this->buildSignedSamlResponse(
            privateKeyPem: $idpPrivateKeyPem,
            certificatePem: $idpCertificatePem,
            issuer: (string) $provider->issuer_url,
            audience: (string) $provider->client_id,
            recipient: route('sso.saml.acs', ['tenantSlug' => $tenant->slug]),
            inResponseTo: $flow['request_id'],
            nameId: 'encrypted-subject',
            email: 'encrypted.user@acme.com',
            displayName: 'Encrypted User',
        );
        $encryptedSamlResponse = $this->encryptAssertionInSamlResponse($samlResponse, $spCertificatePem);

        $response = $this->post(route('sso.saml.acs', [
            'tenantSlug' => $tenant->slug,
        ]), [
            'RelayState' => $flow['relay_state'],
            'SAMLResponse' => $encryptedSamlResponse,
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();

        $user = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('email', 'encrypted.user@acme.com')
            ->firstOrFail();

        $this->assertDatabaseHas('user_identities', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'provider' => 'saml',
            'provider_subject' => 'encrypted-subject',
            'email' => 'encrypted.user@acme.com',
        ]);
    }

    public function test_saml_login_rejects_encrypted_assertion_without_sp_private_key(): void
    {
        config()->set('wids.sso.saml.enabled', true);

        [$idpPrivateKeyPem, $idpCertificatePem] = $this->generateCertificatePair();
        [, $spCertificatePem] = $this->generateCertificatePair();

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $provider = $this->seedSamlProvider($tenant, $idpCertificatePem, true);
        $provider->private_key = null;
        $provider->save();

        $flow = $this->startSamlAndExtractFlow($tenant->slug);

        $samlResponse = $this->buildSignedSamlResponse(
            privateKeyPem: $idpPrivateKeyPem,
            certificatePem: $idpCertificatePem,
            issuer: (string) $provider->issuer_url,
            audience: (string) $provider->client_id,
            recipient: route('sso.saml.acs', ['tenantSlug' => $tenant->slug]),
            inResponseTo: $flow['request_id'],
            nameId: 'encrypted-subject-missing-key',
            email: 'encrypted.missing.key@acme.com',
            displayName: 'Encrypted Missing Key',
        );
        $encryptedSamlResponse = $this->encryptAssertionInSamlResponse($samlResponse, $spCertificatePem);

        $response = $this->post(route('sso.saml.acs', [
            'tenantSlug' => $tenant->slug,
        ]), [
            'RelayState' => $flow['relay_state'],
            'SAMLResponse' => $encryptedSamlResponse,
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_saml_login_accepts_encrypted_assertion_signed_at_response_level(): void
    {
        config()->set('wids.sso.saml.enabled', true);

        [$idpPrivateKeyPem, $idpCertificatePem] = $this->generateCertificatePair();
        [$spPrivateKeyPem, $spCertificatePem] = $this->generateCertificatePair();

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $provider = $this->seedSamlProvider($tenant, $idpCertificatePem, true);
        $provider->private_key = $spPrivateKeyPem;
        $provider->save();

        $flow = $this->startSamlAndExtractFlow($tenant->slug);

        $unsignedAssertionResponse = $this->buildSignedSamlResponse(
            privateKeyPem: $idpPrivateKeyPem,
            certificatePem: $idpCertificatePem,
            issuer: (string) $provider->issuer_url,
            audience: (string) $provider->client_id,
            recipient: route('sso.saml.acs', ['tenantSlug' => $tenant->slug]),
            inResponseTo: $flow['request_id'],
            nameId: 'response-signed-encrypted-subject',
            email: 'response.signed.encrypted@acme.com',
            displayName: 'Response Signed Encrypted User',
            signAssertion: false,
        );
        $encryptedSamlResponse = $this->encryptAssertionInSamlResponse($unsignedAssertionResponse, $spCertificatePem);
        $responseSignedEncryptedSamlResponse = $this->signSamlResponseEnvelope(
            samlResponse: $encryptedSamlResponse,
            privateKeyPem: $idpPrivateKeyPem,
            certificatePem: $idpCertificatePem,
        );

        $response = $this->post(route('sso.saml.acs', [
            'tenantSlug' => $tenant->slug,
        ]), [
            'RelayState' => $flow['relay_state'],
            'SAMLResponse' => $responseSignedEncryptedSamlResponse,
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();
    }

    public function test_saml_logout_redirect_builds_logout_request(): void
    {
        config()->set('wids.sso.saml.enabled', true);

        [$privateKeyPem, $certificatePem] = $this->generateCertificatePair();

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'member',
            'email' => 'john@acme.com',
            'is_active' => true,
        ]);

        $this->seedSamlProvider($tenant, $certificatePem, false);

        $response = $this->actingAs($user)
            ->withSession([
                'sso_auth' => [
                    'provider' => 'saml',
                    'tenant_id' => (int) $tenant->id,
                    'tenant_slug' => (string) $tenant->slug,
                    'provider_subject' => 'saml-subject-john',
                    'name_id' => 'john@acme.com',
                    'session_index' => 'session-idx-123',
                ],
            ])
            ->post('/logout');

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('https://idp.example.com/slo?', $location);

        $query = [];
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertNotSame('', (string) ($query['SAMLRequest'] ?? ''));
        $this->assertNotSame('', (string) ($query['RelayState'] ?? ''));

        $decoded = base64_decode((string) $query['SAMLRequest'], true);
        $this->assertIsString($decoded);
        $xml = gzinflate($decoded);
        $this->assertIsString($xml);
        $this->assertStringContainsString('<samlp:LogoutRequest', $xml);
        $this->assertStringContainsString('<saml:NameID Format="urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress">john@acme.com</saml:NameID>', $xml);
        $this->assertStringContainsString('<samlp:SessionIndex>session-idx-123</samlp:SessionIndex>', $xml);
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_saml_logout_callback_completes_local_logout(): void
    {
        config()->set('wids.sso.saml.enabled', true);

        [$privateKeyPem, $certificatePem] = $this->generateCertificatePair();

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'member',
            'email' => 'john@acme.com',
            'is_active' => true,
        ]);

        $provider = $this->seedSamlProvider($tenant, $certificatePem, false);
        $flow = $this->startSamlLogoutAndExtractFlow(
            user: $user,
            tenant: $tenant,
            nameId: 'john@acme.com',
            sessionIndex: 'session-idx-123',
        );

        $samlResponse = $this->buildSignedSamlLogoutResponse(
            privateKeyPem: $privateKeyPem,
            certificatePem: $certificatePem,
            issuer: (string) $provider->issuer_url,
            destination: route('sso.saml.slo', ['tenantSlug' => $tenant->slug]),
            inResponseTo: $flow['request_id'],
        );

        $response = $this->post(route('sso.saml.slo', [
            'tenantSlug' => $tenant->slug,
        ]), [
            'RelayState' => $flow['relay_state'],
            'SAMLResponse' => $samlResponse,
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status', 'You are signed out.');
        $this->assertGuest();
    }

    public function test_saml_logout_callback_rejects_unsigned_response_when_enforced(): void
    {
        config()->set('wids.sso.saml.enabled', true);

        [$privateKeyPem, $certificatePem] = $this->generateCertificatePair();

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'member',
            'email' => 'john@acme.com',
            'is_active' => true,
        ]);

        $provider = $this->seedSamlProvider($tenant, $certificatePem, false);
        $metadata = is_array($provider->metadata) ? $provider->metadata : [];
        $metadata['enforce_signed_logout_response'] = true;
        $provider->metadata = $metadata;
        $provider->save();

        $flow = $this->startSamlLogoutAndExtractFlow(
            user: $user,
            tenant: $tenant,
            nameId: 'john@acme.com',
            sessionIndex: 'session-idx-123',
        );

        $samlResponse = $this->buildSignedSamlLogoutResponse(
            privateKeyPem: $privateKeyPem,
            certificatePem: $certificatePem,
            issuer: (string) $provider->issuer_url,
            destination: route('sso.saml.slo', ['tenantSlug' => $tenant->slug]),
            inResponseTo: $flow['request_id'],
            signResponse: false,
        );

        $response = $this->post(route('sso.saml.slo', [
            'tenantSlug' => $tenant->slug,
        ]), [
            'RelayState' => $flow['relay_state'],
            'SAMLResponse' => $samlResponse,
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_saml_logout_falls_back_to_local_logout_when_slo_url_missing(): void
    {
        config()->set('wids.sso.saml.enabled', true);

        [$privateKeyPem, $certificatePem] = $this->generateCertificatePair();

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'member',
            'email' => 'john@acme.com',
            'is_active' => true,
        ]);

        $provider = $this->seedSamlProvider($tenant, $certificatePem, false);
        $provider->token_endpoint = null;
        $provider->save();

        $response = $this->actingAs($user)
            ->withSession([
                'sso_auth' => [
                    'provider' => 'saml',
                    'tenant_id' => (int) $tenant->id,
                    'tenant_slug' => (string) $tenant->slug,
                    'provider_subject' => 'saml-subject-john',
                    'name_id' => 'john@acme.com',
                ],
            ])
            ->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_idp_initiated_saml_logout_request_logs_out_session_and_redirects_to_idp(): void
    {
        config()->set('wids.sso.saml.enabled', true);

        [$privateKeyPem, $certificatePem] = $this->generateCertificatePair();

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'member',
            'email' => 'john@acme.com',
            'is_active' => true,
        ]);

        $provider = $this->seedSamlProvider($tenant, $certificatePem, false);
        $requestPayload = $this->buildSamlLogoutRequestPayload(
            privateKeyPem: $privateKeyPem,
            certificatePem: $certificatePem,
            issuer: (string) $provider->issuer_url,
            destination: route('sso.saml.slo', ['tenantSlug' => $tenant->slug]),
            nameId: 'john@acme.com',
            sessionIndex: 'session-idx-123',
            signRequest: true,
        );

        $response = $this->actingAs($user)
            ->withSession([
                'sso_auth' => [
                    'provider' => 'saml',
                    'tenant_id' => (int) $tenant->id,
                    'tenant_slug' => (string) $tenant->slug,
                    'provider_subject' => 'john@acme.com',
                    'name_id' => 'john@acme.com',
                    'session_index' => 'session-idx-123',
                ],
            ])
            ->post(route('sso.saml.slo', [
                'tenantSlug' => $tenant->slug,
            ]), [
                'SAMLRequest' => $requestPayload['encoded'],
                'RelayState' => 'relay-idp-logout',
            ]);

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('https://idp.example.com/slo?', $location);

        $query = [];
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertNotSame('', (string) ($query['SAMLResponse'] ?? ''));
        $this->assertSame('relay-idp-logout', (string) ($query['RelayState'] ?? ''));

        $decoded = base64_decode((string) ($query['SAMLResponse'] ?? ''), true);
        $this->assertIsString($decoded);
        $xml = gzinflate($decoded);
        $this->assertIsString($xml);
        $this->assertStringContainsString('<samlp:LogoutResponse', $xml);
        $this->assertStringContainsString('InResponseTo="'.$this->xmlEscape($requestPayload['request_id']).'"', $xml);
        $this->assertGuest();
    }

    public function test_idp_initiated_saml_logout_request_rejects_unsigned_when_enforced(): void
    {
        config()->set('wids.sso.saml.enabled', true);

        [$privateKeyPem, $certificatePem] = $this->generateCertificatePair();

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'member',
            'email' => 'john@acme.com',
            'is_active' => true,
        ]);

        $provider = $this->seedSamlProvider($tenant, $certificatePem, false);
        $metadata = is_array($provider->metadata) ? $provider->metadata : [];
        $metadata['enforce_signed_logout_request'] = true;
        $provider->metadata = $metadata;
        $provider->save();

        $requestPayload = $this->buildSamlLogoutRequestPayload(
            privateKeyPem: $privateKeyPem,
            certificatePem: $certificatePem,
            issuer: (string) $provider->issuer_url,
            destination: route('sso.saml.slo', ['tenantSlug' => $tenant->slug]),
            nameId: 'john@acme.com',
            sessionIndex: 'session-idx-123',
            signRequest: false,
        );

        $response = $this->actingAs($user)
            ->withSession([
                'sso_auth' => [
                    'provider' => 'saml',
                    'tenant_id' => (int) $tenant->id,
                    'tenant_slug' => (string) $tenant->slug,
                    'provider_subject' => 'john@acme.com',
                    'name_id' => 'john@acme.com',
                    'session_index' => 'session-idx-123',
                ],
            ])
            ->post(route('sso.saml.slo', [
                'tenantSlug' => $tenant->slug,
            ]), [
                'SAMLRequest' => $requestPayload['encoded'],
                'RelayState' => 'relay-idp-logout',
            ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_saml_metadata_endpoint_returns_xml(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $response = $this->get(route('sso.saml.metadata', [
            'tenantSlug' => $tenant->slug,
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/samlmetadata+xml; charset=UTF-8');
        $response->assertSee('EntityDescriptor', false);
        $response->assertSee(route('sso.saml.acs', ['tenantSlug' => $tenant->slug]), false);
        $response->assertSee(route('sso.saml.slo', ['tenantSlug' => $tenant->slug]), false);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function generateCertificatePair(): array
    {
        $privateKey = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);

        $this->assertNotFalse($privateKey);

        $csr = openssl_csr_new([
            'commonName' => 'idp.example.com',
        ], $privateKey, [
            'digest_alg' => 'sha256',
        ]);

        $this->assertNotFalse($csr);

        $x509 = openssl_csr_sign($csr, null, $privateKey, 365, [
            'digest_alg' => 'sha256',
        ]);

        $this->assertNotFalse($x509);

        $privateKeyPem = '';
        $certificatePem = '';
        openssl_pkey_export($privateKey, $privateKeyPem);
        openssl_x509_export($x509, $certificatePem);

        return [$privateKeyPem, $certificatePem];
    }

    /**
     * @return array{relay_state: string, request_id: string}
     */
    private function startSamlAndExtractFlow(string $tenantSlug): array
    {
        $response = $this->get(route('sso.saml.redirect', [
            'tenant' => $tenantSlug,
        ]));

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $query = [];
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $relayState = (string) ($query['RelayState'] ?? '');
        $encodedRequest = (string) ($query['SAMLRequest'] ?? '');
        $this->assertNotSame('', $relayState);
        $this->assertNotSame('', $encodedRequest);

        $decoded = base64_decode($encodedRequest, true);
        $this->assertIsString($decoded);
        $inflated = gzinflate($decoded);
        $this->assertIsString($inflated);

        $document = new \DOMDocument();
        $loaded = $document->loadXML($inflated);
        $this->assertTrue($loaded);

        $requestId = (string) $document->documentElement?->getAttribute('ID');
        $this->assertNotSame('', $requestId);

        return [
            'relay_state' => $relayState,
            'request_id' => $requestId,
        ];
    }

    /**
     * @return array{relay_state: string, request_id: string}
     */
    private function startSamlLogoutAndExtractFlow(User $user, Tenant $tenant, string $nameId, ?string $sessionIndex = null): array
    {
        $response = $this->actingAs($user)
            ->withSession([
                'sso_auth' => [
                    'provider' => 'saml',
                    'tenant_id' => (int) $tenant->id,
                    'tenant_slug' => (string) $tenant->slug,
                    'provider_subject' => 'subject-for-logout',
                    'name_id' => $nameId,
                    'session_index' => $sessionIndex ?? '',
                ],
            ])
            ->post('/logout');

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $query = [];
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $relayState = (string) ($query['RelayState'] ?? '');
        $encodedRequest = (string) ($query['SAMLRequest'] ?? '');
        $this->assertNotSame('', $relayState);
        $this->assertNotSame('', $encodedRequest);

        $decoded = base64_decode($encodedRequest, true);
        $this->assertIsString($decoded);
        $inflated = gzinflate($decoded);
        $this->assertIsString($inflated);

        $document = new \DOMDocument();
        $loaded = $document->loadXML($inflated);
        $this->assertTrue($loaded);

        $requestId = (string) $document->documentElement?->getAttribute('ID');
        $this->assertNotSame('', $requestId);

        return [
            'relay_state' => $relayState,
            'request_id' => $requestId,
        ];
    }

    private function buildSignedSamlResponse(
        string $privateKeyPem,
        string $certificatePem,
        string $issuer,
        string $audience,
        string $recipient,
        string $inResponseTo,
        string $nameId,
        string $email,
        string $displayName,
        array $attributes = [],
        ?string $notBefore = null,
        ?string $notOnOrAfter = null,
        bool $signAssertion = true,
    ): string {
        $responseId = '_'.strtolower((string) Str::uuid());
        $assertionId = '_'.strtolower((string) Str::uuid());
        $issueInstant = now()->utc()->format('Y-m-d\TH:i:s\Z');
        $notBefore = $notBefore ?? now()->subMinute()->utc()->format('Y-m-d\TH:i:s\Z');
        $notOnOrAfter = $notOnOrAfter ?? now()->addMinutes(5)->utc()->format('Y-m-d\TH:i:s\Z');
        $attributes = array_merge(
            [
                'mail' => $email,
                'displayName' => $displayName,
            ],
            $attributes
        );

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" '
            .'ID="'.$this->xmlEscape($responseId).'" Version="2.0" IssueInstant="'.$this->xmlEscape($issueInstant).'" '
            .'Destination="'.$this->xmlEscape($recipient).'" InResponseTo="'.$this->xmlEscape($inResponseTo).'">'
            .'<saml:Issuer>'.$this->xmlEscape($issuer).'</saml:Issuer>'
            .'<samlp:Status><samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Success"/></samlp:Status>'
            .'<saml:Assertion ID="'.$this->xmlEscape($assertionId).'" Version="2.0" IssueInstant="'.$this->xmlEscape($issueInstant).'">'
            .'<saml:Issuer>'.$this->xmlEscape($issuer).'</saml:Issuer>'
            .'<saml:Subject>'
            .'<saml:NameID Format="urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified">'.$this->xmlEscape($nameId).'</saml:NameID>'
            .'<saml:SubjectConfirmation Method="urn:oasis:names:tc:SAML:2.0:cm:bearer">'
            .'<saml:SubjectConfirmationData InResponseTo="'.$this->xmlEscape($inResponseTo).'" Recipient="'.$this->xmlEscape($recipient).'" NotOnOrAfter="'.$this->xmlEscape($notOnOrAfter).'" />'
            .'</saml:SubjectConfirmation>'
            .'</saml:Subject>'
            .'<saml:Conditions NotBefore="'.$this->xmlEscape($notBefore).'" NotOnOrAfter="'.$this->xmlEscape($notOnOrAfter).'">'
            .'<saml:AudienceRestriction><saml:Audience>'.$this->xmlEscape($audience).'</saml:Audience></saml:AudienceRestriction>'
            .'</saml:Conditions>'
            .'<saml:AttributeStatement>'
            .$this->renderSamlAttributes($attributes)
            .'</saml:AttributeStatement>'
            .'</saml:Assertion>'
            .'</samlp:Response>';

        $document = new \DOMDocument();
        $loaded = $document->loadXML($xml);
        $this->assertTrue($loaded);

        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('samlp', 'urn:oasis:names:tc:SAML:2.0:protocol');
        $xpath->registerNamespace('saml', 'urn:oasis:names:tc:SAML:2.0:assertion');
        $assertion = $xpath->query('/samlp:Response/saml:Assertion');
        if ($assertion === false || ! $assertion->item(0) instanceof \DOMElement) {
            $this->fail('Unable to locate SAML assertion for signing.');
        }

        $assertionElement = $assertion->item(0);
        $signature = new XMLSecurityDSig();
        $signature->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
        $signature->addReference(
            $assertionElement,
            XMLSecurityDSig::SHA256,
            [
                'http://www.w3.org/2000/09/xmldsig#enveloped-signature',
                XMLSecurityDSig::EXC_C14N,
            ],
            [
                'id_name' => 'ID',
                'overwrite' => false,
            ]
        );

        if ($signAssertion) {
            $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
            $key->loadKey($privateKeyPem, false);
            $signature->sign($key, $assertionElement);
            $signature->add509Cert($certificatePem, true, false, ['subjectName' => true]);
        }

        return base64_encode((string) $document->saveXML());
    }

    private function encryptAssertionInSamlResponse(string $samlResponse, string $spCertificatePem): string
    {
        $decoded = base64_decode($samlResponse, true);
        $this->assertIsString($decoded);

        $document = new \DOMDocument();
        $loaded = $document->loadXML($decoded);
        $this->assertTrue($loaded);

        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('samlp', 'urn:oasis:names:tc:SAML:2.0:protocol');
        $xpath->registerNamespace('saml', 'urn:oasis:names:tc:SAML:2.0:assertion');
        $assertionNodes = $xpath->query('/samlp:Response/saml:Assertion');
        if ($assertionNodes === false || ! $assertionNodes->item(0) instanceof \DOMElement) {
            $this->fail('Unable to locate SAML assertion for encryption.');
        }

        $assertionElement = $assertionNodes->item(0);
        $dataKey = new XMLSecurityKey(XMLSecurityKey::AES256_CBC);
        $dataKey->generateSessionKey();

        $encryption = new XMLSecEnc();
        $encryption->setNode($assertionElement);
        $encryption->type = XMLSecEnc::Element;
        $encryptedDataElement = $encryption->encryptNode($dataKey, false);
        $this->assertInstanceOf(\DOMElement::class, $encryptedDataElement);

        $keyEncryptionKey = new XMLSecurityKey(XMLSecurityKey::RSA_OAEP_MGF1P, ['type' => 'public']);
        $keyEncryptionKey->loadKey($spCertificatePem, false, true);
        $encryption->encryptKey($keyEncryptionKey, $dataKey);

        $encryptedAssertionElement = $document->createElementNS(
            'urn:oasis:names:tc:SAML:2.0:assertion',
            'saml:EncryptedAssertion',
        );
        $encryptedAssertionElement->appendChild($document->importNode($encryptedDataElement, true));

        $parentNode = $assertionElement->parentNode;
        if (! $parentNode instanceof \DOMNode) {
            $this->fail('Unable to attach encrypted SAML assertion.');
        }

        $parentNode->replaceChild($encryptedAssertionElement, $assertionElement);

        return base64_encode((string) $document->saveXML());
    }

    private function signSamlResponseEnvelope(string $samlResponse, string $privateKeyPem, string $certificatePem): string
    {
        $decoded = base64_decode($samlResponse, true);
        $this->assertIsString($decoded);

        $document = new \DOMDocument();
        $loaded = $document->loadXML($decoded);
        $this->assertTrue($loaded);

        $responseElement = $document->documentElement;
        $this->assertInstanceOf(\DOMElement::class, $responseElement);

        $signature = new XMLSecurityDSig();
        $signature->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
        $signature->addReference(
            $responseElement,
            XMLSecurityDSig::SHA256,
            [
                'http://www.w3.org/2000/09/xmldsig#enveloped-signature',
                XMLSecurityDSig::EXC_C14N,
            ],
            [
                'id_name' => 'ID',
                'overwrite' => false,
            ]
        );

        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $key->loadKey($privateKeyPem, false);
        $signature->sign($key, $responseElement);
        $signature->add509Cert($certificatePem, true, false, ['subjectName' => true]);

        return base64_encode((string) $document->saveXML());
    }

    /**
     * @return array{request_id: string, encoded: string}
     */
    private function buildSamlLogoutRequestPayload(
        string $privateKeyPem,
        string $certificatePem,
        string $issuer,
        string $destination,
        string $nameId,
        ?string $sessionIndex = null,
        bool $signRequest = true,
    ): array {
        $requestId = '_'.strtolower((string) Str::uuid());
        $issueInstant = now()->utc()->format('Y-m-d\TH:i:s\Z');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<samlp:LogoutRequest xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" '
            .'ID="'.$this->xmlEscape($requestId).'" Version="2.0" IssueInstant="'.$this->xmlEscape($issueInstant).'" '
            .'Destination="'.$this->xmlEscape($destination).'">'
            .'<saml:Issuer>'.$this->xmlEscape($issuer).'</saml:Issuer>'
            .'<saml:NameID Format="urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress">'.$this->xmlEscape($nameId).'</saml:NameID>'
            .($sessionIndex !== null && trim($sessionIndex) !== ''
                ? '<samlp:SessionIndex>'.$this->xmlEscape($sessionIndex).'</samlp:SessionIndex>'
                : '')
            .'</samlp:LogoutRequest>';

        $document = new \DOMDocument();
        $loaded = $document->loadXML($xml);
        $this->assertTrue($loaded);

        if ($signRequest) {
            $requestElement = $document->documentElement;
            $this->assertInstanceOf(\DOMElement::class, $requestElement);

            $signature = new XMLSecurityDSig();
            $signature->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
            $signature->addReference(
                $requestElement,
                XMLSecurityDSig::SHA256,
                [
                    'http://www.w3.org/2000/09/xmldsig#enveloped-signature',
                    XMLSecurityDSig::EXC_C14N,
                ],
                [
                    'id_name' => 'ID',
                    'overwrite' => false,
                ]
            );

            $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
            $key->loadKey($privateKeyPem, false);
            $signature->sign($key, $requestElement);
            $signature->add509Cert($certificatePem, true, false, ['subjectName' => true]);
        }

        return [
            'request_id' => $requestId,
            'encoded' => base64_encode((string) $document->saveXML()),
        ];
    }

    private function buildSignedSamlLogoutResponse(
        string $privateKeyPem,
        string $certificatePem,
        string $issuer,
        string $destination,
        string $inResponseTo,
        bool $signResponse = true,
    ): string {
        $responseId = '_'.strtolower((string) Str::uuid());
        $issueInstant = now()->utc()->format('Y-m-d\TH:i:s\Z');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<samlp:LogoutResponse xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" '
            .'ID="'.$this->xmlEscape($responseId).'" Version="2.0" IssueInstant="'.$this->xmlEscape($issueInstant).'" '
            .'Destination="'.$this->xmlEscape($destination).'" InResponseTo="'.$this->xmlEscape($inResponseTo).'">'
            .'<saml:Issuer>'.$this->xmlEscape($issuer).'</saml:Issuer>'
            .'<samlp:Status><samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Success"/></samlp:Status>'
            .'</samlp:LogoutResponse>';

        $document = new \DOMDocument();
        $loaded = $document->loadXML($xml);
        $this->assertTrue($loaded);

        if ($signResponse) {
            $responseElement = $document->documentElement;
            $this->assertInstanceOf(\DOMElement::class, $responseElement);

            $signature = new XMLSecurityDSig();
            $signature->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
            $signature->addReference(
                $responseElement,
                XMLSecurityDSig::SHA256,
                [
                    'http://www.w3.org/2000/09/xmldsig#enveloped-signature',
                    XMLSecurityDSig::EXC_C14N,
                ],
                [
                    'id_name' => 'ID',
                    'overwrite' => false,
                ]
            );

            $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
            $key->loadKey($privateKeyPem, false);
            $signature->sign($key, $responseElement);
            $signature->add509Cert($certificatePem, true, false, ['subjectName' => true]);
        }

        return base64_encode((string) $document->saveXML());
    }

    /**
     * @param  array<string, string|list<string>>  $attributes
     */
    private function renderSamlAttributes(array $attributes): string
    {
        $result = '';

        foreach ($attributes as $name => $rawValues) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }

            $values = is_array($rawValues) ? $rawValues : [$rawValues];
            $valueNodes = '';

            foreach ($values as $value) {
                $normalizedValue = trim((string) $value);
                if ($normalizedValue === '') {
                    continue;
                }

                $valueNodes .= '<saml:AttributeValue>'.$this->xmlEscape($normalizedValue).'</saml:AttributeValue>';
            }

            if ($valueNodes === '') {
                continue;
            }

            $result .= '<saml:Attribute Name="'.$this->xmlEscape($name).'">'.$valueNodes.'</saml:Attribute>';
        }

        return $result;
    }

    private function seedSamlProvider(Tenant $tenant, string $certificatePem, bool $autoProvision): TenantSsoProvider
    {
        return TenantSsoProvider::query()->create([
            'tenant_id' => (int) $tenant->id,
            'provider' => 'saml',
            'is_enabled' => true,
            'issuer_url' => 'https://idp.example.com/metadata',
            'authorization_endpoint' => 'https://idp.example.com/sso',
            'token_endpoint' => 'https://idp.example.com/slo',
            'jwks_uri' => 'https://idp.example.com/metadata.xml',
            'client_id' => 'https://sp.example.com/'.$tenant->slug,
            'client_secret' => $certificatePem,
            'email_claim' => 'mail',
            'name_claim' => 'displayName',
            'subject_claim' => 'NameID',
            'domain_allowlist' => [],
            'auto_provision' => $autoProvision,
            'auto_provision_role' => 'member',
            'metadata' => [
                'nameid_format' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
                'enforce_signed_assertion' => true,
                'enforce_signed_logout_request' => true,
                'enforce_signed_logout_response' => false,
                'foundation_mode' => false,
            ],
        ]);
    }

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
