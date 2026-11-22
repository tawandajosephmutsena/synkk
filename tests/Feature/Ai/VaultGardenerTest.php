<?php

use App\Actions\Vaults\SyncUploadAction;
use App\Enums\TeamRole;
use App\Exceptions\AiException;
use App\Jobs\RunVaultGardenerJob;
use App\Models\AiProposal;
use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\TeamAiConfig;
use App\Models\TeamNotification;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultAiSetting;
use App\Models\VaultFile;
use App\Models\VaultPermission;
use App\Services\Ai\AiProposalService;
use App\Services\Ai\VaultGardenerService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
});

function createGardenerFixture(
    string $plan = 'pro_ltd',
    string $aiMode = 'server',
    bool $isE2ee = false,
    bool $gardenerEnabled = true,
    string $schedule = 'debounced',
    bool $graphLinker = false,
    bool $driftCheck = false,
    bool $directInboxFrontmatter = false,
): array {
    $owner = User::factory()->create(['name' => 'Grace Owner']);
    $member = User::factory()->create(['name' => 'Hank Member']);
    $team = Team::factory()->create(['plan' => $plan]);
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $owner->update(['current_team_id' => $team->id]);

    TeamAiConfig::create([
        'team_id' => $team->id,
        'is_enabled' => true,
        'provider_mode' => 'byok',
        'chat_provider' => 'openai',
        'chat_model' => 'gpt-4o-mini',
        'fast_model' => 'gpt-4o-mini',
        'chat_api_key' => 'sk-test-'.str_repeat('c3', 12),
        'embedding_provider' => 'none',
    ]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Gardener Vault',
        'default_permission' => 'read_write',
        'created_by' => $owner->id,
        'is_e2ee' => $isE2ee,
    ]);

    VaultAiSetting::create([
        'vault_id' => $vault->id,
        'ai_mode' => $aiMode,
        'excluded_paths' => ['Private/**'],
        'gardener_enabled' => $gardenerEnabled,
        'inbox_folders' => ['Inbox'],
        'gardener_debounce_minutes' => 5,
        'gardener_graph_linker' => $graphLinker,
        'gardener_contradiction_check' => $driftCheck,
        'gardener_schedule' => $schedule,
        'allow_direct_inbox_frontmatter' => $directInboxFrontmatter,
    ]);

    return compact('owner', 'member', 'team', 'vault');
}

function fakeGardenerLlm(array $overrides = []): void
{
    Http::preventStrayRequests();
    Http::fake([
        'https://api.openai.com/v1/chat/completions' => function (HttpRequest $request) use ($overrides) {
            $system = (string) ($request->data()['messages'][0]['content'] ?? '');

            if (str_contains($system, 'Inbox Auto-Triage')) {
                $payload = $overrides['triage'] ?? [
                    'needs_triage' => true,
                    'tags' => ['architecture', 'sync'],
                    'summary' => 'Rough architecture notes for incremental delta sync.',
                    'suggested_destination_folder' => 'Projects',
                    'rationale' => 'Contains project architecture plans belonging in Projects.',
                ];
            } elseif (str_contains($system, 'Graph Linker')) {
                $payload = $overrides['linker'] ?? [
                    'links' => [
                        ['path' => 'Projects/SyncProtocol.md', 'rationale' => 'Core sync architecture reference'],
                        ['path' => 'NonExistent/Hallucinated.md', 'rationale' => 'Should be rejected'],
                    ],
                ];
            } else {
                $payload = $overrides['drift'] ?? [
                    'has_drift' => true,
                    'severity' => 'high',
                    'summary' => 'Timeout documented as 10s here conflicts with 60s in Projects/SyncProtocol.md.',
                    'conflicting_paths' => ['Projects/SyncProtocol.md'],
                    'proposed_callout' => 'Sync timeout says 10s here, whereas Projects/SyncProtocol.md specifies 60s.',
                    'rationale' => 'Conflicting operational timeout values detected.',
                ];
            }

            return Http::response([
                'model' => 'gpt-4o-mini',
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode($payload),
                    ],
                    'finish_reason' => 'stop',
                ]],
                'usage' => ['prompt_tokens' => 80, 'completion_tokens' => 40],
            ], 200);
        },
    ]);
}

