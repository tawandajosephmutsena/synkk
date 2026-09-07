<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. E2EE columns for vaults
        Schema::table('vaults', function (Blueprint $table) {
            $table->boolean('is_e2ee')->default(false)->after('default_permission');
            $table->string('e2ee_salt', 64)->nullable()->after('is_e2ee');
            $table->text('e2ee_test_cipher')->nullable()->after('e2ee_salt');
        });

        // 2. Ghost files & E2EE columns for vault_files
        Schema::table('vault_files', function (Blueprint $table) {
            $table->boolean('is_ghost')->default(false)->after('is_deleted');
            $table->unsignedBigInteger('original_size')->default(0)->after('is_ghost');
            $table->string('mime_type', 100)->nullable()->after('original_size');
            $table->timestamp('hydrated_at')->nullable()->after('mime_type');

            $table->boolean('is_encrypted')->default(false)->after('hydrated_at');
            $table->string('encryption_iv', 64)->nullable()->after('is_encrypted');
            $table->string('encryption_tag', 64)->nullable()->after('encryption_iv');
        });
    }

    public function down(): void
    {
        Schema::table('vault_files', function (Blueprint $table) {
            $table->dropColumn([
                'is_ghost',
                'original_size',
                'mime_type',
                'hydrated_at',
                'is_encrypted',
                'encryption_iv',
                'encryption_tag',
            ]);
        });

        Schema::table('vaults', function (Blueprint $table) {
            $table->dropColumn(['is_e2ee', 'e2ee_salt', 'e2ee_test_cipher']);
        });
    }
};
