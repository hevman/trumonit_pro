<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_is_terminated_when_max_duration_is_exceeded(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'identity_governance' => [
                'enforce_sso_for' => 'off',
                'allow_password_login' => true,
                'allowed_sso_providers' => ['oidc', 'saml'],
                'session_max_minutes' => 1,
            ],
        ]);

        $user = User::factory()->create([
            'tenant_id' => (int) $tenant->id,
            'role' => 'member',
        ]);

        $response = $this->actingAs($user)
            ->withSession([
                'auth_session_started_at' => now()->subMinutes(5)->timestamp,
            ])
            ->get(route('dashboard'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_password_session_is_terminated_when_sso_is_required(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'identity_governance' => [
                'enforce_sso_for' => 'all',
                'allow_password_login' => true,
                'allowed_sso_providers' => ['oidc', 'saml'],
                'session_max_minutes' => 0,
            ],
        ]);

        $user = User::factory()->create([
            'tenant_id' => (int) $tenant->id,
            'role' => 'member',
        ]);

        $response = $this->actingAs($user)
            ->withSession([
                'auth_session_started_at' => now()->timestamp,
            ])
            ->get(route('dashboard'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_sso_session_passes_when_policy_requires_sso(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'identity_governance' => [
                'enforce_sso_for' => 'all',
                'allow_password_login' => true,
                'allowed_sso_providers' => ['oidc'],
                'session_max_minutes' => 0,
            ],
        ]);

        $user = User::factory()->create([
            'tenant_id' => (int) $tenant->id,
            'role' => 'member',
        ]);

        $response = $this->actingAs($user)
            ->withSession([
                'auth_session_started_at' => now()->timestamp,
                'sso_auth' => [
                    'provider' => 'oidc',
                    'tenant_id' => (int) $tenant->id,
                    'tenant_slug' => (string) $tenant->slug,
                    'provider_subject' => 'oidc-subject-1',
                ],
            ])
            ->get(route('dashboard'));

        $response->assertOk();
        $this->assertAuthenticatedAs($user);
    }
}
