<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 16);
            $table->boolean('is_enabled')->default(false);
            $table->string('google_client_id', 255)->nullable();
            $table->text('google_client_secret')->nullable();
            $table->text('refresh_token')->nullable();
            $table->text('access_token')->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
            $table->string('scopes', 1000)->nullable();
            $table->string('external_account_email', 255)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();

            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                $table->jsonb('metadata')->nullable();
            } else {
                $table->json('metadata')->nullable();
            }

            $table->timestamps();

            $table->unique(['tenant_id', 'provider'], 'seo_integrations_tenant_provider_unique');
            $table->index(['provider', 'is_enabled'], 'seo_integrations_provider_enabled_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_integrations');
    }
};
