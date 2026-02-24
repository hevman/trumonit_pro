<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seo_property_id')->constrained('seo_properties')->cascadeOnDelete();
            $table->date('metric_date');
            $table->unsignedInteger('organic_clicks')->default(0);
            $table->unsignedInteger('organic_impressions')->default(0);
            $table->decimal('organic_ctr', 6, 4)->default(0);
            $table->decimal('avg_position', 8, 2)->default(0);
            $table->unsignedInteger('organic_sessions')->default(0);
            $table->unsignedInteger('organic_conversions')->default(0);

            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                $table->jsonb('source_payload')->nullable();
            } else {
                $table->json('source_payload')->nullable();
            }

            $table->timestamps();

            $table->unique(['seo_property_id', 'metric_date'], 'seo_daily_metrics_property_date_unique');
            $table->index(['tenant_id', 'metric_date'], 'seo_daily_metrics_tenant_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_daily_metrics');
    }
};
