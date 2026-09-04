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
        Schema::table('vault_change_logs', function (Blueprint $table) {
            $table->boolean('has_secrets')->default(false)->after('size');
            $table->json('detected_secrets')->nullable()->after('has_secrets');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vault_change_logs', function (Blueprint $table) {
            $table->dropColumn(['has_secrets', 'detected_secrets']);
        });
    }
};