test('inbox auto-triage stages move proposal with frontmatter and approving moves note cleanly', function () {
    fakeGardenerLlm();

    ['owner' => $owner, 'vault' => $vault] = createGardenerFixture();

    $sync = app(SyncUploadAction::class);
    $sync->execute($vault, $owner, 'Mac', 'Projects/Existing.md', '# Existing Project Note');
    $sync->execute($vault, $owner, 'Mac', 'Inbox/AlphaSpec.md', "# Alpha Spec\nDesign delta sync checks.");

    $inboxFile = $vault->files()->where('path', 'Inbox/AlphaSpec.md')->firstOrFail();

    $result = app(VaultGardenerService::class)->runForFile($vault, $inboxFile, $owner);

    expect($result['skipped'])->toBeFalse()
        ->and($result['proposals_created'])->toBe(1)
        ->and($result['direct_updates'])->toBe(0);

    $proposal = AiProposal::findOrFail($result['proposal_ids'][0]);
    expect($proposal->source)->toBe('gardener_triage')
        ->and($proposal->action)->toBe('move')
        ->and($proposal->target_path)->toBe('Inbox/AlphaSpec.md')
        ->and($proposal->destination_path)->toBe('Projects/AlphaSpec.md')
        ->and($proposal->proposed_content)->toContain('tags: [architecture, sync]')
        ->and($proposal->proposed_content)->toContain('summary: "Rough architecture notes for incremental delta sync."');

    // Approving move writes destination, soft-deletes source, and records deletion tombstone
    $approval = app(AiProposalService::class)->approve($proposal, $owner);
    expect($approval['proposal']->status)->toBe('approved');

    $movedFile = $vault->files()->where('path', 'Projects/AlphaSpec.md')->where('is_deleted', false)->first();
    $oldFile = $vault->files()->where('path', 'Inbox/AlphaSpec.md')->firstOrFail();

    expect($movedFile)->not->toBeNull()
        ->and($movedFile->getContents())->toContain('tags: [architecture, sync]')
        ->and($oldFile->is_deleted)->toBeTrue()
        ->and($vault->changeLogs()->where('path', 'Inbox/AlphaSpec.md')->where('action', 'deleted')->exists())->toBeTrue();
});

test('allow_direct_inbox_frontmatter writes metadata directly without infinite loop and stages move separately', function () {
    Queue::fake();
    fakeGardenerLlm();

    ['owner' => $owner, 'vault' => $vault] = createGardenerFixture(directInboxFrontmatter: true);

    $sync = app(SyncUploadAction::class);
    $sync->execute($vault, $owner, 'Mac', 'Inbox/Capture.md', "---\nauthor: Grace\n---\n# Quick Capture\nImportant notes.");

    Queue::assertPushed(RunVaultGardenerJob::class, 1);

    $file = $vault->files()->where('path', 'Inbox/Capture.md')->firstOrFail();
    $result = app(VaultGardenerService::class)->runForFile($vault, $file, $owner);

    expect($result['direct_updates'])->toBe(1)
        ->and($result['proposals_created'])->toBe(1);

    // Direct upload used 'Synkk AI Gardener', so NO additional debounced gardener job was queued
    Queue::assertPushed(RunVaultGardenerJob::class, 1);

    $updated = $file->fresh();
    expect($updated->getContents())->toContain('author: Grace')
        ->and($updated->getContents())->toContain('tags: [architecture, sync]')
        ->and($updated->getContents())->toContain('summary: "Rough architecture notes');

    $moveProposal = AiProposal::findOrFail($result['proposal_ids'][0]);
    expect($moveProposal->action)->toBe('move')
        ->and($moveProposal->destination_path)->toBe('Projects/Capture.md');
});

test('graph linker filters hallucinated paths, drift detector alerts team, and reruns supersede stale proposals', function () {
    fakeGardenerLlm();

    ['owner' => $owner, 'vault' => $vault] = createGardenerFixture(graphLinker: true, driftCheck: true);

    $sync = app(SyncUploadAction::class);
    $sync->execute($vault, $owner, 'Mac', 'Projects/SyncProtocol.md', '# Sync Protocol\nTimeout is 60s across devices.');
    $sync->execute($vault, $owner, 'Mac', 'Engineering/Checklist.md', "# Checklist\nConfigure timeout to 10s during upload.");

    $targetFile = $vault->files()->where('path', 'Engineering/Checklist.md')->firstOrFail();

    // Seed an already-existing pending linker proposal to confirm superseding
    $staleProposal = AiProposal::create([
        'team_id' => $vault->team_id,
        'vault_id' => $vault->id,
        'created_by_user_id' => $owner->id,
        'source' => 'gardener_links',
        'action' => 'update',
        'target_path' => 'Engineering/Checklist.md',
        'proposed_content' => '# Stale link suggestion',
        'status' => 'pending',
    ]);

    $result = app(VaultGardenerService::class)->runForFile($vault, $targetFile, $owner);

    expect($result['proposals_created'])->toBe(2)
        ->and($result['drift_alerts'])->toBe(1)
        ->and($staleProposal->fresh()->status)->toBe('superseded');

    $linkProposal = AiProposal::query()
        ->where('vault_id', $vault->id)
        ->where('source', 'gardener_links')
        ->where('status', 'pending')
        ->firstOrFail();

    expect($linkProposal->proposed_content)->toContain('[[Projects/SyncProtocol]]')
        ->and($linkProposal->proposed_content)->not->toContain('Hallucinated');

    $driftProposal = AiProposal::query()
        ->where('vault_id', $vault->id)
        ->where('source', 'gardener_drift')
        ->where('status', 'pending')
        ->firstOrFail();

    expect($driftProposal->proposed_content)->toContain('> [!warning] Potential Knowledge Drift')
        ->and($driftProposal->proposed_content)->toContain('[[Projects/SyncProtocol]]');

    $notification = TeamNotification::query()
        ->where('team_id', $vault->team_id)
        ->where('type', 'ai_gardener')
        ->first();

    expect($notification)->not->toBeNull()
        ->and($notification->title)->toContain('Checklist.md');
});

