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
        Schema::create('dodo_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('webhook_id')->unique();
            $table->string('event_type');
            $table->string('payload_hash', 64);
            $table->timestamp('event_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['event_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dodo_webhook_events');
    }
};
