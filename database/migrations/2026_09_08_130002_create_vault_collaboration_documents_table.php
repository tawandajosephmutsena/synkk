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
        Schema::create('vault_collaboration_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vault_id')->constrained('vaults')->cascadeOnDelete();
            $table->string('path');
            $table->unsignedBigInteger('latest_sequence')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['vault_id', 'path']);
            $table->index(['vault_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vault_collaboration_documents');
    }
};
