<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->string('access_scope')->default('full_access')->after('client_platform'); // 'full_access', 'read_only'
            $table->json('allowed_ip_subnets')->nullable()->after('access_scope');
            $table->json('allowed_vault_ids')->nullable()->after('allowed_ip_subnets');
            $table->boolean('is_wiped')->default(false)->after('allowed_vault_ids');
            $table->timestamp('wiped_at')->nullable()->after('is_wiped');
        });
    }

    public function down(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->dropColumn(['access_scope', 'allowed_ip_subnets', 'allowed_vault_ids', 'is_wiped', 'wiped_at']);
        });
    }
};
