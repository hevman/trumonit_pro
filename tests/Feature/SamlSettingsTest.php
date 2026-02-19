<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantSsoProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SamlSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_update_saml_settings(): void
    {
        config()->set('wids.sso.saml.enabled', true);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $this->actingAs($owner);

        $response = $this->put(route('sso.saml.update'), [
            'saml_enabled' => 1,
            'idp_entity_id' => 'https://idp.example.com/metadata',
            'sso_url' => 'https://idp.example.com/sso',
            'slo_url' => 'https://idp.example.com/slo',
            'metadata_url' => 'https://idp.example.com/metadata.xml',
            'sp_entity_id' => 'https://trumonit.example.com/sp/acme',
            'x509_certificate' => "-----BEGIN CERTIFICATE-----\nMIIB\n-----END CERTIFICATE-----",
            'sp_private_key' => "-----BEGIN PRIVATE KEY-----\nMIIE\n-----END PRIVATE KEY-----",
            'email_attribute' => 'mail',
            'name_attribute' => 'displayName',
            'subject_attribute' => 'NameID',
            'nameid_format' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
            'domain_allowlist' => "acme.com\npartners.acme.com",
            'auto_provision' => 1,
            'auto_provision_role' => 'member',
            'enforce_signed_assertion' => 1,
            'enforce_signed_logout_request' => 1,
            'enforce_signed_logout_response' => 1,
        ]);

        $response->assertRedirect(route('sso.saml.edit'));

        $provider = TenantSsoProvider::query()
            ->where('tenant_id', $tenant->id)
            ->where('provider', 'saml')
            ->firstOrFail();

        $this->assertTrue((bool) $provider->is_enabled);
        $this->assertSame('https://idp.example.com/metadata', $provider->issuer_url);
        $this->assertSame('https://idp.example.com/sso', $provider->authorization_endpoint);
        $this->assertSame('https://idp.example.com/slo', $provider->token_endpoint);
        $this->assertSame('https://idp.example.com/metadata.xml', $provider->jwks_uri);
        $this->assertSame('https://trumonit.example.com/sp/acme', $provider->client_id);
        $this->assertSame("-----BEGIN PRIVATE KEY-----\nMIIE\n-----END PRIVATE KEY-----", $provider->private_key);
        $this->assertSame('mail', $provider->email_claim);
        $this->assertSame('displayName', $provider->name_claim);
        $this->assertSame('NameID', $provider->subject_claim);
        $this->assertSame(['acme.com', 'partners.acme.com'], $provider->domain_allowlist);
        $this->assertTrue((bool) $provider->auto_provision);
        $this->assertSame('member', $provider->auto_provision_role);
        $this->assertIsArray($provider->metadata);
        $this->assertTrue((bool) ($provider->metadata['enforce_signed_assertion'] ?? false));
        $this->assertTrue((bool) ($provider->metadata['enforce_signed_logout_request'] ?? false));
        $this->assertTrue((bool) ($provider->metadata['enforce_signed_logout_response'] ?? false));
        $this->assertSame('urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress', $provider->metadata['nameid_format'] ?? null);
    }

    public function test_member_cannot_manage_saml_settings(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $member = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'member',
        ]);

        $this->actingAs($member);

        $this->get(route('sso.saml.edit'))->assertForbidden();
        $this->put(route('sso.saml.update'), [
            'saml_enabled' => 1,
            'idp_entity_id' => 'https://idp.example.com/metadata',
            'sso_url' => 'https://idp.example.com/sso',
        ])->assertForbidden();
    }
}
