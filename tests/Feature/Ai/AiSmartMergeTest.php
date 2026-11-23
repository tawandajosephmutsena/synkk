<?php

use App\Actions\Vaults\SyncUploadAction;
use App\Enums\TeamRole;
use App\Exceptions\AiException;
use App\Models\AiUsageLog;
use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\TeamAiConfig;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultAiSetting;
use App\Models\VaultPermission;
use App\Services\Ai\AiConflictMergeService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
});

function createSmartMergeFixture(string $plan = 'pro_ltd', string $aiMode = 'server', bool $isE2ee = false): array
{
    $owner = User::factory()->create(['name' => 'Alice Owner']);
    $collaborator = User::factory()->create(['name' => 'Bob Writer']);
    $team = Team::factory()->create(['plan' => $plan]);
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($collaborator, ['role' => TeamRole::Member->value]);
    $owner->update(['current_team_id' => $team->id]);

    TeamAiConfig::create([
        'team_id' => $team->id,
        'is_enabled' => true,
        'provider_mode' => 'byok',
        'chat_provider' => 'openai',
        'chat_model' => 'gpt-4o-mini',
        'fast_model' => 'gpt-4o-mini',
        'chat_api_key' => 'sk-test-'.str_repeat('a1', 12),
        'embedding_provider' => 'none',
    ]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Merge Test Vault',
        'default_permission' => 'read_write',
        'created_by' => $owner->id,
        'is_e2ee' => $isE2ee,
    ]);

    VaultAiSetting::create([
        'vault_id' => $vault->id,
        'ai_mode' => $aiMode,
        'excluded_paths' => ['Private/**'],
    ]);

    return compact('owner', 'collaborator', 'team', 'vault');
}

function seedConflictingNote(
    Vault $vault,
    User $owner,
    User $collaborator,
    string $path,
    string $baseContent,
    string $ourContent,
    string $theirContent,
): array {
    $sync = app(SyncUploadAction::class);

    $v1 = $sync->execute($vault, $owner, 'Mac Alice', $path, $baseContent, 0);
    $v2 = $sync->execute($vault, $owner, 'Mac Alice', $path, $ourContent, (int) $v1['version']);
    $conflict = $sync->execute($vault, $collaborator, 'Linux Bob', $path, $theirContent, (int) $v1['version']);

    return [
        'canonical_path' => $path,
        'conflict_path' => (string) $conflict['path'],
        'canonical_version' => (int) $v2['version'],
    ];
}

test('AiConflictMergeService semantically merges conflicting hunks, preserves clean hunks, logs usage, and does not write disk', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.openai.com/v1/chat/completions' => function (HttpRequest $request) {
            $body = (string) json_encode($request->data());
            expect($body)->toContain('Roadmap.md');

            return Http::response([
                'model' => 'gpt-4o-mini',
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode([
                            'summary' => 'Combined Alice budget item and Bob launch checklist while preserving shared sections.',
                            'hunks' => [[
                                'hunk_id' => 1,
                                'strategy' => 'combine_both',
                                'resolved_text' => "- [x] Finalize Q4 budget\n- [ ] Publish launch notes",
                                'rationale' => 'Merged both non-contradictory sprint tasks.',
                            ]],
                        ]),
                    ],
                    'finish_reason' => 'stop',
                ]],
                'usage' => ['prompt_tokens' => 120, 'completion_tokens' => 45],
            ], 200);
        },
    ]);

    ['owner' => $owner, 'collaborator' => $collaborator, 'team' => $team, 'vault' => $vault] = createSmartMergeFixture();

    $seeded = seedConflictingNote(
        vault: $vault,
        owner: $owner,
        collaborator: $collaborator,
        path: 'Roadmap.md',
        baseContent: "# Q4 Roadmap\n- [ ] Finalize budget\n## Shared Notes\nStay aligned on releases.",
        ourContent: "# Q4 Roadmap\n- [x] Finalize Q4 budget\n## Shared Notes\nStay aligned on releases.",
        theirContent: "# Q4 Roadmap\n- [ ] Publish launch notes\n## Shared Notes\nStay aligned on releases.",
    );

    $service = app(AiConflictMergeService::class);
    $result = $service->suggestMerge($vault, $owner, $seeded['conflict_path'], 'Roadmap.md');

    expect($result['used_ai'])->toBeTrue()
        ->and($result['had_unmerged_markers'])->toBeFalse()
        ->and($result['canonical_path'])->toBe('Roadmap.md')
        ->and($result['conflict_path'])->toBe($seeded['conflict_path'])
        ->and($result['merged_content'])->toBe(
            "# Q4 Roadmap\n- [x] Finalize Q4 budget\n- [ ] Publish launch notes\n## Shared Notes\nStay aligned on releases."
        )
        ->and($result['resolutions'])->toHaveCount(1)
        ->and($result['resolutions'][0]['strategy'])->toBe('combine_both');

    // Disk canonical file must remain untouched until explicit user confirmation
    $canonical = $vault->files()->where('path', 'Roadmap.md')->where('is_deleted', false)->firstOrFail();
    expect($canonical->getContents())->toContain('- [x] Finalize Q4 budget')
        ->and($canonical->getContents())->not->toContain('Publish launch notes');

    // Usage log recorded under smart_merge feature
    $usageLog = AiUsageLog::query()->where('team_id', $team->id)->where('feature', 'smart_merge')->first();
    expect($usageLog)->not->toBeNull()
        ->and($usageLog->input_tokens)->toBe(120)
        ->and($usageLog->output_tokens)->toBe(45);
});

