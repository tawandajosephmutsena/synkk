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
        Schema::create('vault_portals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vault_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('domain')->nullable();
            $table->text('description')->nullable();
            $table->string('layout')->default('docs'); // 'docs', 'bento', 'dashboard', 'minimal'
            $table->string('theme')->default('obsidian-noir'); // 'obsidian-noir', 'slate-luxe', 'midnight-emerald', 'paper-craft', 'amber-gold'
            $table->string('font_family')->default('sans'); // 'sans', 'serif', 'mono'
            $table->string('accent_color')->default('amber'); // 'amber', 'emerald', 'indigo', 'rose', 'cyan', 'violet'
            $table->boolean('is_public')->default(true);
            $table->string('password_hash')->nullable();
            $table->string('root_path')->nullable()->default('/');
            $table->foreignId('primary_file_id')->nullable()->constrained('vault_files')->nullOnDelete();
            $table->json('settings')->nullable();
            $table->unsignedBigInteger('views_count')->default(0);
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'vault_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vault_portals');
    }
};
