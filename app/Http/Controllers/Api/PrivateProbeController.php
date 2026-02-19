<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PrivateProbe;
use App\Models\Tenant;
use App\Services\AuditLogService;
use App\Services\PrivateProbeService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PrivateProbeController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = (int) ($request->user()?->tenant_id ?? 0);

        if ($tenantId <= 0) {
            return response()->json(['message' => 'User has no tenant.'], 400);
        }

        $staleAfterMinutes = max(1, min(1440, (int) config('wids.private_probes.stale_after_minutes', 10)));
        $staleCutoff = now()->subMinutes($staleAfterMinutes);

        $probes = PrivateProbe::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->get()
            ->map(function (PrivateProbe $probe) use ($staleCutoff): array {
                $isStale = $probe->is_active && ($probe->last_seen_at === null || $probe->last_seen_at->lt($staleCutoff));

                return [
                    'id' => $probe->id,
                    'name' => $probe->name,
                    'region_hint' => $probe->region_hint,
                    'capabilities' => is_array($probe->capabilities) ? $probe->capabilities : [],
                    'is_active' => (bool) $probe->is_active,
                    'last_seen_at' => optional($probe->last_seen_at)->toISOString(),
                    'is_stale' => $isStale,
                    'auth_token_last8' => $probe->auth_token_last8,
                    'created_at' => optional($probe->created_at)->toISOString(),
                    'updated_at' => optional($probe->updated_at)->toISOString(),
                ];
            })
            ->values();

        return response()->json([
            'private_probes' => $probes,
            'stale_after_minutes' => $staleAfterMinutes,
        ]);
    }

    public function store(Request $request)
    {
        if (! (bool) config('wids.private_probes.enabled', false)) {
            return response()->json(['message' => 'Private probes are disabled.'], 404);
        }

        $tenantId = (int) ($request->user()?->tenant_id ?? 0);

        if ($tenantId <= 0) {
            return response()->json(['message' => 'User has no tenant.'], 400);
        }

        $tenant = Tenant::query()->findOrFail($tenantId);
        $privateProbeService = app(PrivateProbeService::class);

        if (! $privateProbeService->isTenantTierAllowed($tenant)) {
            throw ValidationException::withMessages([
                'plan_tier' => ['Private probes are not available for current plan tier. Upgrade plan to continue.'],
            ]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'region_hint' => ['nullable', 'string', 'max:64'],
            'capabilities' => ['nullable', 'array'],
        ]);

        $created = $privateProbeService->createForTenant(
            tenant: $tenant,
            name: (string) $data['name'],
            createdByUserId: $request->user()?->id,
            regionHint: isset($data['region_hint']) ? (string) $data['region_hint'] : null,
            capabilities: is_array($data['capabilities'] ?? null) ? $data['capabilities'] : null
        );

        /** @var PrivateProbe $probe */
        $probe = $created['probe'];
        $token = (string) $created['token'];

        app(AuditLogService::class)->log(
            tenantId: $tenantId,
            actorUserId: $request->user()?->id,
            action: 'private_probe.created',
            auditableType: PrivateProbe::class,
            auditableId: $probe->id,
            data: [
                'name' => $probe->name,
                'region_hint' => $probe->region_hint,
                'auth_token_last8' => $probe->auth_token_last8,
            ],
            request: $request,
        );

        return response()->json([
            'private_probe' => [
                'id' => $probe->id,
                'name' => $probe->name,
                'region_hint' => $probe->region_hint,
                'capabilities' => is_array($probe->capabilities) ? $probe->capabilities : [],
                'is_active' => (bool) $probe->is_active,
                'auth_token_last8' => $probe->auth_token_last8,
            ],
            'auth_token' => $token,
            'message' => 'Private probe created. Save token now, it will not be shown again.',
        ], 201);
    }

    public function update(Request $request, PrivateProbe $privateProbe)
    {
        $tenantId = (int) ($request->user()?->tenant_id ?? 0);

        if ($tenantId <= 0) {
            return response()->json(['message' => 'User has no tenant.'], 400);
        }

        if ((int) $privateProbe->tenant_id !== $tenantId) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'region_hint' => ['nullable', 'string', 'max:64'],
            'capabilities' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $nextRegionHint = array_key_exists('region_hint', $data)
            ? (trim((string) $data['region_hint']) !== '' ? trim((string) $data['region_hint']) : null)
            : $privateProbe->region_hint;
        $nextIsActive = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : (bool) $privateProbe->is_active;
        $policyService = app(\App\Services\PrivateProbeFleetPolicyService::class);

        if ($nextIsActive && ! $policyService->isRegionAllowedForTenantId($tenantId, $nextRegionHint)) {
            throw ValidationException::withMessages([
                'region_hint' => ['Region is not allowed by current residency policy for your plan tier.'],
            ]);
        }

        if (! $privateProbe->is_active && $nextIsActive && ! $policyService->canActivateProbe($privateProbe)) {
            throw ValidationException::withMessages([
                'private_probes' => ['Active private probe limit reached for your plan tier.'],
            ]);
        }

        $privateProbe->forceFill([
            'name' => array_key_exists('name', $data) ? trim((string) $data['name']) : $privateProbe->name,
            'region_hint' => $nextRegionHint,
            'capabilities' => array_key_exists('capabilities', $data) && is_array($data['capabilities']) ? $data['capabilities'] : $privateProbe->capabilities,
            'is_active' => $nextIsActive,
        ])->save();

        app(AuditLogService::class)->log(
            tenantId: $tenantId,
            actorUserId: $request->user()?->id,
            action: 'private_probe.updated',
            auditableType: PrivateProbe::class,
            auditableId: $privateProbe->id,
            data: [
                'name' => $privateProbe->name,
                'region_hint' => $privateProbe->region_hint,
                'is_active' => (bool) $privateProbe->is_active,
            ],
            request: $request,
        );

        return response()->json([
            'private_probe' => [
                'id' => $privateProbe->id,
                'name' => $privateProbe->name,
                'region_hint' => $privateProbe->region_hint,
                'capabilities' => is_array($privateProbe->capabilities) ? $privateProbe->capabilities : [],
                'is_active' => (bool) $privateProbe->is_active,
                'auth_token_last8' => $privateProbe->auth_token_last8,
                'last_seen_at' => optional($privateProbe->last_seen_at)->toISOString(),
            ],
        ]);
    }

    public function rotateToken(Request $request, PrivateProbe $privateProbe)
    {
        if (! (bool) config('wids.private_probes.enabled', false)) {
            return response()->json(['message' => 'Private probes are disabled.'], 404);
        }

        $tenantId = (int) ($request->user()?->tenant_id ?? 0);

        if ($tenantId <= 0) {
            return response()->json(['message' => 'User has no tenant.'], 400);
        }

        if ((int) $privateProbe->tenant_id !== $tenantId) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $rotated = app(PrivateProbeService::class)->rotateToken($privateProbe);
        $token = (string) $rotated['token'];

        app(AuditLogService::class)->log(
            tenantId: $tenantId,
            actorUserId: $request->user()?->id,
            action: 'private_probe.token_rotated',
            auditableType: PrivateProbe::class,
            auditableId: $privateProbe->id,
            data: [
                'auth_token_last8' => $privateProbe->auth_token_last8,
            ],
            request: $request,
        );

        return response()->json([
            'private_probe' => [
                'id' => $privateProbe->id,
                'name' => $privateProbe->name,
                'auth_token_last8' => $privateProbe->auth_token_last8,
                'is_active' => (bool) $privateProbe->is_active,
            ],
            'auth_token' => $token,
            'message' => 'Token rotated. Save token now, it will not be shown again.',
        ]);
    }
}
