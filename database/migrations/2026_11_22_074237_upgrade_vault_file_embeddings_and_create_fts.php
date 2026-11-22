<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vault_file_embeddings', function (Blueprint $table): void {
            $table->binary('embedding_blob')->nullable()->after('embedding');
            $table->string('embedding_model', 120)->nullable()->after('embedding_blob');
            $table->unsignedSmallInteger('embedding_dimensions')->default(0)->after('embedding_model');
        });

        if (DB::getDriverName() === 'sqlite') {
            DB::statement("CREATE VIRTUAL TABLE IF NOT EXISTS vault_chunks_fts USING fts5(chunk_id UNINDEXED, vault_id UNINDEXED, path, heading, content, tokenize='porter unicode61')");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP TABLE IF EXISTS vault_chunks_fts');
        }

        Schema::table('vault_file_embeddings', function (Blueprint $table): void {
            $table->dropColumn([
                'embedding_blob',
                'embedding_model',
                'embedding_dimensions',
            ]);
        });
    }
};
