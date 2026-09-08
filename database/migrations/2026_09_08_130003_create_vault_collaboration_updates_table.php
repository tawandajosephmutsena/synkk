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
        Schema::create('vault_collaboration_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vault_collaboration_document_id')
                ->constrained('vault_collaboration_documents')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('sequence');
            $table->string('client_update_id', 128);
            $table->foreignId('device_token_id')->nullable()->constrained('device_tokens')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->longText('payload');
            $table->string('payload_sha256', 64);
            $table->boolean('is_encrypted')->default(false);
            $table->string('encryption_iv', 64)->nullable();
            $table->string('encryption_tag', 64)->nullable();
            $table->unsignedTinyInteger('format_version')->default(2);
            $table->boolean('is_checkpoint')->default(false);
            $table->unsignedBigInteger('acknowledged_base_sequence')->nullable();
            $table->timestamps();

            $table->unique(['vault_collaboration_document_id', 'client_update_id'], 'vcu_doc_client_update_unique');
            $table->unique(['vault_collaboration_document_id', 'sequence'], 'vcu_doc_sequence_unique');
            $table->index(['vault_collaboration_document_id', 'is_checkpoint'], 'vcu_doc_checkpoint_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vault_collaboration_updates');
    }
};
