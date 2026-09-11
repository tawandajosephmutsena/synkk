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
        Schema::table('teams', function (Blueprint $table) {
            $table->string('billing_provider')->nullable()->after('status');
            $table->string('billing_customer_id')->nullable()->after('billing_provider');
            $table->string('billing_subscription_id')->nullable()->after('billing_customer_id');
            $table->string('billing_product_id')->nullable()->after('billing_subscription_id');
            $table->string('billing_status')->nullable()->after('billing_product_id');
            $table->string('billing_previous_plan')->nullable()->after('billing_status');
            $table->timestamp('billing_next_billing_at')->nullable()->after('billing_previous_plan');
            $table->timestamp('billing_access_until')->nullable()->after('billing_next_billing_at');
            $table->timestamp('billing_cancelled_at')->nullable()->after('billing_access_until');
            $table->timestamp('billing_last_event_at')->nullable()->after('billing_cancelled_at');

            $table->index('billing_customer_id');
            $table->unique('billing_subscription_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropUnique(['billing_subscription_id']);
            $table->dropIndex(['billing_customer_id']);
            $table->dropColumn([
                'billing_provider',
                'billing_customer_id',
                'billing_subscription_id',
                'billing_product_id',
                'billing_status',
                'billing_previous_plan',
                'billing_next_billing_at',
                'billing_access_until',
                'billing_cancelled_at',
                'billing_last_event_at',
            ]);
        });
    }
};
