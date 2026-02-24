<?php

namespace App\Services\SeoRisk;

use App\Models\SeoIntegration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleTokenService
{
    public function exchangeAuthorizationCode(
        SeoIntegration $integration,
        string $code,
        string $redirectUri,
    ): array {
        $clientId = trim((string) ($integration->google_client_id ?? ''));
        $clientSecret = trim((string) ($integration->google_client_secret ?? ''));

        if ($clientId === '' || $clientSecret === '') {
            $clientId = trim((string) config('wids.seo_risk.google_oauth_default_client_id', ''));
            $clientSecret = trim((string) config('wids.seo_risk.google_oauth_default_client_secret', ''));
        }

        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('Google OAuth client credentials are missing.');
        }

        $response = Http::asForm()
            ->timeout(20)
            ->post((string) config('wids.seo_risk.google_oauth_token_url', 'https://oauth2.googleapis.com/token'), [
                'code' => $code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Google OAuth token exchange failed (status='.$response->status().').');
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('Google OAuth token exchange returned invalid payload.');
        }

        return $payload;
    }

    public function accessTokenForIntegration(SeoIntegration $integration): string
    {
        $accessToken = trim((string) ($integration->access_token ?? ''));
        $expiresAt = $integration->access_token_expires_at;

        if (
            $accessToken !== ''
            && $expiresAt instanceof Carbon
            && $expiresAt->greaterThan(now()->addMinutes(2))
        ) {
            return $accessToken;
        }

        return $this->refreshAccessToken($integration);
    }

    public function refreshAccessToken(SeoIntegration $integration): string
    {
        $refreshToken = trim((string) ($integration->refresh_token ?? ''));
        $clientId = trim((string) ($integration->google_client_id ?? ''));
        $clientSecret = trim((string) ($integration->google_client_secret ?? ''));

        if ($clientId === '' || $clientSecret === '') {
            $clientId = trim((string) config('wids.seo_risk.google_oauth_default_client_id', ''));
            $clientSecret = trim((string) config('wids.seo_risk.google_oauth_default_client_secret', ''));
        }

        if ($refreshToken === '') {
            throw new RuntimeException('Google refresh token is missing.');
        }

        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('Google OAuth client credentials are missing.');
        }

        $response = Http::asForm()
            ->timeout(20)
            ->post((string) config('wids.seo_risk.google_oauth_token_url', 'https://oauth2.googleapis.com/token'), [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Google token refresh failed (status='.$response->status().').');
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('Google token refresh returned invalid payload.');
        }

        $accessToken = trim((string) ($payload['access_token'] ?? ''));
        if ($accessToken === '') {
            throw new RuntimeException('Google token refresh returned empty access token.');
        }

        $expiresIn = max(60, (int) ($payload['expires_in'] ?? 3600));
        $integration->access_token = $accessToken;
        $integration->access_token_expires_at = now()->addSeconds($expiresIn);
        $integration->save();

        return $accessToken;
    }
}
