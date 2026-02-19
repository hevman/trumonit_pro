<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('url_snapshots', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                $table->jsonb('meta')->nullable();
            } else {
                $table->json('meta')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('url_snapshots', function (Blueprint $table) {
            $table->dropColumn('meta');
        });
    }
};

