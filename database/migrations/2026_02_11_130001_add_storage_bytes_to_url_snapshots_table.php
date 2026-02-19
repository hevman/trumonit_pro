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
        Schema::table('url_snapshots', function (Blueprint $table) {
            $table->unsignedBigInteger('body_bytes_stored')->nullable()->after('body_size');
            $table->unsignedBigInteger('screenshot_bytes_stored')->nullable()->after('screenshot_storage_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('url_snapshots', function (Blueprint $table) {
            $table->dropColumn(['body_bytes_stored', 'screenshot_bytes_stored']);
        });
    }
};
