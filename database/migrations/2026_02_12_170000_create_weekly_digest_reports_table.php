<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_digest_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('generated_by', 64)->default('wids:weekly-digest');
            $table->date('week_start_date');
            $table->date('week_end_date');
            $table->string('dispatch_mode', 16)->default('queue');

            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                $table->jsonb('recipients')->nullable();
                $table->jsonb('summary')->nullable();
            } else {
                $table->json('recipients')->nullable();
                $table->json('summary')->nullable();
            }

            $table->text('json_storage_path');
            $table->text('csv_storage_path')->nullable();
            $table->string('report_format_version', 16)->default('1');
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'week_start_date', 'week_end_date'], 'weekly_digest_reports_tenant_window_idx');
            $table->index(['tenant_id', 'generated_at'], 'weekly_digest_reports_tenant_generated_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_digest_reports');
    }
};

