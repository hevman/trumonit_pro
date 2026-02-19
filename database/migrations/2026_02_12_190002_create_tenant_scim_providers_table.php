<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_scim_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->string('auth_token_hash', 64)->nullable();
            $table->string('auth_token_last8', 8)->nullable();
            $table->boolean('auto_provision')->default(false);
            $table->string('default_role', 16)->default('member');
            $table->string('deprovision_mode', 16)->default('disable');

            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                $table->jsonb('domain_allowlist')->nullable();
                $table->jsonb('metadata')->nullable();
            } else {
                $table->json('domain_allowlist')->nullable();
                $table->json('metadata')->nullable();
            }

            $table->timestamp('last_rotated_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id'], 'tenant_scim_provider_tenant_unique');
            $table->index(['is_enabled'], 'tenant_scim_provider_enabled_idx');
            $table->index(['auth_token_hash'], 'tenant_scim_provider_token_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_scim_providers');
    }
};
