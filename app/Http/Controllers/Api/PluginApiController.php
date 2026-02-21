<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class PluginApiController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'client_id' => ['nullable', 'string', 'max:191'],
        ]);

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower((string) $data['email'])])
            ->where('is_active', true)
            ->first();

        if (! $user || ! Hash::check((string) $data['password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        $clientId = isset($data['client_id']) ? trim((string) $data['client_id']) : '';
        $token = $this->issuePluginToken($user, $clientId);

        return response()->json([
            'ok' => true,
            'token' => $token['plain_text_token'],
            'token_name' => $token['name'],
            'token_scopes' => $token['abilities'],
            'client_id' => $token['client_id'],
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'tenant' => $user->tenant,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $request->attributes->get('plugin_api_token');
        $abilities = [];

        if ($token instanceof PersonalAccessToken && is_array($token->abilities)) {
            $abilities = $this->normalizeScopes($token->abilities);
        }

        return response()->json([
            'ok' => true,
            'user' => [
                'id' => $user?->id,
                'name' => $user?->name,
                'email' => $user?->email,
                'role' => $user?->role,
            ],
            'tenant' => $user?->tenant,
            'token' => [
                'abilities' => $abilities,
            ],
            'capabilities' => [
                'can_run_scan' => $this->canRunScan($user, $abilities),
                'scan_requires_owner' => (bool) config('wids.api.plugin.scan_requires_owner', true),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->attributes->get('plugin_api_token');
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json([
            'ok' => true,
        ]);
    }

    public function monitors(Request $request): JsonResponse
    {
        $payload = app(MonitoredUrlController::class)->index($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        return response()->json($payload);
    }

    public function incidents(Request $request): JsonResponse
    {
        $payload = app(IncidentController::class)->index($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        return response()->json($payload);
    }

    public function runScan(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $token = $request->attributes->get('plugin_api_token');
        $abilities = [];

        if ($token instanceof PersonalAccessToken && is_array($token->abilities)) {
            $abilities = $this->normalizeScopes($token->abilities);
        }

        if (! $this->canRunScan($user, $abilities)) {
            return response()->json([
                'ok' => false,
                'error' => 'owner_required',
                'message' => 'Run scan requires an owner API token.',
            ], 403);
        }

        /** @var \Illuminate\Http\JsonResponse $response */
        $response = app(MonitoredUrlController::class)->scan($request, $id);

        return $response;
    }

    private function canRunScan(?User $user, array $abilities): bool
    {
        $requiresOwner = (bool) config('wids.api.plugin.scan_requires_owner', true);
        if (! $requiresOwner) {
            return true;
        }

        $role = $user?->role;
        if ($role !== null && $role !== 'owner') {
            return false;
        }

        if ($abilities === [] || in_array('*', $abilities, true)) {
            return true;
        }

        return in_array('plugin.write', $abilities, true) || in_array('wids.write', $abilities, true);
    }

    /**
     * @return array{plain_text_token:string,name:string,abilities:list<string>}
     */
    private function issuePluginToken(User $user, string $clientId = ''): array
    {
        $defaultAbilities = ['plugin.read', 'plugin.write'];
        $abilities = config('wids.api.plugin.token_abilities', $defaultAbilities);
        $abilities = is_array($abilities) ? $this->normalizeScopes($abilities) : $defaultAbilities;

        if ($abilities === []) {
            $abilities = $defaultAbilities;
        }

        $ttlMinutes = (int) config('wids.api.plugin.token_ttl_minutes', config('wids.api.tokens.ttl_minutes', 43_200));
        $ttlMinutes = min(525_600, max(0, $ttlMinutes));
        $expiresAt = $ttlMinutes > 0 ? now()->addMinutes($ttlMinutes) : null;

        $clientId = trim($clientId);
        $name = 'plugin-api';
        if ($clientId !== '') {
            $name .= ':' . substr(hash('sha256', mb_strtolower($clientId)), 0, 16);
        }

        // Rotate token per plugin client to avoid token sprawl in repeated logins.
        $user->tokens()->where('name', $name)->delete();
        $plainTextToken = $user->createToken($name, $abilities, $expiresAt)->plainTextToken;

        return [
            'plain_text_token' => $plainTextToken,
            'name' => $name,
            'abilities' => $abilities,
            'client_id' => $clientId,
        ];
    }

    /**
     * @param  array<int, mixed>  $abilities
     * @return list<string>
     */
    private function normalizeScopes(array $abilities): array
    {
        $scopes = [];

        foreach ($abilities as $ability) {
            if (! is_string($ability) || trim($ability) === '') {
                continue;
            }

            $scopes[] = trim($ability);
        }

        return array_values(array_unique($scopes));
    }
}
