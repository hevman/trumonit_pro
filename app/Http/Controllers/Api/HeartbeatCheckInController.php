<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ScanMonitoredUrlJob;
use App\Models\MonitoredUrl;
use App\Services\ScanQueueRoutingService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HeartbeatCheckInController extends Controller
{
    public function __invoke(Request $request, string $token)
    {
        $token = trim($token);

        if ($token === '' || ! preg_match('/\A[a-zA-Z0-9]{24,128}\z/', $token)) {
            return response()->json(['message' => 'Invalid heartbeat token.'], 404);
        }

        /** @var MonitoredUrl|null $monitoredUrl */
        $monitoredUrl = MonitoredUrl::query()
            ->where('is_active', true)
            ->where('scan_mode', 'heartbeat')
            ->where('content_rules->heartbeat->token', $token)
            ->first();

        if (! $monitoredUrl) {
            return response()->json(['message' => 'Heartbeat monitor not found.'], 404);
        }

        $contentRules = is_array($monitoredUrl->content_rules) ? $monitoredUrl->content_rules : [];
        $heartbeat = is_array($contentRules['heartbeat'] ?? null) ? $contentRules['heartbeat'] : [];

        $heartbeat['token'] = $token;
        $heartbeat['last_check_in_at'] = now()->toISOString();
        $heartbeat['last_check_in_ip'] = (string) $request->ip();
        $heartbeat['last_check_in_user_agent'] = Str::limit((string) $request->userAgent(), 255, '');
        $heartbeat['last_check_in_method'] = strtoupper((string) $request->method());

        $contentRules['heartbeat'] = $heartbeat;

        $monitoredUrl->forceFill([
            'content_rules' => $contentRules,
            'next_scan_at' => now(),
        ])->save();

        $queueName = app(ScanQueueRoutingService::class)->queueForReason(
            $monitoredUrl,
            ScanQueueRoutingService::REASON_API
        );

        ScanMonitoredUrlJob::dispatch($monitoredUrl->id)
            ->onQueue($queueName);

        return response()->json([
            'ok' => true,
            'monitor_id' => $monitoredUrl->id,
            'checked_in_at' => $heartbeat['last_check_in_at'],
        ]);
    }
}
