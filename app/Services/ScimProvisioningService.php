<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantScimProvider;
use Illuminate\Support\Str;

class ScimProvisioningService
{
    public function isEnabled(): bool
    {
        return (bool) config('wids.scim.enabled', false);
    }

    public function ensureProviderForTenant(Tenant $tenant): TenantScimProvider
    {
        return TenantScimProvider::query()->firstOrCreate(
            [
                'tenant_id' => (int) $tenant->id,
            ],
            [
                'is_enabled' => false,
                'auto_provision' => false,
                'default_role' => 'member',
                'deprovision_mode' => 'disable',
                'domain_allowlist' => [],
            ]
        );
    }

    /**
     * @return array{provider: TenantScimProvider, token: string}
     */
    public function rotateToken(TenantScimProvider $provider): array
    {
        $token = $this->generateToken();

        $provider->forceFill([
            'auth_token_hash' => $this->tokenHash($token),
            'auth_token_last8' => $this->tokenLast8($token),
            'last_rotated_at' => now(),
            'last_error' => null,
        ])->save();

        return [
            'provider' => $provider,
            'token' => $token,
        ];
    }

    public function findEnabledProviderByToken(string $token): ?TenantScimProvider
    {
        $token = trim($token);

        if ($token === '') {
            return null;
        }

        return TenantScimProvider::query()
            ->where('auth_token_hash', $this->tokenHash($token))
            ->where('is_enabled', true)
            ->first();
    }

    public function tokenHash(string $token): string
    {
        return hash('sha256', $token);
    }

    private function generateToken(): string
    {
        $prefix = trim((string) config('wids.scim.token_prefix', 'wids_scim'));
        $prefix = $prefix !== '' ? $prefix : 'wids_scim';

        return $prefix.'_'.Str::random(56);
    }

    private function tokenLast8(string $token): string
    {
        return substr($token, -8);
    }
}
