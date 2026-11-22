<?php

use App\Enums\TeamRole;
use App\Models\AiProposal;
use App\Models\AiThread;
use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\TeamAiConfig;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultAiSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
});

test('team owner can generate and revoke MCP tokens and toggle allow_direct_mcp_writes', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['plan' => 'pro_ltd']);
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'MCP Settings Vault',
        'default_permission' => 'read_write',
        'created_by' => $owner->id,
    ]);

    $component = Livewire::actingAs($owner)
        ->test('pages::teams.ai-addon-settings', ['team' => $team])
        ->set("vaultSettings.{$vault->id}.ai_mode", 'server')
        ->set("vaultSettings.{$vault->id}.allow_direct_mcp_writes", true)
        ->call('saveVaultSetting', $vault->id)
        ->set('mcpTokenName', 'Cursor IDE')
        ->set('mcpTokenScope', 'mcp_propose')
        ->set('mcpAllowedVaultId', (string) $vault->id)
        ->call('createMcpToken')
        ->assertHasNoErrors();

    $plainToken = $component->get('newlyCreatedMcpToken');
    expect($plainToken)->toStartWith('synkk_')
        ->and($vault->aiSetting()->firstOrFail()->allow_direct_mcp_writes)->toBeTrue();

    $mcpRow = DeviceToken::query()->where('team_id', $team->id)->mcpTokens()->firstOrFail();
    expect($mcpRow->allowed_vault_ids)->toBe([$vault->id])
        ->and($mcpRow->access_scope)->toBe('mcp_propose');

    $component->call('revokeMcpToken', $mcpRow->id);
    expect($mcpRow->fresh()->is_wiped)->toBeTrue();
});

test('vault AI proposals inbox previews, edits, approves, and rejects proposals via Livewire', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['plan' => 'pro_ltd']);
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Inbox UI Vault',
        'default_permission' => 'read_write',
        'created_by' => $owner->id,
    ]);

    $p1 = AiProposal::create([
        'team_id' => $team->id,
        'vault_id' => $vault->id,
        'created_by_user_id' => $owner->id,
        'source' => 'mcp',
        'action' => 'create',
        'target_path' => 'Notes/Reviewed.md',
        'proposed_content' => '# Initial AI Draft',
        'rationale' => 'Created by MCP',
        'status' => 'pending',
    ]);

    $p2 = AiProposal::create([
        'team_id' => $team->id,
        'vault_id' => $vault->id,
        'created_by_user_id' => $owner->id,
        'source' => 'copilot',
        'action' => 'create',
        'target_path' => 'Notes/Rejected.md',
        'proposed_content' => '# Unwanted',
        'status' => 'pending',
    ]);

    Livewire::actingAs($owner)
        ->test('pages::vaults.ai-proposals-inbox', ['vault' => $vault])
        ->call('togglePreview', $p1->id)
        ->set('overrideContent', '# Human-Tweaked Draft')
        ->call('approveProposal', $p1->id)
        ->assertDispatched('vault-proposals-updated')
        ->call('rejectProposal', $p2->id);

    expect($p1->fresh()->status)->toBe('approved')
        ->and($p2->fresh()->status)->toBe('rejected')
        ->and($vault->files()->where('path', 'Notes/Reviewed.md')->firstOrFail()->getContents())->toBe('# Human-Tweaked Draft');
});

test('vault AI agent panel sends messages, toggles sharing, and saves thread as note proposal', function () {
    Http::preventStrayRequests();
    Http::fake([
        'http://localhost:11434/api/chat' => Http::response(
            json_encode(['model' => 'llama3.2', 'message' => ['role' => 'assistant', 'content' => 'Vault summary ready.'], 'done' => true, 'prompt_eval_count' => 8, 'eval_count' => 12])."\n",
            200
        ),
    ]);

    $owner = User::factory()->create();
    $team = Team::factory()->create(['plan' => 'pro_ltd']);
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    TeamAiConfig::create([
        'team_id' => $team->id,
        'is_enabled' => true,
        'provider_mode' => 'ollama',
        'chat_provider' => 'ollama',
        'chat_model' => 'llama3.2',
    ]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Agent UI Vault',
        'default_permission' => 'read_write',
        'created_by' => $owner->id,
    ]);

    VaultAiSetting::create([
        'vault_id' => $vault->id,
        'ai_mode' => 'server',
    ]);

    $component = Livewire::actingAs($owner)
        ->test('pages::vaults.ai-agent-panel', ['vault' => $vault])
        ->set('promptInput', 'Summarize my vault')
        ->call('sendMessage')
        ->assertSee('Vault summary ready.')
        ->call('toggleShareThread')
        ->call('saveThreadAsNote')
        ->assertDispatched('vault-proposals-updated');

    $thread = AiThread::where('vault_id', $vault->id)->firstOrFail();
    expect($thread->is_shared_with_team)->toBeTrue()
        ->and(AiProposal::where('vault_id', $vault->id)->count())->toBe(1);
});