test('AiConflictMergeService uses zero-conflict fast path without calling AI gateway', function () {
    Http::preventStrayRequests();
    Http::fake();

    ['owner' => $owner, 'collaborator' => $collaborator, 'vault' => $vault] = createSmartMergeFixture();

    $seeded = seedConflictingNote(
        vault: $vault,
        owner: $owner,
        collaborator: $collaborator,
        path: 'Notes/CleanMerge.md',
        baseContent: "Title\nSection A\nSection B\nFooter",
        ourContent: "Title\nSection A edited by Alice\nSection B\nFooter",
        theirContent: "Title\nSection A\nSection B edited by Bob\nFooter",
    );

    $result = app(AiConflictMergeService::class)->suggestMerge($vault, $owner, $seeded['conflict_path']);

    Http::assertNothingSent();

    expect($result['used_ai'])->toBeFalse()
        ->and($result['model'])->toBe('three-way-diff')
        ->and($result['conflict_count'])->toBe(0)
        ->and($result['merged_content'])->toContain('Section A edited by Alice')
        ->and($result['merged_content'])->toContain('Section B edited by Bob');
});

test('AiConflictMergeService enforces plan, E2EE, excluded path, read_only ACL, and invalid JSON guards', function () {
    $service = app(AiConflictMergeService::class);

    // 1. Free plan blocked
    ['owner' => $freeOwner, 'collaborator' => $freeCollab, 'vault' => $freeVault] = createSmartMergeFixture(plan: 'free');
    $freeSeed = seedConflictingNote($freeVault, $freeOwner, $freeCollab, 'PlanTest.md', "Base\nLine", "Base\nOurs", "Base\nTheirs");
    expect(fn () => $service->suggestMerge($freeVault, $freeOwner, $freeSeed['conflict_path']))
        ->toThrow(AiException::class, 'Your workspace plan does not include AI Smart Merge.');

    // 2. E2EE vault blocked (create plain then flip flag to simulate E2EE vault with files)
    ['owner' => $e2eeOwner, 'collaborator' => $e2eeCollab, 'vault' => $e2eeVault] = createSmartMergeFixture();
    $e2eeSeed = seedConflictingNote($e2eeVault, $e2eeOwner, $e2eeCollab, 'Encrypted.md', "Base\nA", "Base\nB", "Base\nC");
    $e2eeVault->forceFill(['is_e2ee' => true])->save();
    expect(fn () => $service->suggestMerge($e2eeVault->fresh(), $e2eeOwner, $e2eeSeed['conflict_path']))
        ->toThrow(AiException::class, 'AI Smart Merge is disabled on Zero-Knowledge E2EE vaults.');

    // 3. Excluded path & read_only ACL & invalid JSON
    Http::preventStrayRequests();
    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'model' => 'gpt-4o-mini',
            'choices' => [[
                'message' => ['role' => 'assistant', 'content' => 'not valid json output'],
                'finish_reason' => 'stop',
            ]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
        ], 200),
    ]);

    ['owner' => $owner, 'collaborator' => $collab, 'vault' => $vault] = createSmartMergeFixture();
    $excludedSeed = seedConflictingNote($vault, $owner, $collab, 'Private/Secret.md', "Base\nA", "Base\nB", "Base\nC");
    expect(fn () => $service->suggestMerge($vault, $owner, $excludedSeed['conflict_path']))
        ->toThrow(AiException::class, 'Path is excluded from AI access.');

    VaultPermission::create([
        'vault_id' => $vault->id,
        'user_id' => $collab->id,
        'path' => 'Docs',
        'permission' => 'read_only',
        'is_folder' => true,
    ]);
    $aclSeed = seedConflictingNote($vault, $owner, $owner, 'Docs/Spec.md', "Base\nA", "Base\nB", "Base\nC");
    expect(fn () => $service->suggestMerge($vault, $collab, $aclSeed['conflict_path']))
        ->toThrow(AiException::class, 'Write permission is required');

    // 4. Malformed LLM JSON returns 502 INVALID_AI_OUTPUT
    expect(fn () => $service->suggestMerge($vault, $owner, $aclSeed['conflict_path']))
        ->toThrow(AiException::class, 'AI Smart Merge returned an invalid structured response.');
});

