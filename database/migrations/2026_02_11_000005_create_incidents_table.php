<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('monitored_url_id')->constrained('monitored_urls')->cascadeOnDelete();
            $table->foreignId('previous_snapshot_id')->nullable()->constrained('url_snapshots')->nullOnDelete();
            $table->foreignId('current_snapshot_id')->constrained('url_snapshots')->cascadeOnDelete();
            $table->string('kind', 50);
            $table->unsignedSmallInteger('severity')->default(50);
            $table->string('status', 20)->default('open');
            $table->string('summary');
            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                $table->jsonb('details')->nullable();
            } else {
                $table->json('details')->nullable();
            }
            $table->timestamp('detected_at')->index();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
