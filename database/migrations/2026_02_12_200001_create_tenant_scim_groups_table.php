<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_scim_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('display_name', 191);
            $table->string('external_id', 191)->nullable();

            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                $table->jsonb('metadata')->nullable();
            } else {
                $table->json('metadata')->nullable();
            }

            $table->timestamps();

            $table->unique(['tenant_id', 'display_name'], 'tenant_scim_group_name_unique');
            $table->index(['tenant_id', 'external_id'], 'tenant_scim_group_external_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_scim_groups');
    }
};
