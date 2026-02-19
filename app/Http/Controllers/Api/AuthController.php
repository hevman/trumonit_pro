<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'tenant_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'max:255'],
        ]);

        $baseSlug = Str::slug($data['tenant_name']);
        $slug = $baseSlug !== '' ? $baseSlug : Str::random(12);

        while (Tenant::query()->where('slug', $slug)->exists()) {
            $slug = ($baseSlug !== '' ? $baseSlug.'-' : '').Str::random(6);
        }

        $tenant = Tenant::query()->create([
            'name' => $data['tenant_name'],
            'slug' => $slug,
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $token = $this->issueApiToken($user);

        return response()->json([
            'token' => $token,
            'user' => $user,
            'tenant' => $tenant,
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower((string) $data['email'])])
            ->where('is_active', true)
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        $token = $this->issueApiToken($user);

        return response()->json([
            'token' => $token,
            'user' => $user,
            'tenant' => $user->tenant,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->noContent();
    }

    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => $user,
            'tenant' => $user?->tenant,
        ]);
    }

    private function issueApiToken(User $user): string
    {
        $defaultAbilities = ['wids.read', 'wids.write'];
        $abilities = config('wids.api.tokens.abilities', $defaultAbilities);
        $abilities = is_array($abilities) ? array_values(array_filter(array_map('strval', $abilities))) : $defaultAbilities;

        if ($abilities === []) {
            $abilities = $defaultAbilities;
        }

        $ttlMinutes = (int) config('wids.api.tokens.ttl_minutes', 43_200);
        $ttlMinutes = min(525_600, max(0, $ttlMinutes));
        $expiresAt = $ttlMinutes > 0 ? now()->addMinutes($ttlMinutes) : null;

        return $user->createToken('api', $abilities, $expiresAt)->plainTextToken;
    }
}
