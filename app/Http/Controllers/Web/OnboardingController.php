<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MonitoredUrl;
use App\Services\MonitorTemplateService;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function show(Request $request, MonitorTemplateService $monitorTemplateService)
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 400, 'User has no tenant.');

        $hasMonitors = MonitoredUrl::query()
            ->where('tenant_id', $tenantId)
            ->exists();

        return view('app.onboarding.index', [
            'hasMonitors' => $hasMonitors,
            'monitorTemplates' => $monitorTemplateService->frontendTemplates(),
        ]);
    }
}
