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
        Schema::create('vault_file_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vault_file_id')->constrained('vault_files')->cascadeOnDelete();
            $table->foreignId('vault_id')->constrained('vaults')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('storage_path');
            $table->string('sha256', 64);
            $table->unsignedBigInteger('size');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['vault_file_id', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vault_file_versions');
    }
};
