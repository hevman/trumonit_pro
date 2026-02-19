<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('private_probes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 120);
            $table->string('region_hint', 64)->nullable();
            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                $table->jsonb('capabilities')->nullable();
                $table->jsonb('meta')->nullable();
            } else {
                $table->json('capabilities')->nullable();
                $table->json('meta')->nullable();
            }
            $table->string('auth_token_hash', 64)->unique();
            $table->string('auth_token_last8', 8);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_seen_ip', 64)->nullable();
            $table->string('last_seen_user_agent', 255)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active'], 'private_probes_tenant_active_idx');
            $table->index(['tenant_id', 'last_seen_at'], 'private_probes_tenant_last_seen_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('private_probes');
    }
};
