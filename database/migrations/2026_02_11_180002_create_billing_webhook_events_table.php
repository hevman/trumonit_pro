<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('event_id', 191);
            $table->string('event_type', 120)->default('unknown');
            $table->string('status', 32)->default('received');

            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                $table->jsonb('payload')->nullable();
            } else {
                $table->json('payload')->nullable();
            }

            $table->text('error_message')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_id'], 'billing_webhook_events_provider_event_unique');
            $table->index(['provider', 'status'], 'billing_webhook_events_provider_status_idx');
            $table->index(['received_at'], 'billing_webhook_events_received_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_webhook_events');
    }
};
