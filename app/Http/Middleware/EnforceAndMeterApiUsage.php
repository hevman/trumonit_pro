<?php

namespace App\Http\Middleware;

use App\Services\BillingUsageService;
use Closure;
use Illuminate\Http\Request;

class EnforceAndMeterApiUsage
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $tenantId = (int) ($user?->tenant_id ?? 0);

        if ($tenantId <= 0) {
            return $next($request);
        }

        $billingUsageService = app(BillingUsageService::class);
        $metricKey = trim((string) config('wids.billing.enforced_metric_key', BillingUsageService::METRIC_API_REQUESTS_TOTAL));
        $enforcementEnabled = (bool) config('wids.billing.enforcement_enabled', false);

        if ($enforcementEnabled && $metricKey !== '' && $billingUsageService->wouldExceedMonthlyCap($tenantId, $metricKey, 1)) {
            return response()->json([
                'message' => 'Monthly usage limit reached for current plan. Upgrade plan to continue.',
                'metric' => $metricKey,
            ], 429);
        }

        $response = $next($request);
        $statusCode = method_exists($response, 'getStatusCode') ? (int) $response->getStatusCode() : 200;

        $billingUsageService->recordApiRequest(
            tenantId: $tenantId,
            method: (string) $request->method(),
            statusCode: $statusCode,
        );

        return $response;
    }
}
