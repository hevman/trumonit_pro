<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_checkout_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider', 32);
            $table->string('target_plan_tier', 32);
            $table->string('status', 32)->default('pending');
            $table->string('external_session_id', 191)->nullable();
            $table->text('checkout_url')->nullable();

            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                $table->jsonb('metadata')->nullable();
            } else {
                $table->json('metadata')->nullable();
            }

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status'], 'billing_checkout_sessions_tenant_status_idx');
            $table->index(['tenant_id', 'created_at'], 'billing_checkout_sessions_tenant_created_idx');
            $table->unique(['provider', 'external_session_id'], 'billing_checkout_sessions_provider_external_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_checkout_sessions');
    }
};
