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
        Schema::create('ai_proposals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('vault_id')->constrained('vaults')->cascadeOnDelete();
            $table->foreignId('vault_file_id')->nullable()->constrained('vault_files')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 32)->index();
            $table->string('action', 24)->default('update');
            $table->string('target_path', 500);
            $table->string('destination_path', 500)->nullable();
            $table->unsignedInteger('base_version')->nullable();
            $table->string('base_content_hash', 64)->nullable();
            $table->longText('proposed_content');
            $table->text('rationale')->nullable();
            $table->json('metadata')->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['vault_id', 'status', 'created_at']);
        });

        Schema::create('ai_threads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('vault_id')->constrained('vaults')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 255);
            $table->boolean('is_shared_with_team')->default(false);
            $table->foreignId('saved_vault_file_id')->nullable()->constrained('vault_files')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['vault_id', 'user_id', 'last_message_at']);
        });

        Schema::create('ai_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('thread_id')->constrained('ai_threads')->cascadeOnDelete();
            $table->string('role', 20);
            $table->longText('content');
            $table->json('citations')->nullable();
            $table->json('tool_calls')->nullable();
            $table->json('proposal_ids')->nullable();
            $table->string('model', 120)->nullable();
            $table->unsignedInteger('tokens_in')->default(0);
            $table->unsignedInteger('tokens_out')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamps();
        });

        Schema::create('ai_runs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('thread_id')->constrained('ai_threads')->cascadeOnDelete();
            $table->foreignId('vault_id')->constrained('vaults')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 24)->default('queued');
            $table->longText('partial_content')->nullable();
            $table->string('status_note', 255)->nullable();
            $table->foreignId('assistant_message_id')->nullable()->constrained('ai_messages')->nullOnDelete();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_runs');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_threads');
        Schema::dropIfExists('ai_proposals');
    }
};
