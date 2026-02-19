<?php

namespace App\Services;

use App\Models\TenantSsoProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OidcAuthService
{
    public function isEnabled(): bool
    {
        return (bool) config('wids.sso.oidc.enabled', false);
    }

    public function beginAuthorization(TenantSsoProvider $provider, Request $request, string $callbackUrl): \Illuminate\Http\RedirectResponse
    {
        if (! $this->isEnabled()) {
            throw new \RuntimeException('OIDC SSO is disabled.');
        }

        if (! $provider->is_enabled) {
            throw new \RuntimeException('OIDC SSO is not enabled for this workspace.');
        }

        $clientId = trim((string) ($provider->client_id ?? ''));
        $authorizationEndpoint = $this->resolveEndpoint($provider, 'authorization_endpoint', 'authorization_endpoint');

        if ($clientId === '') {
            throw new \RuntimeException('OIDC client_id is missing.');
        }

        $state = Str::random(64);
        $nonce = Str::random(64);
        $codeVerifier = $this->generateCodeVerifier();
        $codeChallenge = $this->buildCodeChallenge($codeVerifier);
        $scopes = $this->normalizeScopes((string) ($provider->scopes ?? ''));

        $request->session()->put($this->sessionKey($provider), [
            'state' => $state,
            'nonce' => $nonce,
            'code_verifier' => $codeVerifier,
            'issued_at' => now()->getTimestamp(),
        ]);

        $params = [
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $callbackUrl,
            'scope' => $scopes,
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ];

        return redirect()->away(
            $authorizationEndpoint.'?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986)
        );
    }

    /**
     * @return array{
     *   provider_subject: string,
     *   email: string,
     *   name: string,
     *   claims: array<string, mixed>
     * }
     */
    public function consumeAuthorizationCode(TenantSsoProvider $provider, Request $request, string $callbackUrl): array
    {
        if (! $this->isEnabled()) {
            throw new \RuntimeException('OIDC SSO is disabled.');
        }

        $statePayload = $request->session()->pull($this->sessionKey($provider));

        if (! is_array($statePayload)) {
            throw new \RuntimeException('OIDC login state is missing or expired.');
        }

        $expectedState = is_string($statePayload['state'] ?? null) ? (string) $statePayload['state'] : '';
        $receivedState = trim((string) $request->query('state', ''));

        if ($expectedState === '' || $receivedState === '' || ! hash_equals($expectedState, $receivedState)) {
            throw new \RuntimeException('OIDC state validation failed.');
        }

        $codeVerifier = is_string($statePayload['code_verifier'] ?? null) ? (string) $statePayload['code_verifier'] : '';
        $authorizationCode = trim((string) $request->query('code', ''));

        if ($authorizationCode === '') {
            throw new \RuntimeException('OIDC authorization code is missing.');
        }

        if ($codeVerifier === '') {
            throw new \RuntimeException('OIDC PKCE verifier is missing.');
        }

        $tokenEndpoint = $this->resolveEndpoint($provider, 'token_endpoint', 'token_endpoint');
        $userinfoEndpoint = $this->resolveEndpoint($provider, 'userinfo_endpoint', 'userinfo_endpoint');
        $clientId = trim((string) ($provider->client_id ?? ''));
        $clientSecret = trim((string) ($provider->client_secret ?? ''));

        if ($clientId === '' || $clientSecret === '') {
            throw new \RuntimeException('OIDC client credentials are incomplete.');
        }

        $timeoutSeconds = max(2, min(60, (int) config('wids.sso.oidc.http_timeout_seconds', 8)));
        $tokenResponse = Http::timeout($timeoutSeconds)
            ->asForm()
            ->post($tokenEndpoint, [
                'grant_type' => 'authorization_code',
                'code' => $authorizationCode,
                'redirect_uri' => $callbackUrl,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'code_verifier' => $codeVerifier,
            ]);

        if (! $tokenResponse->successful()) {
            throw new \RuntimeException('OIDC token exchange failed (HTTP '.$tokenResponse->status().').');
        }

        $tokenPayload = $tokenResponse->json();

        if (! is_array($tokenPayload)) {
            throw new \RuntimeException('OIDC token response is invalid.');
        }

        $accessToken = trim((string) ($tokenPayload['access_token'] ?? ''));

        if ($accessToken === '') {
            throw new \RuntimeException('OIDC access token is missing in token response.');
        }

        $userinfoResponse = Http::timeout($timeoutSeconds)
            ->acceptJson()
            ->withToken($accessToken)
            ->get($userinfoEndpoint);

        if (! $userinfoResponse->successful()) {
            throw new \RuntimeException('OIDC userinfo request failed (HTTP '.$userinfoResponse->status().').');
        }

        $claims = $userinfoResponse->json();

        if (! is_array($claims)) {
            throw new \RuntimeException('OIDC userinfo payload is invalid.');
        }

        return $this->mapClaims($provider, $claims);
    }

    /**
     * @param  array<string, mixed>  $claims
     * @return array{
     *   provider_subject: string,
     *   email: string,
     *   name: string,
     *   claims: array<string, mixed>
     * }
     */
    private function mapClaims(TenantSsoProvider $provider, array $claims): array
    {
        $emailClaim = $this->normalizeClaimKey((string) ($provider->email_claim ?? ''), 'email');
        $nameClaim = $this->normalizeClaimKey((string) ($provider->name_claim ?? ''), 'name');
        $subjectClaim = $this->normalizeClaimKey((string) ($provider->subject_claim ?? ''), 'sub');

        $providerSubject = $this->claimToString($claims[$subjectClaim] ?? null);
        $email = strtolower(trim($this->claimToString($claims[$emailClaim] ?? null)));
        $name = trim($this->claimToString($claims[$nameClaim] ?? null));

        if ($providerSubject === '') {
            throw new \RuntimeException('OIDC subject claim is missing.');
        }

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('OIDC email claim is missing or invalid.');
        }

        if ((bool) ($provider->enforce_verified_email ?? true)) {
            $emailVerified = $claims['email_verified'] ?? null;
            $isVerified = $this->toBooleanOrNull($emailVerified);

            if ($isVerified === false) {
                throw new \RuntimeException('OIDC user email is not verified.');
            }
        }

        if ($name === '') {
            $name = ucfirst((string) strstr($email, '@', true));
            $name = $name !== '' ? $name : 'User';
        }

        return [
            'provider_subject' => $providerSubject,
            'email' => $email,
            'name' => $name,
            'claims' => $claims,
        ];
    }

    private function resolveEndpoint(TenantSsoProvider $provider, string $configuredField, string $discoveryField): string
    {
        $issuerUrl = trim((string) ($provider->issuer_url ?? ''));

        if ($issuerUrl === '') {
            throw new \RuntimeException('OIDC issuer URL is missing.');
        }

        $discoveryService = app(OidcDiscoveryService::class);
        $configuredUrl = trim((string) ($provider->{$configuredField} ?? ''));

        if ($configuredUrl !== '') {
            return $discoveryService->normalizeEndpointUrl($configuredUrl, $issuerUrl);
        }

        $discovered = $discoveryService->discover($issuerUrl);
        $discoveredUrl = trim((string) ($discovered[$discoveryField] ?? ''));

        if ($discoveredUrl === '') {
            throw new \RuntimeException('OIDC discovery missing endpoint: '.$discoveryField);
        }

        return $discoveryService->normalizeEndpointUrl($discoveredUrl, $issuerUrl);
    }

    private function normalizeScopes(string $scopes): string
    {
        $scopes = trim($scopes);

        if ($scopes === '') {
            $scopes = (string) config('wids.sso.oidc.default_scopes', 'openid profile email');
        }

        $parts = preg_split('/[\s,]+/', $scopes) ?: [];
        $normalized = [];

        foreach ($parts as $part) {
            $value = trim((string) $part);

            if ($value === '') {
                continue;
            }

            $normalized[$value] = true;
        }

        if ($normalized === []) {
            $normalized['openid'] = true;
            $normalized['profile'] = true;
            $normalized['email'] = true;
        }

        if (! isset($normalized['openid'])) {
            $normalized = ['openid' => true] + $normalized;
        }

        return implode(' ', array_keys($normalized));
    }

    private function normalizeClaimKey(string $value, string $default): string
    {
        $value = trim($value);

        return $value !== '' ? $value : $default;
    }

    private function claimToString(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (is_numeric($value)) {
            return trim((string) $value);
        }

        return '';
    }

    private function toBooleanOrNull(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return ((int) $value) === 1;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));

            if ($value === 'true' || $value === '1' || $value === 'yes') {
                return true;
            }

            if ($value === 'false' || $value === '0' || $value === 'no') {
                return false;
            }
        }

        return null;
    }

    private function generateCodeVerifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
    }

    private function buildCodeChallenge(string $codeVerifier): string
    {
        $hashed = hash('sha256', $codeVerifier, true);

        return rtrim(strtr(base64_encode($hashed), '+/', '-_'), '=');
    }

    private function sessionKey(TenantSsoProvider $provider): string
    {
        return 'sso:oidc:state:tenant:'.$provider->tenant_id.':provider:'.$provider->provider;
    }
}
