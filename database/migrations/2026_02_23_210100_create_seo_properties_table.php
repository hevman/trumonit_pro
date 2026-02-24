<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('monitored_url_id')->constrained('monitored_urls')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('domain', 255);
            $table->string('gsc_site_url', 500);
            $table->string('ga4_property_id', 64);
            $table->boolean('is_enabled')->default(true);
            $table->unsignedSmallInteger('alert_clicks_drop_percent')->default(30);
            $table->unsignedSmallInteger('alert_impressions_drop_percent')->default(30);
            $table->unsignedSmallInteger('alert_sessions_drop_percent')->default(25);
            $table->unsignedTinyInteger('baseline_days')->default(7);
            $table->timestamp('last_evaluated_at')->nullable();

            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                $table->jsonb('metadata')->nullable();
            } else {
                $table->json('metadata')->nullable();
            }

            $table->timestamps();

            $table->unique(['tenant_id', 'domain'], 'seo_properties_tenant_domain_unique');
            $table->index(['tenant_id', 'is_enabled'], 'seo_properties_tenant_enabled_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_properties');
    }
};
