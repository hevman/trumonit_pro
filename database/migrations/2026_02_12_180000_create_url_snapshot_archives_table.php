<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('url_snapshot_archives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('monitored_url_id')->index();
            $table->unsignedBigInteger('original_snapshot_id')->nullable()->unique();
            $table->string('archived_month', 7)->index();
            $table->timestamp('fetched_at')->index();
            $table->integer('http_status')->nullable();
            $table->integer('response_time_ms')->nullable();
            $table->unsignedBigInteger('body_size')->nullable();
            $table->unsignedBigInteger('body_bytes_stored')->default(0);
            $table->unsignedBigInteger('screenshot_bytes_stored')->default(0);
            $table->text('body_storage_path')->nullable();
            $table->text('screenshot_storage_path')->nullable();
            $table->string('storage_tier', 16)->default('archive');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'archived_month', 'id'], 'url_snapshot_archives_tenant_month_id_idx');
            $table->index(['monitored_url_id', 'fetched_at', 'id'], 'url_snapshot_archives_monitor_fetched_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('url_snapshot_archives');
    }
};
