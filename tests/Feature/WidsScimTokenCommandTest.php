<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantScimProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class WidsScimTokenCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_can_enable_and_rotate_scim_token(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $exitCode = Artisan::call('wids:scim-token', [
            'tenant_id' => (string) $tenant->id,
            '--enable' => true,
            '--rotate' => true,
            '--format' => 'json',
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"is_enabled": true', $output);
        $this->assertStringContainsString('"auth_token"', $output);

        $provider = TenantScimProvider::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->assertTrue((bool) $provider->is_enabled);
        $this->assertNotNull($provider->auth_token_hash);
        $this->assertNotNull($provider->auth_token_last8);
        $this->assertNotNull($provider->last_rotated_at);
    }

    public function test_command_can_show_provider_state(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        TenantScimProvider::query()->create([
            'tenant_id' => $tenant->id,
            'is_enabled' => false,
            'auto_provision' => false,
            'default_role' => 'member',
            'deprovision_mode' => 'disable',
        ]);

        $exitCode = Artisan::call('wids:scim-token', [
            'tenant_id' => (string) $tenant->id,
            '--show' => true,
            '--format' => 'json',
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"tenant_id": '.$tenant->id, $output);
        $this->assertStringContainsString('"is_enabled": false', $output);
    }

    public function test_command_can_configure_group_role_sync_metadata(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $exitCode = Artisan::call('wids:scim-token', [
            'tenant_id' => (string) $tenant->id,
            '--group-role-sync' => '1',
            '--group-role-sync-strict' => '1',
            '--group-owner-groups' => 'Owners,Platform Admins',
            '--group-member-groups' => 'Members,Employees',
            '--format' => 'json',
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"group_role_sync_enabled": true', $output);
        $this->assertStringContainsString('"group_role_sync_strict": true', $output);

        $provider = TenantScimProvider::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $metadata = is_array($provider->metadata) ? $provider->metadata : [];

        $this->assertTrue((bool) ($metadata['group_role_sync_enabled'] ?? false));
        $this->assertTrue((bool) ($metadata['group_role_sync_strict'] ?? false));
        $this->assertSame(
            ['Owners', 'Platform Admins'],
            array_values($metadata['group_role_mappings']['owner'] ?? [])
        );
        $this->assertSame(
            ['Members', 'Employees'],
            array_values($metadata['group_role_mappings']['member'] ?? [])
        );
    }
}
