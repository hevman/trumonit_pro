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
        Schema::create('monitored_urls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('url');
            $table->string('display_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('frequency_minutes')->default(1440);
            $table->unsignedInteger('timeout_seconds')->default(30);
            $table->boolean('follow_redirects')->default(true);
            $table->unsignedSmallInteger('expected_status_min')->default(200);
            $table->unsignedSmallInteger('expected_status_max')->default(399);
            $table->timestamp('last_scanned_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monitored_urls');
    }
};

