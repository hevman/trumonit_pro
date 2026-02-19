<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitored_urls', function (Blueprint $table) {
            $table->foreignId('baseline_snapshot_id')
                ->nullable()
                ->constrained('url_snapshots')
                ->nullOnDelete();

            $table->timestamp('baseline_set_at')->nullable();

            $table->string('scan_mode', 20)->default('http');

            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                $table->jsonb('journey_steps')->nullable();
                $table->jsonb('content_rules')->nullable();
            } else {
                $table->json('journey_steps')->nullable();
                $table->json('content_rules')->nullable();
            }

            $table->unsignedSmallInteger('appearance_min_distance')->nullable();
        });

        Schema::table('monitored_urls', function (Blueprint $table) {
            $table->index(['tenant_id', 'scan_mode']);
        });
    }

    public function down(): void
    {
        Schema::table('monitored_urls', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'scan_mode']);

            $table->dropColumn([
                'appearance_min_distance',
                'content_rules',
                'journey_steps',
                'scan_mode',
                'baseline_set_at',
            ]);

            $table->dropConstrainedForeignId('baseline_snapshot_id');
        });
    }
};

