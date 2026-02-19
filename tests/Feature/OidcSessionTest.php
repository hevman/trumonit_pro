<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantSsoProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OidcSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_oidc_login_links_identity_to_existing_user(): void
    {
        config()->set('wids.sso.oidc.enabled', true);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'member',
            'email' => 'john@acme.com',
        ]);

        $this->seedProvider($tenant, autoProvision: false);
        $state = $this->startOidcAndExtractState($tenant->slug);

        Http::fake([
            'https://1.1.1.1/token' => Http::response([
                'access_token' => 'token-existing',
                'token_type' => 'Bearer',
            ]),
            'https://1.1.1.1/userinfo' => Http::response([
                'sub' => 'oidc-sub-existing',
                'email' => 'john@acme.com',
                'name' => 'John Existing',
                'email_verified' => true,
            ]),
        ]);

        $response = $this->get(route('sso.oidc.callback', [
            'tenantSlug' => $tenant->slug,
            'code' => 'code-existing',
            'state' => $state,
        ]));

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user->fresh());
        $this->assertDatabaseHas('user_identities', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'provider' => 'oidc',
            'provider_subject' => 'oidc-sub-existing',
            'email' => 'john@acme.com',
        ]);
    }

    public function test_oidc_login_auto_provisions_member_when_enabled(): void
    {
        config()->set('wids.sso.oidc.enabled', true);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $this->seedProvider($tenant, autoProvision: true);
        $state = $this->startOidcAndExtractState($tenant->slug);

        Http::fake([
            'https://1.1.1.1/token' => Http::response([
                'access_token' => 'token-new',
                'token_type' => 'Bearer',
            ]),
            'https://1.1.1.1/userinfo' => Http::response([
                'sub' => 'oidc-sub-new',
                'email' => 'new.user@acme.com',
                'name' => 'New User',
                'email_verified' => true,
            ]),
        ]);

        $response = $this->get(route('sso.oidc.callback', [
            'tenantSlug' => $tenant->slug,
            'code' => 'code-new',
            'state' => $state,
        ]));

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();

        $user = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('email', 'new.user@acme.com')
            ->firstOrFail();

        $this->assertSame('member', $user->role);
        $this->assertDatabaseHas('user_identities', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'provider' => 'oidc',
            'provider_subject' => 'oidc-sub-new',
            'email' => 'new.user@acme.com',
        ]);
    }

    public function test_oidc_login_rejects_unknown_user_when_auto_provision_disabled(): void
    {
        config()->set('wids.sso.oidc.enabled', true);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $this->seedProvider($tenant, autoProvision: false);
        $state = $this->startOidcAndExtractState($tenant->slug);

        Http::fake([
            'https://1.1.1.1/token' => Http::response([
                'access_token' => 'token-reject',
                'token_type' => 'Bearer',
            ]),
            'https://1.1.1.1/userinfo' => Http::response([
                'sub' => 'oidc-sub-reject',
                'email' => 'unknown@acme.com',
                'name' => 'Unknown User',
                'email_verified' => true,
            ]),
        ]);

        $response = $this->get(route('sso.oidc.callback', [
            'tenantSlug' => $tenant->slug,
            'code' => 'code-reject',
            'state' => $state,
        ]));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
        $response->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', [
            'tenant_id' => $tenant->id,
            'email' => 'unknown@acme.com',
        ]);
    }

    public function test_oidc_login_redirect_is_blocked_when_provider_is_disallowed_by_policy(): void
    {
        config()->set('wids.sso.oidc.enabled', true);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'identity_governance' => [
                'enforce_sso_for' => 'off',
                'allow_password_login' => true,
                'allowed_sso_providers' => ['saml'],
                'session_max_minutes' => 0,
            ],
        ]);

        $this->seedProvider($tenant, autoProvision: true);

        $response = $this->from(route('login'))->get(route('sso.oidc.redirect', [
            'tenant' => $tenant->slug,
        ]));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
    }

    private function seedProvider(Tenant $tenant, bool $autoProvision): TenantSsoProvider
    {
        return TenantSsoProvider::query()->create([
            'tenant_id' => (int) $tenant->id,
            'provider' => 'oidc',
            'is_enabled' => true,
            'issuer_url' => 'https://1.1.1.1/issuer',
            'authorization_endpoint' => 'https://1.1.1.1/authorize',
            'token_endpoint' => 'https://1.1.1.1/token',
            'userinfo_endpoint' => 'https://1.1.1.1/userinfo',
            'jwks_uri' => 'https://1.1.1.1/jwks',
            'client_id' => 'client-acme',
            'client_secret' => 'secret-acme',
            'scopes' => 'openid profile email',
            'email_claim' => 'email',
            'name_claim' => 'name',
            'subject_claim' => 'sub',
            'domain_allowlist' => [],
            'auto_provision' => $autoProvision,
            'auto_provision_role' => 'member',
            'enforce_verified_email' => true,
        ]);
    }

    private function startOidcAndExtractState(string $tenantSlug): string
    {
        $response = $this->get(route('sso.oidc.redirect', [
            'tenant' => $tenantSlug,
        ]));

        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('https://1.1.1.1/authorize?', $location);

        $query = [];
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $state = is_string($query['state'] ?? null) ? (string) $query['state'] : '';
        $this->assertNotSame('', $state);

        return $state;
    }
}
