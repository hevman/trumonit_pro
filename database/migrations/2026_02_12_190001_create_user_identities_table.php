<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('provider_subject', 191);
            $table->string('email', 191)->nullable();
            $table->string('name', 191)->nullable();

            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                $table->jsonb('last_claims')->nullable();
            } else {
                $table->json('last_claims')->nullable();
            }

            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'provider', 'provider_subject'], 'user_identity_subject_unique');
            $table->index(['tenant_id', 'provider', 'email'], 'user_identity_email_lookup_idx');
            $table->index(['user_id', 'provider'], 'user_identity_user_provider_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_identities');
    }
};
