<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_page_monitored_url', function (Blueprint $table) {
            $table->foreignId('status_page_id')->constrained('status_pages')->cascadeOnDelete();
            $table->foreignId('monitored_url_id')->constrained('monitored_urls')->cascadeOnDelete();

            $table->unsignedInteger('position')->default(0);
            $table->string('label')->nullable();

            $table->primary(['status_page_id', 'monitored_url_id']);
            $table->index(['monitored_url_id']);
            $table->index(['status_page_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_page_monitored_url');
    }
};