test('smart-merge API endpoint returns merged preview for device tokens and blocks MCP and read-only tokens', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'model' => 'gpt-4o-mini',
            'choices' => [[
                'message' => [
                    'role' => 'assistant',
                    'content' => json_encode([
                        'summary' => 'Kept both checklist edits.',
                        'hunks' => [[
                            'hunk_id' => 1,
                            'strategy' => 'combine_both',
                            'resolved_text' => "- Ours\n- Theirs",
                            'rationale' => 'Combined items.',
                        ]],
                    ]),
                ],
                'finish_reason' => 'stop',
            ]],
            'usage' => ['prompt_tokens' => 30, 'completion_tokens' => 20],
        ], 200),
    ]);

    ['owner' => $owner, 'collaborator' => $collab, 'team' => $team, 'vault' => $vault] = createSmartMergeFixture();
    $seeded = seedConflictingNote($vault, $owner, $collab, 'Tasks.md', "# Tasks\n- Base", "# Tasks\n- Ours", "# Tasks\n- Theirs");

    $writeToken = DeviceToken::createToken($owner, $team, 'Desktop App', 'mac', 'read_write')['plain_token'];
    $readOnlyToken = DeviceToken::createToken($owner, $team, 'Reader App', 'ios', 'read_only')['plain_token'];
    $mcpToken = DeviceToken::createToken($owner, $team, 'Cursor MCP', DeviceToken::PLATFORM_MCP, 'mcp_write')['plain_token'];

    $this->withHeader('Authorization', "Bearer {$mcpToken}")
        ->postJson("/api/v1/vaults/{$vault->slug}/conflicts/smart-merge", [
            'conflict_path' => $seeded['conflict_path'],
        ])
        ->assertForbidden();

    $this->withHeader('Authorization', "Bearer {$readOnlyToken}")
        ->postJson("/api/v1/vaults/{$vault->slug}/conflicts/smart-merge", [
            'conflict_path' => $seeded['conflict_path'],
        ])
        ->assertForbidden();

    $this->withHeader('Authorization', "Bearer {$writeToken}")
        ->postJson("/api/v1/vaults/{$vault->slug}/conflicts/smart-merge", [
            'conflict_path' => $seeded['conflict_path'],
            'canonical_path' => 'Tasks.md',
            'instructions' => 'Keep both bullets',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('used_ai', true)
        ->assertJsonPath('merged_content', "# Tasks\n- Ours\n- Theirs");
});

