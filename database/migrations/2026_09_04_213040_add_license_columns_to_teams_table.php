<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('license_key')->nullable()->after('personal_team');
            $table->string('license_status')->default('free_trial')->after('license_key'); // 'active', 'inactive', 'free_trial', 'expired'
            $table->timestamp('license_activated_at')->nullable()->after('license_status');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['license_key', 'license_status', 'license_activated_at']);
        });
    }
};
