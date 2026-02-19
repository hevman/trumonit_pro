<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceWindow;
use App\Models\MonitoredUrl;
use App\Models\Tag;
use App\Services\AuditLogService;
use App\Services\TagService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MaintenanceWindowController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 400, 'User has no tenant.');

        $now = now();

        $windows = MaintenanceWindow::query()
            ->where('tenant_id', $tenantId)
            ->with(['monitoredUrl'])
            ->orderByRaw("CASE WHEN starts_at <= ? AND ends_at >= ? THEN 0 WHEN starts_at > ? THEN 1 ELSE 2 END", [$now, $now, $now])
            ->orderBy('starts_at')
            ->paginate(50);

        $monitoredUrls = MonitoredUrl::query()
            ->where('tenant_id', $tenantId)
            ->orderByRaw("COALESCE(NULLIF(display_name, ''), url) ASC")
            ->limit(1000)
            ->get(['id', 'display_name', 'url']);

        $tags = Tag::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->limit(500)
            ->pluck('name');

        return view('app.maintenance-windows.index', [
            'windows' => $windows,
            'monitoredUrls' => $monitoredUrls,
            'tags' => $tags,
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 400, 'User has no tenant.');

        $data = $request->validate([
            'scope' => ['required', 'string', Rule::in(['all', 'monitor', 'tag'])],
            'monitored_url_id' => ['nullable', 'integer', 'min:1'],
            'tag' => ['nullable', 'string', 'max:64'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $scope = (string) $data['scope'];

        $monitoredUrlId = null;
        $tag = null;

        if ($scope === 'monitor') {
            $monitoredUrlId = isset($data['monitored_url_id']) ? (int) $data['monitored_url_id'] : null;

            if (! is_int($monitoredUrlId) || $monitoredUrlId < 1) {
                throw ValidationException::withMessages([
                    'monitored_url_id' => ['Monitor is required for this scope.'],
                ]);
            }

            $exists = MonitoredUrl::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $monitoredUrlId)
                ->exists();

            if (! $exists) {
                throw ValidationException::withMessages([
                    'monitored_url_id' => ['Monitor not found.'],
                ]);
            }
        }

        if ($scope === 'tag') {
            $tagInput = is_string($data['tag'] ?? null) ? trim((string) $data['tag']) : '';
            $tag = app(TagService::class)->normalize($tagInput);

            if ($tag === null) {
                throw ValidationException::withMessages([
                    'tag' => ['Invalid tag. Use a-z0-9:_- (max 32 chars).'],
                ]);
            }
        }

        $startsAt = Carbon::parse((string) $data['starts_at']);
        $endsAt = Carbon::parse((string) $data['ends_at']);

        $window = MaintenanceWindow::query()->create([
            'tenant_id' => $tenantId,
            'created_by_user_id' => $request->user()?->id,
            'monitored_url_id' => $monitoredUrlId,
            'tag' => $tag,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'note' => $data['note'] ?? null,
        ]);

        app(AuditLogService::class)->log(
            tenantId: $tenantId,
            actorUserId: $request->user()?->id,
            action: 'maintenance_window.created',
            auditableType: MaintenanceWindow::class,
            auditableId: $window->id,
            data: [
                'scope' => $scope,
                'monitored_url_id' => $monitoredUrlId,
                'tag' => $tag,
                'starts_at' => $startsAt->toISOString(),
                'ends_at' => $endsAt->toISOString(),
                'note' => $window->note,
            ],
            request: $request,
        );

        return redirect()
            ->route('maintenance-windows.index')
            ->with('status', 'Maintenance window created.');
    }

    public function destroy(Request $request, MaintenanceWindow $maintenanceWindow)
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 400, 'User has no tenant.');
        abort_if($maintenanceWindow->tenant_id !== $tenantId, 404);

        $auditData = [
            'monitored_url_id' => $maintenanceWindow->monitored_url_id,
            'tag' => $maintenanceWindow->tag,
            'starts_at' => $maintenanceWindow->starts_at?->toISOString(),
            'ends_at' => $maintenanceWindow->ends_at?->toISOString(),
            'note' => $maintenanceWindow->note,
        ];

        $maintenanceWindow->delete();

        app(AuditLogService::class)->log(
            tenantId: $tenantId,
            actorUserId: $request->user()?->id,
            action: 'maintenance_window.deleted',
            auditableType: MaintenanceWindow::class,
            auditableId: $maintenanceWindow->id,
            data: $auditData,
            request: $request,
        );

        return redirect()
            ->route('maintenance-windows.index')
            ->with('status', 'Maintenance window deleted.');
    }
}

