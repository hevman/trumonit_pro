<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->foreignId('assigned_user_id')
                ->nullable()
                ->after('monitored_url_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('snoozed_until')
                ->nullable()
                ->after('resolved_at');

            $table->index(['tenant_id', 'assigned_user_id']);
            $table->index(['tenant_id', 'snoozed_until']);
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'snoozed_until']);
            $table->dropIndex(['tenant_id', 'assigned_user_id']);
            $table->dropConstrainedForeignId('assigned_user_id');
            $table->dropColumn('snoozed_until');
        });
    }
};
