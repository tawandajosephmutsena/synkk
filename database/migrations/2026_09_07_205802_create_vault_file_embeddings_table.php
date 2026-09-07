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
        Schema::create('vault_file_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vault_id')->constrained('vaults')->cascadeOnDelete();
            $table->foreignId('vault_file_id')->constrained('vault_files')->cascadeOnDelete();
            $table->integer('chunk_index')->default(0);
            $table->string('heading', 255)->nullable();
            $table->integer('start_line')->default(1);
            $table->text('content');
            $table->integer('token_count')->default(0);
            $table->longText('embedding');
            $table->string('content_hash', 64)->index();
            $table->json('wikilinks')->nullable();
            $table->timestamps();

            $table->index(['vault_id', 'vault_file_id']);
            $table->index(['vault_id', 'content_hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vault_file_embeddings');
    }
};
