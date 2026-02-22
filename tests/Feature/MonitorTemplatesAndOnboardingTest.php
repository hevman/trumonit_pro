<?php

namespace Tests\Feature;

use App\Jobs\ScanMonitoredUrlJob;
use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MonitorTemplatesAndOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_onboarding_wizard(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $this->actingAs($owner)
            ->get(route('onboarding.show'))
            ->assertOk()
            ->assertSee('Onboarding wizard')
            ->assertSee('Website uptime')
            ->assertSee('API health endpoint')
            ->assertSee('Keyword monitor')
            ->assertSee('Cron heartbeat');
    }

    public function test_member_cannot_open_onboarding_wizard(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $member = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'member',
        ]);

        $this->actingAs($member)
            ->get(route('onboarding.show'))
            ->assertForbidden();
    }

    public function test_template_defaults_are_applied_on_web_store(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $this->actingAs($owner);

        $this->post(route('monitored-urls.store'), [
            'template' => 'status_page',
            'url' => 'https://example.com/status',
            'display_name' => 'Status',
            'is_active' => true,
        ])->assertRedirect();

        $monitor = MonitoredUrl::query()
            ->where('tenant_id', $tenant->id)
            ->where('url', 'https://example.com/status')
            ->firstOrFail();

        $this->assertSame('http', $monitor->scan_mode);
        $this->assertSame(5, $monitor->frequency_minutes);
        $this->assertSame(20, $monitor->timeout_seconds);
        $this->assertSame(200, $monitor->expected_status_min);
        $this->assertSame(399, $monitor->expected_status_max);
        $this->assertSame(2, $monitor->alert_after_failures);
        $this->assertSame(30, $monitor->alert_cooldown_minutes);
        $this->assertSame('status_page', $monitor->content_rules['mode'] ?? null);
    }

    public function test_onboarding_creation_dispatches_first_scan_and_sets_guidance_message(): void
    {
        Queue::fake();

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $this->actingAs($owner);

        $response = $this->post(route('monitored-urls.store'), [
            'from_onboarding' => true,
            'template' => 'api_health',
            'url' => 'https://example.com/health',
            'display_name' => 'API Health',
            'is_active' => true,
        ]);

        $monitor = MonitoredUrl::query()
            ->where('tenant_id', $tenant->id)
            ->where('url', 'https://example.com/health')
            ->firstOrFail();

        $response
            ->assertRedirect(route('monitored-urls.show', $monitor))
            ->assertSessionHas(
                'status',
                'Monitor created. First check queued.',
            );

        $this->assertSame(2, $monitor->frequency_minutes);
        $this->assertSame(200, $monitor->expected_status_min);
        $this->assertSame(299, $monitor->expected_status_max);

        Queue::assertPushed(ScanMonitoredUrlJob::class, function (ScanMonitoredUrlJob $job) use ($monitor): bool {
            return $job->monitoredUrlId === $monitor->id;
        });
    }

    public function test_heartbeat_template_generates_token_and_grace_defaults(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $this->actingAs($owner);

        $this->post(route('monitored-urls.store'), [
            'template' => 'cron_heartbeat',
            'url' => 'https://example.com/cron',
            'display_name' => 'Cron',
            'is_active' => true,
        ])->assertRedirect();

        $monitor = MonitoredUrl::query()
            ->where('tenant_id', $tenant->id)
            ->where('url', 'https://example.com/cron')
            ->firstOrFail();

        $this->assertSame('heartbeat', $monitor->scan_mode);
        $this->assertIsArray($monitor->content_rules);
        $this->assertIsArray($monitor->content_rules['heartbeat'] ?? null);
        $this->assertNotEmpty($monitor->content_rules['heartbeat']['token'] ?? null);
        $this->assertSame(3, (int) ($monitor->content_rules['heartbeat']['grace_minutes'] ?? 0));
    }
}
