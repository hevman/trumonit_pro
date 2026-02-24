<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_backlink_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seo_property_id')->constrained('seo_properties')->cascadeOnDelete();
            $table->date('metric_date');
            $table->unsignedInteger('referring_domains_total')->default(0);
            $table->unsignedInteger('backlinks_total')->default(0);
            $table->unsignedInteger('lost_referring_domains')->default(0);
            $table->unsignedInteger('new_referring_domains')->default(0);

            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                $table->jsonb('source_payload')->nullable();
            } else {
                $table->json('source_payload')->nullable();
            }

            $table->timestamps();

            $table->unique(['tenant_id', 'seo_property_id', 'metric_date'], 'seo_backlink_metrics_unique');
            $table->index(['tenant_id', 'metric_date'], 'seo_backlink_metrics_tenant_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_backlink_metrics');
    }
};
