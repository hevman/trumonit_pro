<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitored_urls', function (Blueprint $table) {
            $table->index(['tenant_id', 'url']);
        });

        Schema::table('incidents', function (Blueprint $table) {
            $table->index(['tenant_id', 'detected_at']);
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'detected_at']);
        });

        Schema::table('monitored_urls', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'url']);
        });
    }
};

