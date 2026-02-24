<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_index_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seo_property_id')->constrained('seo_properties')->cascadeOnDelete();
            $table->date('metric_date');
            $table->unsignedInteger('indexed_urls')->default(0);
            $table->unsignedInteger('submitted_urls')->default(0);
            $table->decimal('coverage_ratio', 6, 4)->default(0);

            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                $table->jsonb('source_payload')->nullable();
            } else {
                $table->json('source_payload')->nullable();
            }

            $table->timestamps();

            $table->unique(['tenant_id', 'seo_property_id', 'metric_date'], 'seo_index_metrics_unique');
            $table->index(['tenant_id', 'metric_date'], 'seo_index_metrics_tenant_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_index_metrics');
    }
};
