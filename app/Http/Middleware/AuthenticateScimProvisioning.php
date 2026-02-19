<?php

namespace App\Http\Middleware;

use App\Services\ScimProvisioningService;
use Closure;
use Illuminate\Http\Request;

class AuthenticateScimProvisioning
{
    public function handle(Request $request, Closure $next)
    {
        $service = app(ScimProvisioningService::class);

        if (! $service->isEnabled()) {
            return response()->json([
                'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
                'status' => '404',
                'detail' => 'SCIM API is disabled.',
            ], 404, ['Content-Type' => 'application/scim+json']);
        }

        $token = $request->bearerToken();

        if (! is_string($token) || trim($token) === '') {
            return response()->json([
                'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
                'status' => '401',
                'detail' => 'Missing SCIM bearer token.',
            ], 401, ['Content-Type' => 'application/scim+json']);
        }

        $provider = $service->findEnabledProviderByToken($token);

        if (! $provider) {
            return response()->json([
                'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
                'status' => '401',
                'detail' => 'Invalid SCIM bearer token.',
            ], 401, ['Content-Type' => 'application/scim+json']);
        }

        $request->attributes->set('scimProvider', $provider);
        $request->attributes->set('scimTenantId', (int) $provider->tenant_id);

        return $next($request);
    }
}
