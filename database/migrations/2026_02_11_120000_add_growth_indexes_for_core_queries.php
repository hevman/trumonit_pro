<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitored_urls', function (Blueprint $table) {
            // Accelerates scheduler selection of due active monitors.
            $table->index(['is_active', 'next_scan_at', 'id'], 'monitored_urls_active_next_scan_id_idx');
        });

        Schema::table('incidents', function (Blueprint $table) {
            // Speeds up incidents list when filtering by status.
            $table->index(['tenant_id', 'status', 'detected_at', 'id'], 'incidents_tenant_status_detected_id_idx');

            // Speeds up incidents list when filtering by kind.
            $table->index(['tenant_id', 'kind', 'detected_at', 'id'], 'incidents_tenant_kind_detected_id_idx');

            // Speeds up dedupe upsert lookup with status + fingerprint.
            $table->index(
                ['tenant_id', 'monitored_url_id', 'kind', 'fingerprint', 'status'],
                'incidents_tenant_monitor_kind_fp_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropIndex('incidents_tenant_monitor_kind_fp_status_idx');
            $table->dropIndex('incidents_tenant_kind_detected_id_idx');
            $table->dropIndex('incidents_tenant_status_detected_id_idx');
        });

        Schema::table('monitored_urls', function (Blueprint $table) {
            $table->dropIndex('monitored_urls_active_next_scan_id_idx');
        });
    }
};

