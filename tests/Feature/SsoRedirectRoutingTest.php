<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantSsoProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SsoRedirectRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_unified_sso_redirect_uses_domain_route_when_match_exists(): void
    {
        config()->set('wids.sso.oidc.enabled', true);
        config()->set('wids.sso.saml.enabled', true);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'identity_governance' => [
                'enforce_sso_for' => 'off',
                'allow_password_login' => true,
                'allowed_sso_providers' => ['oidc', 'saml'],
                'session_max_minutes' => 0,
                'default_sso_provider' => 'oidc',
                'sso_domain_routing' => [
                    'partner.com' => 'saml',
                ],
            ],
        ]);

        $this->seedProvider($tenant, 'oidc', true);
        $this->seedProvider($tenant, 'saml', true);

        $response = $this->get(route('sso.redirect', [
            'tenant' => $tenant->slug,
            'email' => 'user@partner.com',
        ]));

        $response->assertRedirect(route('sso.saml.redirect', ['tenant' => $tenant->slug]));
    }

    public function test_unified_sso_redirect_uses_default_provider_when_no_domain_match(): void
    {
        config()->set('wids.sso.oidc.enabled', true);
        config()->set('wids.sso.saml.enabled', true);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'identity_governance' => [
                'enforce_sso_for' => 'off',
                'allow_password_login' => true,
                'allowed_sso_providers' => ['oidc', 'saml'],
                'session_max_minutes' => 0,
                'default_sso_provider' => 'oidc',
                'sso_domain_routing' => [],
            ],
        ]);

        $this->seedProvider($tenant, 'oidc', true);
        $this->seedProvider($tenant, 'saml', true);

        $response = $this->get(route('sso.redirect', [
            'tenant' => $tenant->slug,
            'email' => 'user@acme.com',
        ]));

        $response->assertRedirect(route('sso.oidc.redirect', ['tenant' => $tenant->slug]));
    }

    public function test_unified_sso_redirect_returns_error_when_no_allowed_enabled_provider(): void
    {
        config()->set('wids.sso.oidc.enabled', true);
        config()->set('wids.sso.saml.enabled', true);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'identity_governance' => [
                'enforce_sso_for' => 'off',
                'allow_password_login' => true,
                'allowed_sso_providers' => ['saml'],
                'session_max_minutes' => 0,
                'default_sso_provider' => 'saml',
                'sso_domain_routing' => [],
            ],
        ]);

        $this->seedProvider($tenant, 'oidc', true);
        $this->seedProvider($tenant, 'saml', false);

        $response = $this->from(route('login'))->get(route('sso.redirect', [
            'tenant' => $tenant->slug,
        ]));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
    }

    private function seedProvider(Tenant $tenant, string $provider, bool $enabled): TenantSsoProvider
    {
        return TenantSsoProvider::query()->create([
            'tenant_id' => (int) $tenant->id,
            'provider' => $provider,
            'is_enabled' => $enabled,
            'metadata' => [],
        ]);
    }
}
