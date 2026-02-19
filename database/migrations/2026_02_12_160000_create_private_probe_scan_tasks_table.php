<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('private_probe_scan_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('private_probe_id')->constrained('private_probes')->cascadeOnDelete();
            $table->foreignId('monitored_url_id')->constrained('monitored_urls')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('queued');
            $table->unsignedSmallInteger('priority')->default(50);
            $table->string('lease_token_hash', 64)->nullable();
            $table->timestamp('lease_expires_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('result_received_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                $table->jsonb('result_payload')->nullable();
                $table->jsonb('meta')->nullable();
            } else {
                $table->json('result_payload')->nullable();
                $table->json('meta')->nullable();
            }
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'priority', 'id'], 'probe_tasks_tenant_status_priority_id_idx');
            $table->index(['private_probe_id', 'status', 'id'], 'probe_tasks_probe_status_id_idx');
            $table->index('lease_expires_at', 'probe_tasks_lease_expires_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('private_probe_scan_tasks');
    }
};