test('sync upload schedules debounced gardener job cleanly and stale debounce tokens exit without LLM calls', function () {
    Queue::fake();
    Http::preventStrayRequests();
    Http::fake();

    ['owner' => $owner, 'vault' => $vault] = createGardenerFixture(schedule: 'debounced');

    $sync = app(SyncUploadAction::class);
    $sync->execute($vault, $owner, 'Mac', 'Inbox/Debounced.md', '# Draft note');

    /** @var VaultFile $file */
    $file = $vault->files()->where('path', 'Inbox/Debounced.md')->firstOrFail();

    Queue::assertPushedOn('ai-background', RunVaultGardenerJob::class, function (RunVaultGardenerJob $job) use ($vault, $file): bool {
        return $job->vaultId === $vault->id
            && $job->vaultFileId === $file->id
            && $job->delay !== null;
    });

    // Running job with stale token must exit early without hitting LLM
    $staleJob = new RunVaultGardenerJob($vault->id, $file->id, 'stale-ulid-token');
    app()->call([$staleJob, 'handle']);
    Http::assertNothingSent();

    // Disabling gardener stops SyncUploadAction from queuing any RunVaultGardenerJob
    Queue::fake();
    $vault->aiSetting()->update(['gardener_enabled' => false]);
    $sync->execute($vault->fresh(), $owner, 'Mac', 'Inbox/NoGarden.md', '# Disabled');
    Queue::assertNotPushed(RunVaultGardenerJob::class);
});

test('vault gardener skips excluded and hidden notes and blocks E2EE and Free plans', function () {
    Http::preventStrayRequests();
    Http::fake();

    $service = app(VaultGardenerService::class);

    ['owner' => $owner, 'member' => $member, 'vault' => $vault] = createGardenerFixture();
    VaultPermission::create([
        'vault_id' => $vault->id,
        'user_id' => $member->id,
        'path' => 'Executive',
        'permission' => 'hidden',
        'is_folder' => true,
    ]);

    $sync = app(SyncUploadAction::class);
    $sync->execute($vault, $owner, 'Mac', 'Private/Secret.md', '# Secret');
    $sync->execute($vault, $owner, 'Mac', 'Executive/Board.md', '# Board');

    $privateFile = $vault->files()->where('path', 'Private/Secret.md')->firstOrFail();
    $hiddenFile = $vault->files()->where('path', 'Executive/Board.md')->firstOrFail();

    expect($service->runForFile($vault, $privateFile, $owner)['skipped'])->toBeTrue()
        ->and($service->runForFile($vault, $hiddenFile, $owner)['skipped'])->toBeTrue();
    Http::assertNothingSent();

    ['owner' => $freeOwner, 'vault' => $freeVault] = createGardenerFixture(plan: 'free');
    expect(fn () => $service->runForVault($freeVault, $freeOwner))
        ->toThrow(AiException::class, 'Your workspace plan does not include Vault Gardener.');

    ['owner' => $e2eeOwner, 'vault' => $e2eeVault] = createGardenerFixture(isE2ee: true);
    expect(fn () => $service->runForVault($e2eeVault, $e2eeOwner))
        ->toThrow(AiException::class, 'Vault Gardener is disabled on Zero-Knowledge E2EE vaults.');
});

