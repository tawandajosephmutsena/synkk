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
        Schema::create('pending_saas_checkouts', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->string('name');
            $table->string('workspace_name');
            $table->string('email')->index();
            $table->string('plan', 32);
            $table->string('status', 32)->default('pending')->index();
            $table->string('checkout_session_id')->nullable()->index();
            $table->string('billing_customer_id')->nullable()->index();
            $table->string('billing_subscription_id')->nullable()->unique();
            $table->string('billing_product_id')->nullable();
            $table->string('billing_status')->nullable();
            $table->timestamp('billing_next_billing_at')->nullable();
            $table->timestamp('billing_last_event_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['email', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_saas_checkouts');
    }
};
