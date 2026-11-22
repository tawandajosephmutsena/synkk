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
        Schema::table('vault_portals', function (Blueprint $table): void {
            $table->boolean('ai_enabled')->default(false)->after('settings');
            $table->unsignedInteger('ai_daily_question_limit')->default(100)->after('ai_enabled');
            $table->boolean('llms_txt_enabled')->default(true)->after('ai_daily_question_limit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vault_portals', function (Blueprint $table): void {
            $table->dropColumn(['ai_enabled', 'ai_daily_question_limit', 'llms_txt_enabled']);
        });
    }
};
