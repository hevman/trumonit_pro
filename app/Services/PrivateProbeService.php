<?php

namespace App\Services;

use App\Models\PrivateProbe;
use App\Models\Tenant;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PrivateProbeService
{
    /**
     * @param  array<string, mixed>|null  $capabilities
     * @return array{probe: PrivateProbe, token: string}
     */
    public function createForTenant(
        Tenant $tenant,
        string $name,
        ?int $createdByUserId = null,
        ?string $regionHint = null,
        ?array $capabilities = null
    ): array {
        $policyService = app(PrivateProbeFleetPolicyService::class);

        if (! $policyService->canCreateProbeForTenant($tenant)) {
            throw ValidationException::withMessages([
                'private_probes' => ['Active private probe limit reached for your plan tier.'],
            ]);
        }

        $normalizedRegionHint = $this->normalizeRegionHint($regionHint);

        if (! $policyService->isRegionAllowedForTenant($tenant, $normalizedRegionHint)) {
            throw ValidationException::withMessages([
                'region_hint' => ['Region is not allowed by current residency policy for your plan tier.'],
            ]);
        }

        $token = $this->generateToken();

        $probe = PrivateProbe::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $createdByUserId,
            'name' => trim($name),
            'region_hint' => $normalizedRegionHint,
            'capabilities' => $this->normalizeCapabilities($capabilities),
            'auth_token_hash' => $this->tokenHash($token),
            'auth_token_last8' => $this->tokenLast8($token),
            'is_active' => true,
        ]);

        return [
            'probe' => $probe,
            'token' => $token,
        ];
    }

    /**
     * @return array{probe: PrivateProbe, token: string}
     */
    public function rotateToken(PrivateProbe $probe): array
    {
        $token = $this->generateToken();

        $probe->forceFill([
            'auth_token_hash' => $this->tokenHash($token),
            'auth_token_last8' => $this->tokenLast8($token),
            'is_active' => true,
        ])->save();

        return [
            'probe' => $probe,
            'token' => $token,
        ];
    }

    public function findActiveByToken(string $token): ?PrivateProbe
    {
        $token = trim($token);

        if ($token === '') {
            return null;
        }

        $hash = $this->tokenHash($token);

        return PrivateProbe::query()
            ->where('auth_token_hash', $hash)
            ->where('is_active', true)
            ->first();
    }

    public function isTenantTierAllowed(Tenant $tenant): bool
    {
        $allowedTiers = config('wids.private_probes.allowed_tiers', []);
        $allowedTiers = is_array($allowedTiers) ? $allowedTiers : [];

        $normalized = [];
        foreach ($allowedTiers as $tier) {
            if (! is_string($tier)) {
                continue;
            }

            $tier = strtolower(trim($tier));
            if ($tier !== '') {
                $normalized[$tier] = true;
            }
        }

        if ($normalized === []) {
            return true;
        }

        $tenantTier = strtolower(trim((string) ($tenant->plan_tier ?? config('wids.default_plan_tier', 'lite'))));

        return isset($normalized[$tenantTier]);
    }

    private function generateToken(): string
    {
        $prefix = trim((string) config('wids.private_probes.token_prefix', 'wids_probe'));
        $prefix = $prefix !== '' ? $prefix : 'wids_probe';

        return $prefix.'_'.Str::random(56);
    }

    private function tokenHash(string $token): string
    {
        return hash('sha256', $token);
    }

    private function tokenLast8(string $token): string
    {
        return substr($token, -8);
    }

    private function normalizeRegionHint(?string $regionHint): ?string
    {
        if (! is_string($regionHint)) {
            return null;
        }

        $regionHint = trim($regionHint);

        return $regionHint !== '' ? $regionHint : null;
    }

    /**
     * @param  array<string, mixed>|null  $capabilities
     * @return array<string, mixed>|null
     */
    private function normalizeCapabilities(?array $capabilities): ?array
    {
        if (! is_array($capabilities) || $capabilities === []) {
            return null;
        }

        return $capabilities;
    }
}
