<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitored_urls', function (Blueprint $table) {
            $table->unsignedSmallInteger('alert_after_failures')->default(1);
            $table->unsignedInteger('alert_cooldown_minutes')->default(0);
            $table->unsignedInteger('consecutive_failures')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('monitored_urls', function (Blueprint $table) {
            $table->dropColumn([
                'consecutive_failures',
                'alert_cooldown_minutes',
                'alert_after_failures',
            ]);
        });
    }
};

