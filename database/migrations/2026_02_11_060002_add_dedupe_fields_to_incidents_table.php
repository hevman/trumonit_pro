<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->string('fingerprint', 64)->nullable()->index();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('resolved_at')->nullable();
        });

        Schema::table('incidents', function (Blueprint $table) {
            $table->index(['tenant_id', 'monitored_url_id', 'kind', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'monitored_url_id', 'kind', 'status']);
            $table->dropColumn(['fingerprint', 'last_seen_at', 'occurrences', 'resolved_at']);
        });
    }
};

