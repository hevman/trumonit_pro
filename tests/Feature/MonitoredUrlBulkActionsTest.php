<?php

namespace Tests\Feature;

use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MonitoredUrlBulkActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_pause_and_resume_multiple_monitors(): void
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
            'next_scan_at' => now(),
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
            'next_scan_at' => now(),
            'frequency_minutes' => 10,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $this->actingAs($owner);

        $pause = $this->post(route('monitored-urls.bulk.actions'), [
            'action' => 'pause',
            'ids' => [$a->id, $b->id],
        ]);

        $pause->assertRedirect();

        $a->refresh();
        $b->refresh();

        $this->assertFalse((bool) $a->is_active);
        $this->assertNull($a->next_scan_at);
        $this->assertFalse((bool) $b->is_active);
        $this->assertNull($b->next_scan_at);

        $resume = $this->post(route('monitored-urls.bulk.actions'), [
            'action' => 'resume',
            'ids' => [$a->id, $b->id],
        ]);

        $resume->assertRedirect();

        $a->refresh();
        $b->refresh();

        $this->assertTrue((bool) $a->is_active);
        $this->assertSame(now()->toISOString(), $a->next_scan_at?->toISOString());
        $this->assertTrue((bool) $b->is_active);
        $this->assertSame(now()->toISOString(), $b->next_scan_at?->toISOString());
    }

    public function test_owner_can_add_and_remove_tags_in_bulk(): void
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
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        app(TagService::class)->syncMonitoredUrlTags($a, ['prod']);
        app(TagService::class)->syncMonitoredUrlTags($b, ['staging']);

        $this->actingAs($owner);

        $add = $this->post(route('monitored-urls.bulk.actions'), [
            'action' => 'add_tags',
            'ids' => [$a->id, $b->id],
            'tags' => 'foo, Bar',
        ]);

        $add->assertRedirect();

        $this->assertSame(['bar', 'foo', 'prod'], $a->fresh()->tags()->pluck('name')->sort()->values()->all());
        $this->assertSame(['bar', 'foo', 'staging'], $b->fresh()->tags()->pluck('name')->sort()->values()->all());

        $remove = $this->post(route('monitored-urls.bulk.actions'), [
            'action' => 'remove_tags',
            'ids' => [$a->id, $b->id],
            'tags' => 'foo',
        ]);

        $remove->assertRedirect();

        $this->assertSame(['bar', 'prod'], $a->fresh()->tags()->pluck('name')->sort()->values()->all());
        $this->assertSame(['bar', 'staging'], $b->fresh()->tags()->pluck('name')->sort()->values()->all());

        $clear = $this->post(route('monitored-urls.bulk.actions'), [
            'action' => 'set_tags',
            'ids' => [$a->id, $b->id],
            'tags' => '',
        ]);

        $clear->assertRedirect();

        $this->assertSame([], $a->fresh()->tags()->pluck('name')->sort()->values()->all());
        $this->assertSame([], $b->fresh()->tags()->pluck('name')->sort()->values()->all());
    }

    public function test_member_cannot_use_bulk_actions(): void
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

        $response = $this->post(route('monitored-urls.bulk.actions'), [
            'action' => 'pause',
            'ids' => [1],
        ]);

        $response->assertForbidden();
    }
}
