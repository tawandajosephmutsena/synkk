<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

test('team owner can configure BYOK AI settings without leaking raw API key into rendered HTML', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['plan' => 'pro_ltd']);
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Design Vault',
        'default_permission' => 'read_write',
        'created_by' => $owner->id,
    ]);

    $secretKey = 'sk-super-secret-byok-key-9876543210';

    $component = Livewire::actingAs($owner)
        ->test('pages::teams.ai-addon-settings', ['team' => $team])
        ->set('isEnabled', true)
        ->set('providerMode', 'byok')
        ->set('chatProvider', 'openai')
        ->set('chatModel', 'gpt-4o-mini')
        ->set('chatApiKeyInput', $secretKey)
        ->set('dlpRedactionEnabled', true)
        ->call('saveAiConfig')
        ->assertHasNoErrors()
        ->assertSet('chatApiKeyInput', '')
        ->assertSet('hasChatApiKey', true);

    expect($component->html())->not->toContain($secretKey);

    $savedConfig = $team->aiConfig()->firstOrFail();
    expect($savedConfig->is_enabled)->toBeTrue()
        ->and($savedConfig->provider_mode)->toBe('byok')
        ->and($savedConfig->chat_api_key)->toBe($secretKey);

    // Save per-vault server mode + excluded paths
    Livewire::actingAs($owner)
        ->test('pages::teams.ai-addon-settings', ['team' => $team])
        ->set("vaultSettings.{$vault->id}.ai_mode", 'server')
        ->set("vaultSettings.{$vault->id}.excluded_paths_text", 'Private/**, HR/**')
        ->call('saveVaultSetting', $vault->id)
        ->assertHasNoErrors();

    $vaultSetting = $vault->aiSetting()->firstOrFail();
    expect($vaultSetting->ai_mode)->toBe('server')
        ->and($vaultSetting->excluded_paths)->toBe(['Private/**', 'HR/**']);
});

test('Zero-Knowledge E2EE vault rejects server AI mode in team AI settings', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['plan' => 'cloud']);
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $e2eeVault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Secret E2EE Vault',
        'default_permission' => 'read_write',
        'created_by' => $owner->id,
        'is_e2ee' => true,
    ]);

    Livewire::actingAs($owner)
        ->test('pages::teams.ai-addon-settings', ['team' => $team])
        ->set("vaultSettings.{$e2eeVault->id}.ai_mode", 'server')
        ->call('saveVaultSetting', $e2eeVault->id)
        ->assertHasErrors(["vaultSettings.{$e2eeVault->id}.ai_mode"]);

    expect($e2eeVault->aiSetting()->exists())->toBeFalse();
});

test('non-admin team member is forbidden from mutating AI settings', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create(['plan' => 'cloud']);
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    Livewire::actingAs($member)
        ->test('pages::teams.ai-addon-settings', ['team' => $team])
        ->set('isEnabled', true)
        ->set('providerMode', 'ollama')
        ->set('chatModel', 'llama3.2')
        ->call('saveAiConfig')
        ->assertForbidden();

    expect($team->aiConfig()->exists())->toBeFalse();
});

test('team owner can test AI connection via Livewire action', function () {
    Http::preventStrayRequests();
    Http::fake([
        'http://localhost:11434/api/chat' => Http::response([
            'model' => 'llama3.2',
            'message' => ['role' => 'assistant', 'content' => 'OK'],
            'done_reason' => 'stop',
        ], 200),
    ]);

    $owner = User::factory()->create();
    $team = Team::factory()->create(['plan' => 'free']);
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    Livewire::actingAs($owner)
        ->test('pages::teams.ai-addon-settings', ['team' => $team])
        ->set('isEnabled', true)
        ->set('providerMode', 'ollama')
        ->set('chatModel', 'llama3.2')
        ->call('testChatConnection')
        ->assertSet('chatTestResult.ok', true);
});
