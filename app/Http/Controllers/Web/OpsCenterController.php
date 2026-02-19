<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\AuditLogService;
use App\Services\OpsDrillArtifactService;
use App\Services\OpsDrillService;
use App\Services\OpsTuneEnvFileService;
use App\Services\OpsTuningPlanService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OpsCenterController extends Controller
{
    public function index(Request $request)
    {
        $tenant = $request->user()?->tenant;
        abort_if(! $tenant, 400, 'User has no tenant.');

        $filters = $request->validate([
            'ops_window_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'ops_current_workers' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);

        $windowHours = is_numeric($filters['ops_window_hours'] ?? null)
            ? (int) $filters['ops_window_hours']
            : null;
        $currentWorkers = is_numeric($filters['ops_current_workers'] ?? null)
            ? max(0, (int) $filters['ops_current_workers'])
            : null;

        $drillReport = app(OpsDrillService::class)->analyze($windowHours);
        $tuneReport = app(OpsTuningPlanService::class)->build($windowHours, $currentWorkers);
        $artifactRows = app(OpsDrillArtifactService::class)->list(limit: 20);
        $artifactTrends = $this->buildArtifactTrends($artifactRows);
        $backupRows = app(OpsTuneEnvFileService::class)->listBackups(limit: 20);
        $ownership = $this->resolveOpsOwnership();
        $escalationChannels = $this->resolveOpsEscalationChannels();
        $escalationTargets = $this->resolveOpsEscalationTargets();
        $opsReadiness = $this->buildOpsReadinessChecklist(
            $ownership,
            $escalationChannels,
            $escalationTargets,
            $artifactRows,
        );

        return view('app.ops.index', [
            'tenant' => $tenant,
            'drillReport' => $drillReport,
            'tuneReport' => $tuneReport,
            'artifactRows' => $artifactRows,
            'artifactTrends' => $artifactTrends,
            'backupRows' => $backupRows,
            'opsWindowHours' => $windowHours,
            'opsCurrentWorkers' => $currentWorkers,
            'opsTuneEnvFile' => app(OpsTuneEnvFileService::class)->resolveConfiguredEnvFilePath(),
            'opsOwnership' => $ownership,
            'opsEscalationChannels' => $escalationChannels,
            'opsEscalationTargets' => $escalationTargets,
            'opsReadiness' => $opsReadiness,
        ]);
    }

    public function runDrill(Request $request)
    {
        $tenant = $request->user()?->tenant;
        abort_if(! $tenant, 400, 'User has no tenant.');

        $data = $request->validate([
            'ops_window_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'ops_current_workers' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);

        $windowHours = is_numeric($data['ops_window_hours'] ?? null)
            ? (int) $data['ops_window_hours']
            : null;
        $currentWorkers = is_numeric($data['ops_current_workers'] ?? null)
            ? max(0, (int) $data['ops_current_workers'])
            : null;

        try {
            $report = app(OpsDrillService::class)->analyze($windowHours);
            $stored = app(OpsDrillArtifactService::class)->store($report);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'ops_window_hours' => [$e->getMessage()],
            ]);
        }

        app(AuditLogService::class)->log(
            tenantId: (int) $tenant->id,
            actorUserId: (int) ($request->user()?->id ?? 0),
            action: 'tenant.ops_drill_manual_run',
            auditableType: Tenant::class,
            auditableId: (int) $tenant->id,
            data: [
                'ops_window_hours' => $windowHours,
                'artifact' => $stored,
            ],
            request: $request,
        );

        return redirect()
            ->route('ops-center.index', $this->buildQuery($windowHours, $currentWorkers))
            ->with('status', 'Ops drill run completed and artifact saved.');
    }

    public function showArtifact(Request $request, string $filename)
    {
        $tenant = $request->user()?->tenant;
        abort_if(! $tenant, 400, 'User has no tenant.');

        $filename = trim($filename);

        if ($filename === '' || str_contains($filename, '/') || str_contains($filename, '\\')) {
            abort(404);
        }

        $relativePath = 'ops-drills/'.$filename;
        $payload = app(OpsDrillArtifactService::class)->read($relativePath);

        abort_if(! is_array($payload), 404);

        return response()->json($payload);
    }

    public function applyTune(Request $request)
    {
        $tenant = $request->user()?->tenant;
        abort_if(! $tenant, 400, 'User has no tenant.');

        $data = $request->validate([
            'ops_window_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'ops_current_workers' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);

        $windowHours = is_numeric($data['ops_window_hours'] ?? null)
            ? (int) $data['ops_window_hours']
            : null;
        $currentWorkers = is_numeric($data['ops_current_workers'] ?? null)
            ? max(0, (int) $data['ops_current_workers'])
            : null;

        $report = app(OpsTuningPlanService::class)->build($windowHours, $currentWorkers);
        $envOverrides = is_array($report['env_overrides'] ?? null) ? $report['env_overrides'] : [];

        if ($envOverrides === []) {
            return redirect()
                ->route('ops-center.index', $this->buildQuery($windowHours, $currentWorkers))
                ->with('status', 'No ops tuning overrides to apply for current profile.');
        }

        $envService = app(OpsTuneEnvFileService::class);

        try {
            $applyResult = $envService->applyEnvOverrides($envService->resolveConfiguredEnvFilePath(), $envOverrides);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'ops_window_hours' => [$e->getMessage()],
            ]);
        }

        app(AuditLogService::class)->log(
            tenantId: (int) $tenant->id,
            actorUserId: (int) ($request->user()?->id ?? 0),
            action: 'tenant.ops_tune_applied',
            auditableType: Tenant::class,
            auditableId: (int) $tenant->id,
            data: [
                'ops_window_hours' => $windowHours,
                'ops_current_workers' => $currentWorkers,
                'scale_action' => (string) ($report['scale_action'] ?? ''),
                'suggested_scale_command' => (string) ($report['suggested_scale_command'] ?? ''),
                'apply_result' => $applyResult,
            ],
            request: $request,
        );

        return redirect()
            ->route('ops-center.index', $this->buildQuery($windowHours, $currentWorkers))
            ->with('status', 'Ops tuning overrides applied to configured env file.')
            ->with('ops_tune_apply_result', $applyResult);
    }

    public function rollbackTune(Request $request)
    {
        $tenant = $request->user()?->tenant;
        abort_if(! $tenant, 400, 'User has no tenant.');

        $data = $request->validate([
            'backup_id' => ['required', 'string', 'max:255'],
            'ops_window_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'ops_current_workers' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);

        $windowHours = is_numeric($data['ops_window_hours'] ?? null)
            ? (int) $data['ops_window_hours']
            : null;
        $currentWorkers = is_numeric($data['ops_current_workers'] ?? null)
            ? max(0, (int) $data['ops_current_workers'])
            : null;

        $envService = app(OpsTuneEnvFileService::class);
        $backupPath = $envService->resolveBackupPathById((string) $data['backup_id']);

        if (! is_string($backupPath) || $backupPath === '') {
            throw ValidationException::withMessages([
                'backup_id' => ['Selected backup no longer exists.'],
            ]);
        }

        try {
            $rollbackResult = $envService->rollbackEnvFile($envService->resolveConfiguredEnvFilePath(), $backupPath);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'backup_id' => [$e->getMessage()],
            ]);
        }

        app(AuditLogService::class)->log(
            tenantId: (int) $tenant->id,
            actorUserId: (int) ($request->user()?->id ?? 0),
            action: 'tenant.ops_tune_rollback',
            auditableType: Tenant::class,
            auditableId: (int) $tenant->id,
            data: [
                'ops_window_hours' => $windowHours,
                'ops_current_workers' => $currentWorkers,
                'rollback_result' => $rollbackResult,
            ],
            request: $request,
        );

        return redirect()
            ->route('ops-center.index', $this->buildQuery($windowHours, $currentWorkers))
            ->with('status', 'Ops tuning rollback completed.')
            ->with('ops_tune_rollback_result', $rollbackResult);
    }

    /**
     * @return array<string, int>
     */
    private function buildQuery(?int $windowHours, ?int $currentWorkers): array
    {
        $query = [];

        if (is_int($windowHours) && $windowHours > 0) {
            $query['ops_window_hours'] = $windowHours;
        }

        if (is_int($currentWorkers) && $currentWorkers >= 0) {
            $query['ops_current_workers'] = $currentWorkers;
        }

        return $query;
    }

    /**
     * @return array{
     *   service_owner: string,
     *   primary_on_call: string,
     *   secondary_on_call: string,
     *   incident_commander: string
     * }
     */
    private function resolveOpsOwnership(): array
    {
        $raw = config('ops.ownership', []);
        $raw = is_array($raw) ? $raw : [];

        return [
            'service_owner' => $this->normalizeContact($raw['service_owner'] ?? null),
            'primary_on_call' => $this->normalizeContact($raw['primary_on_call'] ?? null),
            'secondary_on_call' => $this->normalizeContact($raw['secondary_on_call'] ?? null),
            'incident_commander' => $this->normalizeContact($raw['incident_commander'] ?? null),
        ];
    }

    /**
     * @return array{sev1: string, sev2: string, status_updates: string}
     */
    private function resolveOpsEscalationChannels(): array
    {
        $raw = config('ops.escalation.channels', []);
        $raw = is_array($raw) ? $raw : [];

        return [
            'sev1' => $this->normalizeContact($raw['sev1'] ?? null),
            'sev2' => $this->normalizeContact($raw['sev2'] ?? null),
            'status_updates' => $this->normalizeContact($raw['status_updates'] ?? null),
        ];
    }

    /**
     * @return array{customer_updates: string, internal_updates: string}
     */
    private function resolveOpsEscalationTargets(): array
    {
        $raw = config('ops.escalation.targets', []);
        $raw = is_array($raw) ? $raw : [];

        return [
            'customer_updates' => $this->normalizeContact($raw['customer_updates'] ?? null),
            'internal_updates' => $this->normalizeContact($raw['internal_updates'] ?? null),
        ];
    }

    /**
     * @param  array{service_owner: string, primary_on_call: string, secondary_on_call: string, incident_commander: string}  $ownership
     * @param  array{sev1: string, sev2: string, status_updates: string}  $channels
     * @param  array{customer_updates: string, internal_updates: string}  $targets
     * @param  list<array<string, mixed>>  $artifactRows
     * @return array{
     *   contacts_configured: bool,
     *   channels_configured: bool,
     *   targets_configured: bool,
     *   monthly_drill_ready: bool,
     *   error_budget_policy_present: bool,
     *   ready_for_go_no_go: bool
     * }
     */
    private function buildOpsReadinessChecklist(
        array $ownership,
        array $channels,
        array $targets,
        array $artifactRows,
    ): array {
        $contactsConfigured = $this->allConfigured($ownership);
        $channelsConfigured = $this->allConfigured($channels);
        $targetsConfigured = $this->allConfigured($targets);
        $monthlyDrillReady = $artifactRows !== [];
        $errorBudgetPolicyPresent = is_file(base_path('docs/observability/ERROR_BUDGET_ONCALL_ESCALATION.md'));

        return [
            'contacts_configured' => $contactsConfigured,
            'channels_configured' => $channelsConfigured,
            'targets_configured' => $targetsConfigured,
            'monthly_drill_ready' => $monthlyDrillReady,
            'error_budget_policy_present' => $errorBudgetPolicyPresent,
            'ready_for_go_no_go' => $contactsConfigured
                && $channelsConfigured
                && $targetsConfigured
                && $monthlyDrillReady
                && $errorBudgetPolicyPresent,
        ];
    }

    /**
     * @param  array<string, string>  $values
     */
    private function allConfigured(array $values): bool
    {
        foreach ($values as $value) {
            if (! $this->isConfiguredContact($value)) {
                return false;
            }
        }

        return true;
    }

    private function isConfiguredContact(string $value): bool
    {
        if (trim($value) === '') {
            return false;
        }

        return ! in_array(strtolower(trim($value)), [
            'tbd',
            'todo',
            'platform owner',
            'security/infra owner',
            'product/engineering owner on duty',
        ], true);
    }

    private function normalizeContact(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return trim($value);
    }

    /**
     * @param  list<array{
     *   filename: string,
     *   relative_path: string,
     *   storage_path: string,
     *   size_bytes: int,
     *   modified_at_iso: string
     * }>  $artifactRows
     * @return array{
     *   labels: list<string>,
     *   drilldown_urls: list<string>,
     *   queue_lag_seconds: list<float>,
     *   queue_wait_ms: list<float>,
     *   status_scores: list<int>,
     *   status_labels: list<string>,
     *   status_counts: array{pass: int, warn: int, fail: int, other: int},
     *   has_data: bool
     * }
     */
    private function buildArtifactTrends(array $artifactRows): array
    {
        $rows = array_reverse(array_slice($artifactRows, 0, 12));
        $artifactService = app(OpsDrillArtifactService::class);

        $labels = [];
        $drilldownUrls = [];
        $queueLagSeries = [];
        $queueWaitSeries = [];
        $statusScores = [];
        $statusLabels = [];
        $statusCounts = [
            'pass' => 0,
            'warn' => 0,
            'fail' => 0,
            'other' => 0,
        ];

        foreach ($rows as $index => $row) {
            $filename = (string) ($row['filename'] ?? '');
            $relativePath = (string) ($row['relative_path'] ?? '');
            $payload = $artifactService->read($relativePath);
            $status = strtolower((string) data_get($payload, 'overall_status', 'unknown'));

            $statusScore = match ($status) {
                'fail' => 3,
                'warn' => 2,
                'pass' => 1,
                default => 0,
            };

            if (isset($statusCounts[$status])) {
                $statusCounts[$status]++;
            } else {
                $statusCounts['other']++;
            }

            $label = (string) ($row['modified_at_iso'] ?? '');
            $label = trim($label);

            if ($label !== '') {
                try {
                    $label = \Illuminate\Support\Carbon::parse($label)->format('m-d H:i');
                } catch (\Throwable) {
                    $label = '';
                }
            }

            if ($label === '') {
                $label = $filename !== '' ? $filename : '#'.($index + 1);
            }

            $queueLag = data_get($payload, 'checks.queue_lag.data.scan_lag_p95_seconds');
            $queueWait = data_get($payload, 'checks.queue_wait.data.queue_wait_p95_ms');

            $labels[] = $label;
            $drilldownUrls[] = $filename !== '' ? route('ops-center.drills.show', ['filename' => $filename]) : '';
            $queueLagSeries[] = is_numeric($queueLag) ? round((float) $queueLag, 1) : 0.0;
            $queueWaitSeries[] = is_numeric($queueWait) ? round((float) $queueWait, 1) : 0.0;
            $statusScores[] = $statusScore;
            $statusLabels[] = strtoupper($status);
        }

        return [
            'labels' => $labels,
            'drilldown_urls' => $drilldownUrls,
            'queue_lag_seconds' => $queueLagSeries,
            'queue_wait_ms' => $queueWaitSeries,
            'status_scores' => $statusScores,
            'status_labels' => $statusLabels,
            'status_counts' => $statusCounts,
            'has_data' => $labels !== [],
        ];
    }
}
