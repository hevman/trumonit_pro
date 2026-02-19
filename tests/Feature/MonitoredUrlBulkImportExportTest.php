<?php

namespace Tests\Feature;

use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MonitoredUrlBulkImportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_export_monitors_with_tags(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00', 'UTC'));

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $a = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'url' => 'https://example.com/a',
            'display_name' => 'A',
            'is_active' => true,
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $b = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'url' => 'https://example.com/b',
            'display_name' => 'B',
            'is_active' => true,
            'frequency_minutes' => 10,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        app(TagService::class)->syncMonitoredUrlTags($a, ['prod', 'checkout']);
        app(TagService::class)->syncMonitoredUrlTags($b, ['staging']);

        $this->actingAs($owner);

        $response = $this->get(route('monitored-urls.bulk.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertHeader('Content-Disposition');

        $payload = json_decode((string) $response->streamedContent(), true);

        $this->assertIsArray($payload);
        $this->assertSame(1, $payload['version'] ?? null);
        $this->assertCount(2, $payload['monitors'] ?? []);

        $tagsByUrl = collect($payload['monitors'])
            ->keyBy('url')
            ->map(fn ($row) => $row['tags'] ?? [])
            ->all();

        $this->assertSame(['checkout', 'prod'], $tagsByUrl['https://example.com/a'] ?? []);
        $this->assertSame(['staging'], $tagsByUrl['https://example.com/b'] ?? []);
    }

    public function test_owner_can_import_monitors_and_tags(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00', 'UTC'));

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $existing = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'url' => 'https://example.com/existing',
            'display_name' => 'Existing',
            'is_active' => true,
            'frequency_minutes' => 10,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        app(TagService::class)->syncMonitoredUrlTags($existing, ['old']);

        $payload = [
            'version' => 1,
            'monitors' => [
                [
                    'url' => 'https://example.com/existing',
                    'display_name' => 'Existing updated',
                    'is_active' => true,
                    'frequency_minutes' => 5,
                    'timeout_seconds' => 20,
                    'follow_redirects' => false,
                    'expected_status_min' => 200,
                    'expected_status_max' => 399,
                    'scan_mode' => 'http',
                    'tags' => ['prod'],
                ],
                [
                    'url' => 'https://example.com/new',
                    'display_name' => 'New',
                    'is_active' => true,
                    'frequency_minutes' => 15,
                    'timeout_seconds' => 30,
                    'follow_redirects' => true,
                    'expected_status_min' => 200,
                    'expected_status_max' => 399,
                    'scan_mode' => 'http',
                    'tags' => ['staging'],
                ],
            ],
        ];

        $this->actingAs($owner);

        $response = $this->post(route('monitored-urls.bulk.import'), [
            'json' => json_encode($payload),
        ]);

        $response->assertRedirect(route('monitored-urls.bulk'));

        $this->assertSame(2, MonitoredUrl::query()->where('tenant_id', $tenant->id)->count());

        $existing->refresh();

        $this->assertSame('Existing updated', $existing->display_name);
        $this->assertSame(5, (int) $existing->frequency_minutes);
        $this->assertSame(20, (int) $existing->timeout_seconds);
        $this->assertFalse((bool) $existing->follow_redirects);
        $this->assertNotNull($existing->next_scan_at);
        $this->assertSame(now()->toISOString(), $existing->next_scan_at?->toISOString());
        $this->assertSame(['prod'], $existing->tags()->pluck('name')->sort()->values()->all());

        $new = MonitoredUrl::query()
            ->where('tenant_id', $tenant->id)
            ->where('url', 'https://example.com/new')
            ->firstOrFail();

        $this->assertNotNull($new->next_scan_at);
        $this->assertSame(now()->toISOString(), $new->next_scan_at?->toISOString());
        $this->assertSame(['staging'], $new->tags()->pluck('name')->sort()->values()->all());
    }
}
