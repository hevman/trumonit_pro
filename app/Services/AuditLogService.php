<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AuditLogService
{
    /**
     * @param  array<string, mixed>|null  $data
     */
    public function log(
        int $tenantId,
        ?int $actorUserId,
        string $action,
        ?string $auditableType = null,
        ?int $auditableId = null,
        ?array $data = null,
        ?Request $request = null,
    ): void {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        $ip = null;
        $userAgent = null;

        if ($request) {
            $ip = $request->ip();
            $userAgent = $request->userAgent();
        }

        AuditLog::query()->create([
            'tenant_id' => $tenantId,
            'actor_user_id' => $actorUserId,
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'data' => $data,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'created_at' => now(),
        ]);
    }
}
