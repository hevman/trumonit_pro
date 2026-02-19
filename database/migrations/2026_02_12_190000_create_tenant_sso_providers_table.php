<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_sso_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32)->default('oidc');
            $table->boolean('is_enabled')->default(false);
            $table->string('issuer_url', 2048)->nullable();
            $table->string('authorization_endpoint', 2048)->nullable();
            $table->string('token_endpoint', 2048)->nullable();
            $table->string('userinfo_endpoint', 2048)->nullable();
            $table->string('jwks_uri', 2048)->nullable();
            $table->string('client_id', 255)->nullable();
            $table->text('client_secret')->nullable();
            $table->string('scopes', 255)->default('openid profile email');
            $table->string('email_claim', 64)->default('email');
            $table->string('name_claim', 64)->default('name');
            $table->string('subject_claim', 64)->default('sub');

            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                $table->jsonb('domain_allowlist')->nullable();
                $table->jsonb('metadata')->nullable();
            } else {
                $table->json('domain_allowlist')->nullable();
                $table->json('metadata')->nullable();
            }

            $table->boolean('auto_provision')->default(false);
            $table->string('auto_provision_role', 16)->default('member');
            $table->boolean('enforce_verified_email')->default(true);
            $table->timestamp('last_tested_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'provider'], 'tenant_sso_provider_unique');
            $table->index(['tenant_id', 'is_enabled'], 'tenant_sso_provider_enabled_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_sso_providers');
    }
};
