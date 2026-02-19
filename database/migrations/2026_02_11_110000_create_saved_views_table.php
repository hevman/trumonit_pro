<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scope', 32);
            $table->string('name', 120);

            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                $table->jsonb('filters');
            } else {
                $table->json('filters');
            }

            $table->timestamps();

            $table->unique(['tenant_id', 'scope', 'name']);
            $table->index(['tenant_id', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_views');
    }
};

