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
        // 1. Vaults
        Schema::create('vaults', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('default_permission')->default('read_write'); // 'read_write', 'read_only', 'hidden'
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_id', 'slug']);
        });

        // 2. Folder and File Level Permissions
        Schema::create('vault_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vault_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete(); // null = applies to all team members
            $table->string('path'); // e.g. '01 - Projects' or '02 - Finance/Confidential.md'
            $table->string('permission'); // 'read_write', 'read_only', 'hidden'
            $table->boolean('is_folder')->default(true);
            $table->timestamps();

            $table->index(['vault_id', 'path']);
            $table->index(['vault_id', 'user_id']);
        });

        // 3. Vault Files
        Schema::create('vault_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vault_id')->constrained()->cascadeOnDelete();
            $table->string('path', 1024); // relative path within the vault
            $table->string('storage_path'); // location in storage/app/vaults/
            $table->string('sha256', 64);
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedBigInteger('version')->default(1);
            $table->boolean('is_deleted')->default(false);
            $table->foreignId('last_modified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['vault_id', 'path']);
            $table->index(['vault_id', 'version']);
        });

        // 4. Change Log (For incremental sync & audit history)
        Schema::create('vault_change_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vault_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('device_name')->nullable();
            $table->string('path', 1024);
            $table->string('action'); // 'created', 'updated', 'deleted', 'conflict'
            $table->unsignedBigInteger('version');
            $table->string('sha256', 64)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['vault_id', 'version']);
            $table->index(['vault_id', 'created_at']);
        });

        // 5. Device Tokens (For Obsidian Mac, Windows, iOS, Android sync)
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // e.g. "MacBook Pro", "iPhone 15", "Team Office PC"
            $table->string('token_hash', 64)->unique();
            $table->string('token_preview', 8); // first 8 chars for user display
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->string('client_platform')->nullable(); // 'ios', 'android', 'mac', 'windows', 'linux'
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
        Schema::dropIfExists('vault_change_logs');
        Schema::dropIfExists('vault_files');
        Schema::dropIfExists('vault_permissions');
        Schema::dropIfExists('vaults');
    }
};
