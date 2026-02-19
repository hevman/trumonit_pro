<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_windows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('monitored_url_id')->nullable()->constrained('monitored_urls')->cascadeOnDelete();
            $table->string('tag', 32)->nullable();
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->index();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'starts_at']);
            $table->index(['tenant_id', 'ends_at']);
            $table->index(['tenant_id', 'monitored_url_id']);
            $table->index(['tenant_id', 'tag']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_windows');
    }
};

