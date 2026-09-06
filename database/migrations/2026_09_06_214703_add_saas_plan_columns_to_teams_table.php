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
            $table->string('plan')->default('free')->after('license_status'); // 'free', 'pro_ltd', 'cloud'
            $table->string('status')->default('active')->after('plan'); // 'active', 'suspended', 'trial'
            $table->unsignedInteger('storage_limit_mb')->nullable()->after('status');
            $table->unsignedInteger('max_devices')->nullable()->after('storage_limit_mb');
            $table->unsignedInteger('max_vaults')->nullable()->after('max_devices');
            $table->unsignedInteger('max_members')->nullable()->after('max_vaults');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn([
                'plan',
                'status',
                'storage_limit_mb',
                'max_devices',
                'max_vaults',
                'max_members',
            ]);
        });
    }
};
