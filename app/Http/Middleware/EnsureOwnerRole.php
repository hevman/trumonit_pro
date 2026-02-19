<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureOwnerRole
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Backward compatible: before the `users.role` migration runs, `$user->role` will be null.
        $role = $user?->role;

        if (! $user || ($role !== null && $role !== 'owner')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }

            abort(403);
        }

        return $next($request);
    }
}
