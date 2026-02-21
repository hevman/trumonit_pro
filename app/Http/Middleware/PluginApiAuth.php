<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class PluginApiAuth
{
    /**
     * @param  array<int, string>  $requiredScopes
     */
    public function handle(Request $request, Closure $next, string ...$requiredScopes): Response
    {
        $token = $this->extractToken($request);

        if ($token === '') {
            return response()->json([
                'ok' => false,
                'error' => 'missing_api_key',
            ], 401);
        }

        $accessToken = PersonalAccessToken::findToken($token);
        if (! $accessToken) {
            return response()->json([
                'ok' => false,
                'error' => 'invalid_api_key',
            ], 401);
        }

        if ($accessToken->expires_at !== null && $accessToken->expires_at->isPast()) {
            return response()->json([
                'ok' => false,
                'error' => 'api_key_expired',
            ], 401);
        }

        $tokenable = $accessToken->tokenable;
        if (! $tokenable instanceof User || ! $tokenable->is_active) {
            return response()->json([
                'ok' => false,
                'error' => 'invalid_api_key',
            ], 401);
        }

        $abilities = is_array($accessToken->abilities) ? $accessToken->abilities : [];
        if (! $this->hasAllScopes($abilities, $requiredScopes)) {
            return response()->json([
                'ok' => false,
                'error' => 'insufficient_scope',
                'required_scopes' => array_values($requiredScopes),
            ], 403);
        }

        $accessToken->forceFill(['last_used_at' => now()])->save();

        $request->setUserResolver(static fn (): User => $tokenable);
        $request->attributes->set('plugin_api_token', $accessToken);

        return $next($request);
    }

    private function extractToken(Request $request): string
    {
        $token = trim((string) $request->header('X-Api-Key', ''));
        if ($token !== '') {
            return $token;
        }

        $auth = (string) $request->header('Authorization', '');
        if (preg_match('/^Bearer\s+(.+)$/i', $auth, $match) === 1) {
            return trim((string) ($match[1] ?? ''));
        }

        return '';
    }

    /**
     * @param  array<int, mixed>  $abilities
     * @param  array<int, string>  $requiredScopes
     */
    private function hasAllScopes(array $abilities, array $requiredScopes): bool
    {
        $tokenScopes = $this->normalizeScopes($abilities);

        if ($tokenScopes === []) {
            return (bool) config('wids.api.plugin.allow_empty_scopes', false);
        }

        if (in_array('*', $tokenScopes, true)) {
            return true;
        }

        foreach ($requiredScopes as $scope) {
            $scope = trim((string) $scope);
            if ($scope === '') {
                continue;
            }

            $matched = false;
            foreach ($this->equivalentScopesFor($scope) as $candidate) {
                if (in_array($candidate, $tokenScopes, true)) {
                    $matched = true;
                    break;
                }
            }

            if (! $matched) {
                return false;
            }
        }

        return true;
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

    /**
     * @return list<string>
     */
    private function equivalentScopesFor(string $scope): array
    {
        $scope = trim($scope);
        if ($scope === '') {
            return [];
        }

        $allowLegacy = (bool) config('wids.api.plugin.accept_legacy_wids_scopes', false);
        $equivalents = [$scope];

        if ($allowLegacy) {
            if ($scope === 'plugin.read') {
                $equivalents[] = 'wids.read';
            } elseif ($scope === 'plugin.write') {
                $equivalents[] = 'wids.write';
            } elseif ($scope === 'wids.read') {
                $equivalents[] = 'plugin.read';
            } elseif ($scope === 'wids.write') {
                $equivalents[] = 'plugin.write';
            }
        }

        return array_values(array_unique($equivalents));
    }
}
