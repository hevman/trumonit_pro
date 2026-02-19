<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('status_pages', function (Blueprint $table) {
            $table->string('custom_domain', 253)->nullable()->after('slug');
            $table->unsignedInteger('cache_ttl_seconds')->default(30)->after('custom_domain');
            $table->unique('custom_domain');
        });
    }

    public function down(): void
    {
        Schema::table('status_pages', function (Blueprint $table) {
            $table->dropUnique(['custom_domain']);
            $table->dropColumn(['custom_domain', 'cache_ttl_seconds']);
        });
    }
};
