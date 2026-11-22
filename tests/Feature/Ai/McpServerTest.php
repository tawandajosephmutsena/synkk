<?php

use App\Actions\Vaults\SyncUploadAction;
use App\Enums\TeamRole;
use App\Models\AiProposal;
use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\TeamAiConfig;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultAiSetting;
use App\Models\VaultPermission;
use App\Services\Ai\VaultIndexService;
use App\Services\PlanService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('MCP tokens are excluded from Obsidian device limits and blocked from direct sync write endpoints', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['plan' => 'free', 'max_devices' => 1]);
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    // 1 physical device fills the 1-device quota
    DeviceToken::createToken($owner, $team, 'MacBook', 'mac');
    $planService = app(PlanService::class);
    expect($planService->canAddDevice($team))->toBeFalse();

    // Creating an MCP token does NOT change userDevices() count
    $mcpToken = DeviceToken::createToken($owner, $team, 'Claude Desktop', DeviceToken::PLATFORM_MCP, 'mcp_write');
    expect($team->deviceTokens()->userDevices()->count())->toBe(1)
        ->and($team->deviceTokens()->mcpTokens()->count())->toBe(1);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'MCP Guarded Vault',
        'default_permission' => 'read_write',
        'created_by' => $owner->id,
    ]);

    // MCP token cannot call /upload, /batch-sync, or /delete directly AND cannot spoof X-Client-Platform
    $this->withHeaders([
        'Authorization' => 'Bearer '.$mcpToken['plain_token'],
        'X-Client-Platform' => 'mac',
    ])->postJson("/api/v1/vaults/{$vault->slug}/upload", [
        'path' => 'Notes/Bypass.md',
        'content' => 'Should be blocked',
    ])->assertForbidden();

    expect($mcpToken['device_token']->fresh()->client_platform)->toBe(DeviceToken::PLATFORM_MCP);
});

