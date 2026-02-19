<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_scim_group_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_scim_group_id')->constrained('tenant_scim_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['tenant_scim_group_id', 'user_id'], 'tenant_scim_group_membership_unique');
            $table->index(['tenant_id', 'user_id'], 'tenant_scim_group_membership_tenant_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_scim_group_memberships');
    }
};
