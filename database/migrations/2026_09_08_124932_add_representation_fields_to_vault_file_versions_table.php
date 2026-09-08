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
        Schema::table('vault_file_versions', function (Blueprint $table) {
            $table->boolean('is_encrypted')->default(false)->after('size');
            $table->string('encryption_iv')->nullable()->after('is_encrypted');
            $table->string('encryption_tag')->nullable()->after('encryption_iv');
            $table->boolean('is_ghost')->default(false)->after('encryption_tag');
            $table->unsignedBigInteger('original_size')->nullable()->after('is_ghost');
            $table->string('mime_type')->nullable()->after('original_size');
            $table->unsignedSmallInteger('format_version')->default(2)->after('mime_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vault_file_versions', function (Blueprint $table) {
            $table->dropColumn([
                'is_encrypted',
                'encryption_iv',
                'encryption_tag',
                'is_ghost',
                'original_size',
                'mime_type',
                'format_version',
            ]);
        });
    }
};