test('conflict sandbox modal runs Smart Merge with AI, allows reconciliation, and hides AI UI when disabled', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'model' => 'gpt-4o-mini',
            'choices' => [[
                'message' => [
                    'role' => 'assistant',
                    'content' => json_encode([
                        'summary' => 'Reconciled Alice and Bob bullets into one list.',
                        'hunks' => [[
                            'hunk_id' => 1,
                            'strategy' => 'combine_both',
                            'resolved_text' => "- Bullet A\n- Bullet B",
                            'rationale' => 'Combined both bullets.',
                        ]],
                    ]),
                ],
                'finish_reason' => 'stop',
            ]],
            'usage' => ['prompt_tokens' => 40, 'completion_tokens' => 25],
        ], 200),
    ]);

    ['owner' => $owner, 'collaborator' => $collab, 'vault' => $vault] = createSmartMergeFixture();
    $seeded = seedConflictingNote($vault, $owner, $collab, 'Meeting.md', "# Notes\n- Original", "# Notes\n- Bullet A", "# Notes\n- Bullet B");

    Livewire::actingAs($owner)
        ->test('pages::vaults.show', ['vault' => $vault])
        ->call('openConflictSandbox', $seeded['conflict_path'])
        ->assertSet('canUseSmartMerge', true)
        ->assertSee('Smart Merge with AI')
        ->set('conflictAiInstructions', 'Combine all bullets cleanly')
        ->call('runSmartMerge')
        ->assertSet('conflictAiSummary', 'Reconciled Alice and Bob bullets into one list.')
        ->assertSet('conflictReconciledContent', "# Notes\n- Bullet A\n- Bullet B")
        ->call('executeConflictResolution')
        ->assertHasNoErrors();

    $canonical = $vault->files()->where('path', 'Meeting.md')->where('is_deleted', false)->firstOrFail();
    $conflictCopy = $vault->files()->where('path', $seeded['conflict_path'])->firstOrFail();

    expect($canonical->getContents())->toBe("# Notes\n- Bullet A\n- Bullet B")
        ->and($conflictCopy->is_deleted)->toBeTrue();

    // Verify AI UI stays hidden when server AI is off on the vault
    $secondSeed = seedConflictingNote($vault, $owner, $collab, 'Meeting2.md', "# Notes\n- A", "# Notes\n- B", "# Notes\n- C");
    $vault->aiSetting()->update(['ai_mode' => 'off']);

    Livewire::actingAs($owner)
        ->test('pages::vaults.show', ['vault' => $vault->fresh()])
        ->call('openConflictSandbox', $secondSeed['conflict_path'])
        ->assertSet('canUseSmartMerge', false)
        ->assertDontSee('Smart Merge with AI');
});

test('smart-merge API merges unsynced local conflict snapshots and still blocks E2EE vaults', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'model' => 'gpt-4o-mini',
            'choices' => [[
                'message' => [
                    'role' => 'assistant',
                    'content' => json_encode([
                        'summary' => 'Reconciled local unsynced conflict edits.',
                        'hunks' => [[
                            'hunk_id' => 1,
                            'strategy' => 'combine_both',
                            'resolved_text' => "- Keep server goal\n- Add local milestone",
                            'rationale' => 'Preserved both changes.',
                        ]],
                    ]),
                ],
                'finish_reason' => 'stop',
            ]],
            'usage' => ['prompt_tokens' => 25, 'completion_tokens' => 20],
        ], 200),
    ]);

    ['owner' => $owner, 'team' => $team, 'vault' => $vault] = createSmartMergeFixture();
    $sync = app(SyncUploadAction::class);
    $v1 = $sync->execute($vault, $owner, 'Mac', 'Plans/Sprint.md', "# Sprint Plan\n- Base", baseVersion: 0);
    $sync->execute($vault, $owner, 'Mac', 'Plans/Sprint.md', "# Sprint Plan\n- Keep server goal", baseVersion: (int) $v1['version']);

    $writeToken = DeviceToken::createToken($owner, $team, 'Desktop Obsidian', 'mac', 'read_write')['plain_token'];

    // Conflict copy does NOT exist on server yet, but local content is provided cleanly
    $this->withHeader('Authorization', "Bearer {$writeToken}")
        ->postJson("/api/v1/vaults/{$vault->slug}/conflicts/smart-merge", [
            'conflict_path' => 'Plans/Sprint.sync-conflict-20261122.md',
            'canonical_path' => 'Plans/Sprint.md',
            'canonical_content' => "# Sprint Plan\n- Keep server goal",
            'conflict_content' => "# Sprint Plan\n- Add local milestone",
            'instructions' => 'Preserve both items',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('used_ai', true)
        ->assertJsonPath('merged_content', "# Sprint Plan\n- Keep server goal\n- Add local milestone");

    // E2EE vault MUST STILL block server smart-merge even when inline content is provided
    $e2eeVault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Encrypted Smart Merge Vault',
        'default_permission' => 'read_write',
        'created_by' => $owner->id,
        'is_e2ee' => true,
    ]);

    $this->withHeader('Authorization', "Bearer {$writeToken}")
        ->postJson("/api/v1/vaults/{$e2eeVault->slug}/conflicts/smart-merge", [
            'conflict_path' => 'Plans/Sprint.sync-conflict-20261122.md',
            'canonical_path' => 'Plans/Sprint.md',
            'canonical_content' => 'ciphertext',
            'conflict_content' => 'ciphertext 2',
        ])
        ->assertForbidden()
        ->assertJsonPath('error', 'E2EE_BLOCKED');
});
