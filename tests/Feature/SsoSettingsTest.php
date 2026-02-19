<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantSsoProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SsoSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_update_oidc_settings(): void
    {
        config()->set('wids.sso.oidc.enabled', true);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $this->actingAs($owner);

        Http::fake([
            'https://1.1.1.1/issuer/.well-known/openid-configuration' => Http::response([
                'issuer' => 'https://1.1.1.1/issuer',
                'authorization_endpoint' => 'https://1.1.1.1/authorize',
                'token_endpoint' => 'https://1.1.1.1/token',
                'userinfo_endpoint' => 'https://1.1.1.1/userinfo',
                'jwks_uri' => 'https://1.1.1.1/jwks',
            ]),
        ]);

        $response = $this->put(route('sso.update'), [
            'oidc_enabled' => 1,
            'issuer_url' => 'https://1.1.1.1/issuer',
            'client_id' => 'client-acme',
            'client_secret' => 'secret-acme',
            'scopes' => 'openid profile email',
            'email_claim' => 'email',
            'name_claim' => 'name',
            'subject_claim' => 'sub',
            'domain_allowlist' => "acme.com\npartners.acme.com",
            'auto_provision' => 1,
            'auto_provision_role' => 'member',
            'enforce_verified_email' => 1,
            'identity_enforce_sso_for' => 'owner',
            'identity_allow_password_login' => 1,
            'identity_allow_oidc' => 1,
            'identity_allow_saml' => 0,
            'identity_default_sso_provider' => 'oidc',
            'identity_sso_domain_routing' => "acme.com oidc\npartners.acme.com oidc",
            'identity_session_max_minutes' => 90,
        ]);

        $response->assertRedirect(route('sso.edit'));

        $provider = TenantSsoProvider::query()
            ->where('tenant_id', $tenant->id)
            ->where('provider', 'oidc')
            ->firstOrFail();

        $this->assertTrue((bool) $provider->is_enabled);
        $this->assertSame('https://1.1.1.1/issuer', $provider->issuer_url);
        $this->assertSame('https://1.1.1.1/authorize', $provider->authorization_endpoint);
        $this->assertSame('https://1.1.1.1/token', $provider->token_endpoint);
        $this->assertSame('https://1.1.1.1/userinfo', $provider->userinfo_endpoint);
        $this->assertSame('https://1.1.1.1/jwks', $provider->jwks_uri);
        $this->assertSame('client-acme', $provider->client_id);
        $this->assertSame(['acme.com', 'partners.acme.com'], $provider->domain_allowlist);
        $this->assertTrue((bool) $provider->auto_provision);
        $this->assertSame('member', $provider->auto_provision_role);
        $this->assertTrue((bool) $provider->enforce_verified_email);
        $this->assertNotNull($provider->last_tested_at);

        $tenant->refresh();
        $this->assertSame([
            'enforce_sso_for' => 'owner',
            'allow_password_login' => true,
            'allowed_sso_providers' => ['oidc'],
            'session_max_minutes' => 90,
            'default_sso_provider' => 'oidc',
            'sso_domain_routing' => [
                'acme.com' => 'oidc',
                'partners.acme.com' => 'oidc',
            ],
        ], $tenant->identity_governance);
    }

    public function test_member_cannot_manage_sso_settings(): void
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

        $this->get(route('sso.edit'))->assertForbidden();
        $this->put(route('sso.update'), [
            'oidc_enabled' => 1,
            'issuer_url' => 'https://1.1.1.1/issuer',
            'client_id' => 'client',
            'client_secret' => 'secret',
        ])->assertForbidden();
    }

    public function test_owner_cannot_set_domain_route_for_disallowed_provider(): void
    {
        config()->set('wids.sso.oidc.enabled', true);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $this->actingAs($owner);

        Http::fake([
            'https://1.1.1.1/issuer/.well-known/openid-configuration' => Http::response([
                'issuer' => 'https://1.1.1.1/issuer',
                'authorization_endpoint' => 'https://1.1.1.1/authorize',
                'token_endpoint' => 'https://1.1.1.1/token',
                'userinfo_endpoint' => 'https://1.1.1.1/userinfo',
                'jwks_uri' => 'https://1.1.1.1/jwks',
            ]),
        ]);

        $response = $this->from(route('sso.edit'))->put(route('sso.update'), [
            'oidc_enabled' => 1,
            'issuer_url' => 'https://1.1.1.1/issuer',
            'client_id' => 'client-acme',
            'client_secret' => 'secret-acme',
            'identity_enforce_sso_for' => 'off',
            'identity_allow_password_login' => 1,
            'identity_allow_oidc' => 1,
            'identity_allow_saml' => 0,
            'identity_default_sso_provider' => 'oidc',
            'identity_sso_domain_routing' => 'partner.com saml',
            'identity_session_max_minutes' => 0,
        ]);

        $response->assertRedirect(route('sso.edit'));
        $response->assertSessionHasErrors('identity_sso_domain_routing');
    }

    public function test_owner_cannot_disable_password_without_any_enabled_allowed_sso_provider(): void
    {
        config()->set('wids.sso.oidc.enabled', false);
        config()->set('wids.sso.saml.enabled', false);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $this->actingAs($owner);

        $response = $this->from(route('sso.edit'))->put(route('sso.update'), [
            'oidc_enabled' => 0,
            'identity_enforce_sso_for' => 'all',
            'identity_allow_password_login' => 0,
            'identity_allow_oidc' => 1,
            'identity_allow_saml' => 0,
            'identity_session_max_minutes' => 30,
        ]);

        $response->assertRedirect(route('sso.edit'));
        $response->assertSessionHasErrors('identity_enforce_sso_for');

        $tenant->refresh();
        $this->assertNull($tenant->identity_governance);
    }
}