test('Remote MCP server handles initialize, resources, tools, scopes, ACLs, excluded paths, and E2EE guards', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create(['plan' => 'free']);
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    TeamAiConfig::create([
        'team_id' => $team->id,
        'is_enabled' => true,
        'provider_mode' => 'ollama',
        'chat_provider' => 'ollama',
        'chat_model' => 'llama3.2',
    ]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Team Knowledge',
        'default_permission' => 'read_write',
        'created_by' => $owner->id,
    ]);
    VaultAiSetting::create([
        'vault_id' => $vault->id,
        'ai_mode' => 'server',
        'excluded_paths' => ['Private/**'],
        'allow_direct_mcp_writes' => false,
    ]);

    $e2eeVault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Zero Knowledge',
        'default_permission' => 'read_write',
        'created_by' => $owner->id,
        'is_e2ee' => true,
    ]);

    VaultPermission::create([
        'vault_id' => $vault->id,
        'user_id' => $member->id,
        'path' => 'Finance',
        'permission' => 'hidden',
        'is_folder' => true,
    ]);

    $sync = app(SyncUploadAction::class);
    $sync->execute($vault, $owner, 'Mac', 'Engineering/Sync.md', "# Sync\nLinks to [[Engineering/Protocol]].");
    $sync->execute($vault, $owner, 'Mac', 'Engineering/Protocol.md', '# Protocol Details');
    $sync->execute($vault, $owner, 'Mac', 'Finance/Payroll.md', '# Payroll Secret');
    $sync->execute($vault, $owner, 'Mac', 'Private/Journal.md', '# Excluded Journal');
    app(VaultIndexService::class)->indexVault($vault);

    $readToken = DeviceToken::createToken($member, $team, 'Read Client', DeviceToken::PLATFORM_MCP, 'mcp_read')['plain_token'];
    $proposeToken = DeviceToken::createToken($member, $team, 'Propose Client', DeviceToken::PLATFORM_MCP, 'mcp_propose')['plain_token'];
    $writeToken = DeviceToken::createToken($owner, $team, 'Write Client', DeviceToken::PLATFORM_MCP, 'mcp_write')['plain_token'];

    // 1. Initialize
    $this->withHeader('Authorization', "Bearer {$readToken}")
        ->postJson(route('api.mcp.handle'), ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize'])
        ->assertOk()
        ->assertJsonPath('result.serverInfo.name', 'synkk-mcp');

    // 2. resources/list excludes Finance/Payroll.md (ACL) and Private/Journal.md (excluded_paths)
    $resourcesRes = $this->withHeader('Authorization', "Bearer {$readToken}")
        ->postJson(route('api.mcp.handle'), ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'resources/list'])
        ->assertOk();

    $uris = collect($resourcesRes->json('result.resources'))->pluck('uri');
    expect($uris)->toContain("synkk://vault/{$vault->slug}/note/Engineering/Sync.md")
        ->and($uris)->not->toContain("synkk://vault/{$vault->slug}/note/Finance/Payroll.md")
        ->and($uris)->not->toContain("synkk://vault/{$vault->slug}/note/Private/Journal.md");

    // 3. resources/read rejects hidden/excluded and E2EE
    $this->withHeader('Authorization', "Bearer {$readToken}")
        ->postJson(route('api.mcp.handle'), [
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'resources/read',
            'params' => ['uri' => "synkk://vault/{$vault->slug}/note/Finance/Payroll.md"],
        ])
        ->assertOk()
        ->assertJsonPath('error.code', -32003);

    $this->withHeader('Authorization', "Bearer {$writeToken}")
        ->postJson(route('api.mcp.handle'), [
            'jsonrpc' => '2.0',
            'id' => 4,
            'method' => 'tools/call',
            'params' => ['name' => 'synkk_read_note', 'arguments' => ['vault' => $e2eeVault->slug, 'path' => 'Anything.md']],
        ])
        ->assertOk()
        ->assertJsonPath('error.code', -32003);

    // 4. mcp_read token cannot propose notes
    $this->withHeader('Authorization', "Bearer {$readToken}")
        ->postJson(route('api.mcp.handle'), [
            'jsonrpc' => '2.0',
            'id' => 5,
            'method' => 'tools/call',
            'params' => [
                'name' => 'synkk_propose_note',
                'arguments' => ['vault' => $vault->slug, 'path' => 'Engineering/New.md', 'content' => '# New'],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('error.code', -32003);

    // 5. mcp_propose token stages proposal cleanly
    $this->withHeader('Authorization', "Bearer {$proposeToken}")
        ->postJson(route('api.mcp.handle'), [
            'jsonrpc' => '2.0',
            'id' => 6,
            'method' => 'tools/call',
            'params' => [
                'name' => 'synkk_propose_note',
                'arguments' => [
                    'vault' => $vault->slug,
                    'path' => 'Engineering/New.md',
                    'content' => '# Draft from Cursor',
                    'rationale' => 'Document API contract',
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('result.isError', false);

    expect(AiProposal::where('vault_id', $vault->id)->where('source', 'mcp')->count())->toBe(1);

    // 6. synkk_write_note blocked until vault opts into allow_direct_mcp_writes
    $this->withHeader('Authorization', "Bearer {$writeToken}")
        ->postJson(route('api.mcp.handle'), [
            'jsonrpc' => '2.0',
            'id' => 7,
            'method' => 'tools/call',
            'params' => [
                'name' => 'synkk_write_note',
                'arguments' => ['vault' => $vault->slug, 'path' => 'Engineering/Direct.md', 'content' => '# Direct'],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('error.code', -32003);

    $vault->aiSetting()->update(['allow_direct_mcp_writes' => true]);

    $this->withHeader('Authorization', "Bearer {$writeToken}")
        ->postJson(route('api.mcp.handle'), [
            'jsonrpc' => '2.0',
            'id' => 8,
            'method' => 'tools/call',
            'params' => [
                'name' => 'synkk_write_note',
                'arguments' => ['vault' => $vault->slug, 'path' => 'Engineering/Direct.md', 'content' => '# Direct Write OK'],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('result.isError', false);

    $directFile = $vault->files()->where('path', 'Engineering/Direct.md')->firstOrFail();
    expect($directFile->getContents())->toBe('# Direct Write OK');
});