test('ai:run-gardener command and gardener API endpoint honor schedules, force flag, and MCP restrictions', function () {
    fakeGardenerLlm();

    ['owner' => $owner, 'team' => $team, 'vault' => $vault] = createGardenerFixture(schedule: 'nightly');

    app(SyncUploadAction::class)->execute($vault, $owner, 'Mac', 'Projects/Ref.md', '# Ref');
    app(SyncUploadAction::class)->execute($vault, $owner, 'Mac', 'Inbox/Nightly.md', '# Nightly Capture');

    // 1. First scheduled run processes the vault
    $this->artisan('ai:run-gardener')
        ->expectsOutputToContain('Vaults processed: 1, Proposals staged: 1, Skipped: 0, Failed: 0')
        ->assertSuccessful();

    // 2. Immediate repeat without --force skips because last_gardened_at < 12h
    $this->artisan('ai:run-gardener')
        ->expectsOutputToContain('Vaults processed: 0, Proposals staged: 0, Skipped: 1, Failed: 0')
        ->assertSuccessful();

    // 3. --vault + --force runs immediately
    $this->artisan("ai:run-gardener --vault={$vault->slug} --force")
        ->expectsOutputToContain('Vaults processed: 1')
        ->assertSuccessful();

    // 4. API endpoint allows device token and rejects MCP tokens
    $deviceToken = DeviceToken::createToken($owner, $team, 'Desktop App', 'mac', 'read_write')['plain_token'];
    $mcpToken = DeviceToken::createToken($owner, $team, 'Claude MCP', DeviceToken::PLATFORM_MCP, 'mcp_propose')['plain_token'];

    $this->withHeader('Authorization', "Bearer {$mcpToken}")
        ->postJson("/api/v1/vaults/{$vault->slug}/ai/gardener/run")
        ->assertForbidden();

    $this->withHeader('Authorization', "Bearer {$deviceToken}")
        ->postJson("/api/v1/vaults/{$vault->slug}/ai/gardener/run", ['path' => 'Inbox/Nightly.md'])
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('proposals_created', 1);
});

test('team settings UI configures gardener and proposal inbox triggers Run Gardener Now and displays move destination', function () {
    fakeGardenerLlm();

    ['owner' => $owner, 'vault' => $vault] = createGardenerFixture(gardenerEnabled: false, schedule: 'manual');

    app(SyncUploadAction::class)->execute($vault, $owner, 'Mac', 'Projects/Guide.md', '# Project Guide');
    app(SyncUploadAction::class)->execute($vault, $owner, 'Mac', 'Inbox/Idea.md', '# New Feature Idea');

    Livewire::actingAs($owner)
        ->test('pages::teams.ai-addon-settings', ['team' => $vault->team])
        ->set("vaultSettings.{$vault->id}.ai_mode", 'server')
        ->set("vaultSettings.{$vault->id}.gardener_enabled", true)
        ->set("vaultSettings.{$vault->id}.gardener_schedule", 'nightly')
        ->set("vaultSettings.{$vault->id}.inbox_folders_text", 'Inbox, Scratch')
        ->set("vaultSettings.{$vault->id}.gardener_debounce_minutes", 12)
        ->set("vaultSettings.{$vault->id}.gardener_graph_linker", true)
        ->set("vaultSettings.{$vault->id}.gardener_contradiction_check", false)
        ->set("vaultSettings.{$vault->id}.allow_direct_inbox_frontmatter", true)
        ->call('saveVaultSetting', $vault->id)
        ->call('runGardenerNow', $vault->id)
        ->assertHasNoErrors();

    $setting = $vault->aiSetting()->firstOrFail();
    expect($setting->gardener_enabled)->toBeTrue()
        ->and($setting->gardener_schedule)->toBe('nightly')
        ->and($setting->inbox_folders)->toBe(['Inbox', 'Scratch'])
        ->and($setting->gardener_debounce_minutes)->toBe(12)
        ->and($setting->gardener_graph_linker)->toBeTrue()
        ->and($setting->gardener_contradiction_check)->toBeFalse()
        ->and($setting->allow_direct_inbox_frontmatter)->toBeTrue();

    // Disable direct frontmatter to test Proposal Inbox Run Gardener Now + move rendering & approval
    $setting->update(['allow_direct_inbox_frontmatter' => false]);
    AiProposal::query()->where('vault_id', $vault->id)->delete();

    app(SyncUploadAction::class)->execute($vault, $owner, 'Mac', 'Inbox/Beta.md', '# Beta Notes');

    $inbox = Livewire::actingAs($owner)
        ->test('pages::vaults.ai-proposals-inbox', ['vault' => $vault->fresh()])
        ->assertSet('canRunGardener', true)
        ->call('runGardenerNow')
        ->assertDispatched('vault-proposals-updated')
        ->assertSee('→ Projects/Beta.md');

    $moveProposal = AiProposal::query()
        ->where('vault_id', $vault->id)
        ->where('target_path', 'Inbox/Beta.md')
        ->where('source', 'gardener_triage')
        ->where('status', 'pending')
        ->firstOrFail();

    $inbox->call('approveProposal', $moveProposal->id)
        ->assertHasNoErrors();

    expect($vault->files()->where('path', 'Projects/Beta.md')->where('is_deleted', false)->exists())->toBeTrue()
        ->and($vault->files()->where('path', 'Inbox/Beta.md')->firstOrFail()->is_deleted)->toBeTrue();
});
