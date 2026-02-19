<?php

namespace App\Http\Middleware;

use App\Services\PrivateProbeService;
use Closure;
use Illuminate\Http\Request;

class AuthenticatePrivateProbe
{
    public function handle(Request $request, Closure $next)
    {
        if (! (bool) config('wids.private_probes.enabled', false)) {
            return response()->json(['message' => 'Private probes are disabled.'], 404);
        }

        $token = $request->bearerToken();

        if (! is_string($token) || trim($token) === '') {
            return response()->json(['message' => 'Missing probe token.'], 401);
        }

        $probe = app(PrivateProbeService::class)->findActiveByToken($token);

        if (! $probe) {
            return response()->json(['message' => 'Invalid probe token.'], 401);
        }

        $request->attributes->set('privateProbe', $probe);

        return $next($request);
    }
}
