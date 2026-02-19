<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitored_url_tag', function (Blueprint $table) {
            $table->foreignId('monitored_url_id')->constrained('monitored_urls')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();

            $table->primary(['monitored_url_id', 'tag_id']);
            $table->index(['tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitored_url_tag');
    }
};

