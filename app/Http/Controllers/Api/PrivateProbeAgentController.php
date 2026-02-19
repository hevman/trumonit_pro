<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PrivateProbe;
use Illuminate\Http\Request;

class PrivateProbeAgentController extends Controller
{
    public function heartbeat(Request $request)
    {
        /** @var PrivateProbe|null $probe */
        $probe = $request->attributes->get('privateProbe');

        if (! $probe) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $data = $request->validate([
            'version' => ['nullable', 'string', 'max:64'],
            'region' => ['nullable', 'string', 'max:64'],
            'checks_running' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'queue_ready' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:64'],
        ]);

        $meta = is_array($probe->meta) ? $probe->meta : [];
        $meta['heartbeat'] = [
            'version' => $data['version'] ?? null,
            'region' => $data['region'] ?? $probe->region_hint,
            'checks_running' => isset($data['checks_running']) ? (int) $data['checks_running'] : null,
            'queue_ready' => isset($data['queue_ready']) ? (int) $data['queue_ready'] : null,
            'tags' => isset($data['tags']) && is_array($data['tags']) ? array_values($data['tags']) : [],
            'seen_at' => now()->toISOString(),
        ];

        $probe->forceFill([
            'last_seen_at' => now(),
            'last_seen_ip' => $request->ip(),
            'last_seen_user_agent' => $request->userAgent(),
            'meta' => $meta,
        ])->save();

        return response()->json([
            'ok' => true,
            'probe' => [
                'id' => $probe->id,
                'name' => $probe->name,
                'region_hint' => $probe->region_hint,
                'last_seen_at' => optional($probe->last_seen_at)->toISOString(),
            ],
            'server_time' => now()->toISOString(),
        ]);
    }
}
