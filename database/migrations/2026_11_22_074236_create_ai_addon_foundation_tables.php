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
        Schema::create('team_ai_configs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->unique()->constrained('teams')->cascadeOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->string('provider_mode', 32)->default('off');
            $table->boolean('fallback_to_byok')->default(false);
            $table->string('chat_provider', 40)->nullable();
            $table->string('chat_base_url', 255)->nullable();
            $table->string('chat_model', 120)->nullable();
            $table->string('fast_model', 120)->nullable();
            $table->text('chat_api_key')->nullable();
            $table->string('embedding_provider', 40)->nullable();
            $table->string('embedding_base_url', 255)->nullable();
            $table->string('embedding_model', 120)->nullable();
            $table->text('embedding_api_key')->nullable();
            $table->boolean('dlp_redaction_enabled')->default(true);
            $table->unsignedInteger('monthly_budget_cents')->nullable();
            $table->unsignedBigInteger('trial_credits_microcents')->default(0);
            $table->timestamp('trial_granted_at')->nullable();
            $table->unsignedBigInteger('managed_credits_microcents')->default(0);
            $table->unsignedBigInteger('managed_credits_used_microcents')->default(0);
            $table->timestamp('managed_cycle_resets_at')->nullable();
            $table->string('ai_billing_subscription_id', 120)->nullable()->index();
            $table->string('ai_billing_product_id', 120)->nullable();
            $table->string('ai_billing_status', 40)->nullable();
            $table->timestamp('ai_billing_access_until')->nullable();
            $table->timestamp('ai_billing_cancelled_at')->nullable();
            $table->timestamp('ai_billing_last_event_at')->nullable();
            $table->timestamps();
        });

        Schema::create('vault_ai_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vault_id')->unique()->constrained('vaults')->cascadeOnDelete();
            $table->string('ai_mode', 24)->default('off');
            $table->json('excluded_paths')->nullable();
            $table->boolean('allow_direct_mcp_writes')->default(false);
            $table->boolean('allow_direct_inbox_frontmatter')->default(false);
            $table->boolean('gardener_enabled')->default(false);
            $table->json('inbox_folders')->nullable();
            $table->unsignedSmallInteger('gardener_debounce_minutes')->default(5);
            $table->boolean('gardener_graph_linker')->default(true);
            $table->boolean('gardener_contradiction_check')->default(true);
            $table->string('gardener_schedule', 24)->default('debounced');
            $table->timestamp('last_gardened_at')->nullable();
            $table->string('digest_schedule', 24)->default('off');
            $table->string('digest_folder', 255)->default('Digests');
            $table->json('digest_delivery')->nullable();
            $table->timestamp('last_digest_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_usage_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('vault_id')->nullable()->constrained('vaults')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('portal_id')->nullable()->index();
            $table->string('feature', 40)->index();
            $table->string('provider_mode', 24);
            $table->string('provider', 40);
            $table->string('model', 120);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedBigInteger('cost_microcents')->default(0);
            $table->unsignedInteger('redacted_secrets_count')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['team_id', 'created_at']);
        });

        Schema::create('ai_platform_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('managed_ai_kill_switch')->default(false);
            $table->unsignedBigInteger('default_trial_credits_microcents')->default(200_000_000);
            $table->unsignedInteger('global_daily_spend_cap_cents')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_platform_settings');
        Schema::dropIfExists('ai_usage_logs');
        Schema::dropIfExists('vault_ai_settings');
        Schema::dropIfExists('team_ai_configs');
    }
};
