<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('url_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitored_url_id')->constrained('monitored_urls')->cascadeOnDelete();
            $table->timestamp('fetched_at')->index();
            $table->text('request_url');
            $table->text('final_url')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                $table->jsonb('headers')->nullable();
            } else {
                $table->json('headers')->nullable();
            }
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->unsignedBigInteger('body_size')->nullable();
            $table->string('body_sha256', 64)->nullable()->index();
            $table->text('body_storage_path')->nullable();
            $table->text('screenshot_storage_path')->nullable();
            $table->string('screenshot_phash', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['monitored_url_id', 'fetched_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('url_snapshots');
    }
};
