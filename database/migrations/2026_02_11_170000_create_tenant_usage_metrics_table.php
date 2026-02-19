<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_usage_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('period_type', 16);
            $table->date('period_start_date');
            $table->string('metric_key', 64);
            $table->unsignedBigInteger('value')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'period_type', 'period_start_date', 'metric_key'], 'tenant_usage_metric_unique');
            $table->index(['tenant_id', 'period_type', 'period_start_date'], 'tenant_usage_metric_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_usage_metrics');
    }
};
