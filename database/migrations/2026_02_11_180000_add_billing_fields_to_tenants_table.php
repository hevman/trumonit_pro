<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('billing_provider', 32)->nullable();
            $table->string('billing_customer_ref', 191)->nullable();
            $table->string('billing_subscription_ref', 191)->nullable();
            $table->string('billing_subscription_status', 32)->nullable();
            $table->timestamp('billing_plan_changed_at')->nullable();

            $table->index('billing_provider');
            $table->index('billing_subscription_status');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['billing_provider']);
            $table->dropIndex(['billing_subscription_status']);

            $table->dropColumn([
                'billing_provider',
                'billing_customer_ref',
                'billing_subscription_ref',
                'billing_subscription_status',
                'billing_plan_changed_at',
            ]);
        });
    }
};
