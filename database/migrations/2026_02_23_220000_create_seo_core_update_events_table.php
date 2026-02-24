<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_core_update_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 64)->default('feed');
            $table->string('source_hash', 64)->unique();
            $table->string('title', 500);
            $table->string('source_url', 2048)->nullable();
            $table->text('summary')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->unsignedSmallInteger('impact_score')->default(70);

            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                $table->jsonb('metadata')->nullable();
            } else {
                $table->json('metadata')->nullable();
            }

            $table->timestamps();

            $table->index(['effective_from', 'effective_to'], 'seo_core_updates_effective_range_idx');
            $table->index(['published_at'], 'seo_core_updates_published_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_core_update_events');
    }
};
