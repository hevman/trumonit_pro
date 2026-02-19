<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\ScimProvisioningService;
use Illuminate\Console\Command;

class WidsScimTokenCommand extends Command
{
    protected $signature = 'wids:scim-token
        {tenant_id : Tenant ID}
        {--show : Show SCIM provider state}
        {--enable : Enable SCIM provider}
        {--disable : Disable SCIM provider}
        {--rotate : Rotate SCIM bearer token}
        {--auto-provision= : 1|0 auto-provision toggle}
        {--default-role= : member|owner}
        {--group-role-sync= : 1|0 group to RBAC role sync toggle}
        {--group-role-sync-strict= : 1|0 strict role fallback when mapped groups are absent}
        {--group-owner-groups= : Comma/newline separated SCIM group display names mapped to owner role}
        {--group-member-groups= : Comma/newline separated SCIM group display names mapped to member role}
        {--format=text : text|json}';

    protected $description = 'Show/update tenant SCIM provider and rotate provisioning token';

    public function handle(): int
    {
        $tenantId = (int) $this->argument('tenant_id');

        if ($tenantId < 1) {
            $this->error('Invalid tenant_id.');

            return self::FAILURE;
        }

        $tenant = Tenant::query()->find($tenantId);

        if (! $tenant) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        $service = app(ScimProvisioningService::class);
        $provider = $service->ensureProviderForTenant($tenant);
        $show = (bool) $this->option('show');
        $enable = (bool) $this->option('enable');
        $disable = (bool) $this->option('disable');
        $rotate = (bool) $this->option('rotate');
        $autoProvision = $this->option('auto-provision');
        $defaultRole = $this->option('default-role');
        $groupRoleSync = $this->option('group-role-sync');
        $groupRoleSyncStrict = $this->option('group-role-sync-strict');
        $groupOwnerGroups = $this->option('group-owner-groups');
        $groupMemberGroups = $this->option('group-member-groups');
        $format = strtolower(trim((string) $this->option('format')));
        $format = in_array($format, ['text', 'json'], true) ? $format : 'text';
        $token = null;

        if ($enable && $disable) {
            $this->error('Use either --enable or --disable, not both.');

            return self::FAILURE;
        }

        if ($enable) {
            $provider->is_enabled = true;
        }

        if ($disable) {
            $provider->is_enabled = false;
        }

        if ($autoProvision !== null) {
            $value = trim((string) $autoProvision);
            $provider->auto_provision = in_array($value, ['1', 'true', 'yes'], true);
        }

        if ($defaultRole !== null) {
            $value = strtolower(trim((string) $defaultRole));

            if (! in_array($value, ['member', 'owner'], true)) {
                $this->error('Invalid --default-role value. Use member|owner.');

                return self::FAILURE;
            }

            $provider->default_role = $value;
        }

        $metadata = is_array($provider->metadata) ? $provider->metadata : [];

        try {
            if ($groupRoleSync !== null) {
                $metadata['group_role_sync_enabled'] = $this->parseBooleanOption($groupRoleSync, '--group-role-sync');
            }

            if ($groupRoleSyncStrict !== null) {
                $metadata['group_role_sync_strict'] = $this->parseBooleanOption($groupRoleSyncStrict, '--group-role-sync-strict');
            }
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($groupOwnerGroups !== null) {
            $metadata['group_role_mappings'] = is_array($metadata['group_role_mappings'] ?? null) ? $metadata['group_role_mappings'] : [];
            $metadata['group_role_mappings']['owner'] = $this->parseGroupNamesOption((string) $groupOwnerGroups);
        }

        if ($groupMemberGroups !== null) {
            $metadata['group_role_mappings'] = is_array($metadata['group_role_mappings'] ?? null) ? $metadata['group_role_mappings'] : [];
            $metadata['group_role_mappings']['member'] = $this->parseGroupNamesOption((string) $groupMemberGroups);
        }

        $provider->metadata = $metadata;

        if ($rotate) {
            $rotated = $service->rotateToken($provider);
            $provider = $rotated['provider'];
            $token = (string) $rotated['token'];
        } else {
            $provider->save();
        }

        $payload = [
            'tenant_id' => (int) $tenant->id,
            'tenant_slug' => (string) $tenant->slug,
            'is_enabled' => (bool) $provider->is_enabled,
            'auto_provision' => (bool) $provider->auto_provision,
            'default_role' => (string) $provider->default_role,
            'deprovision_mode' => (string) $provider->deprovision_mode,
            'group_role_sync_enabled' => (bool) ($metadata['group_role_sync_enabled'] ?? false),
            'group_role_sync_strict' => (bool) ($metadata['group_role_sync_strict'] ?? false),
            'group_role_mappings' => is_array($metadata['group_role_mappings'] ?? null) ? $metadata['group_role_mappings'] : [],
            'auth_token_last8' => $provider->auth_token_last8,
            'last_rotated_at' => optional($provider->last_rotated_at)->toISOString(),
            'endpoint_base' => url('/api/scim/v2'),
            'service_provider_config_url' => url('/api/scim/v2/ServiceProviderConfig'),
        ];

        if (is_string($token)) {
            $payload['auth_token'] = $token;
            $payload['token_notice'] = 'Save token now, it is not shown again.';
        }

        if ($format === 'json') {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($rotate) {
            $this->info('SCIM token rotated.');
        } elseif ($enable || $disable || $autoProvision !== null || $defaultRole !== null) {
            $this->info('SCIM provider updated.');
        } elseif ($show || ! $rotate) {
            $this->info('SCIM provider state:');
        }

        foreach ($payload as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            $this->line('  '.$key.': '.(is_scalar($value) || $value === null ? (string) $value : json_encode($value)));
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function parseGroupNamesOption(string $value): array
    {
        $parts = preg_split('/[\r\n,;]+/', $value) ?: [];
        $result = [];

        foreach ($parts as $part) {
            $name = trim((string) $part);

            if ($name === '') {
                continue;
            }

            $result[strtolower($name)] = $name;
        }

        return array_values($result);
    }

    private function parseBooleanOption(mixed $value, string $optionName): bool
    {
        $normalized = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if (! is_bool($normalized)) {
            throw new \InvalidArgumentException($optionName.' expects 1|0|true|false|yes|no.');
        }

        return $normalized;
    }
}
