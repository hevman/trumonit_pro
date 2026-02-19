<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('url_snapshots', function (Blueprint $table) {
            $table->string('storage_tier', 16)->default('hot')->after('screenshot_bytes_stored');
            $table->timestamp('archived_at')->nullable()->after('storage_tier');

            $table->index(['storage_tier', 'fetched_at', 'id'], 'url_snapshots_tier_fetched_id_idx');
            $table->index('archived_at', 'url_snapshots_archived_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('url_snapshots', function (Blueprint $table) {
            $table->dropIndex('url_snapshots_archived_at_idx');
            $table->dropIndex('url_snapshots_tier_fetched_id_idx');
            $table->dropColumn(['storage_tier', 'archived_at']);
        });
    }
};
