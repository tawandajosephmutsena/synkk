<?php

use App\Actions\Vaults\SyncUploadAction;
use App\Enums\TeamRole;
use App\Exceptions\AiException;
use App\Mail\VaultDigestMail;
use App\Models\AiProposal;
use App\Models\AiUsageLog;
use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\TeamAiConfig;
use App\Models\TeamMessage;
use App\Models\TeamNotification;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultAiSetting;
use App\Models\VaultPermission;
use App\Services\Ai\VaultDigestService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
});

function createDigestFixture(
    string $plan = 'pro_ltd',
    string $aiMode = 'server',
    bool $isE2ee = false,
    string $digestSchedule = 'daily',
    ?array $delivery = null,
): array {
    $owner = User::factory()->create(['name' => 'Dana Owner']);
    $member = User::factory()->create(['name' => 'Evan Member']);
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
        'chat_api_key' => 'sk-test-'.str_repeat('b2', 12),
        'embedding_provider' => 'none',
    ]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Engineering Vault',
        'default_permission' => 'read_write',
        'created_by' => $owner->id,
        'is_e2ee' => $isE2ee,
    ]);

    VaultAiSetting::create([
        'vault_id' => $vault->id,
        'ai_mode' => $aiMode,
        'excluded_paths' => ['Private/**'],
        'digest_schedule' => $digestSchedule,
        'digest_folder' => 'Digests',
        'digest_delivery' => $delivery ?? [
            'vault_note' => true,
            'via_proposal' => true,
            'team_feed' => true,
            'email' => true,
        ],
    ]);

    return compact('owner', 'member', 'team', 'vault');
}

function fakeDigestChatResponse(string $headline = 'Q4 Architecture & API Progress'): void
{
    Http::preventStrayRequests();
    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'model' => 'gpt-4o-mini',
            'choices' => [[
                'message' => [
                    'role' => 'assistant',
                    'content' => json_encode([
                        'headline' => $headline,
                        'summary' => 'Team finalized [[Architecture/API.md]] endpoints and updated sprint priorities.',
                        'markdown_body' => "## Highlights\n- Finalized [[Architecture/API.md]] contracts.",
                    ]),
                ],
                'finish_reason' => 'stop',
            ]],
            'usage' => ['prompt_tokens' => 95, 'completion_tokens' => 55],
        ], 200),
    ]);
}

test('VaultDigestService stages digest proposal, posts team feed notifications, queues emails, and logs usage', function () {
    Mail::fake();
    fakeDigestChatResponse();

    ['owner' => $owner, 'member' => $member, 'team' => $team, 'vault' => $vault] = createDigestFixture();

    $sync = app(SyncUploadAction::class);
    $sync->execute($vault, $owner, 'MacBook', 'Architecture/API.md', "# API Contract\nAdded v2 endpoints.");
    $sync->execute($vault, $member, 'Linux', 'Sprint.md', "# Sprint\n- Shipped Phase 3");

    $result = app(VaultDigestService::class)->generateDigest($vault, $owner);

    expect($result['status'])->toBe('generated')
        ->and($result['changes_count'])->toBe(2)
        ->and($result['files_touched'])->toBe(2)
        ->and($result['headline'])->toBe('Q4 Architecture & API Progress')
        ->and($result['proposal_id'])->not->toBeNull()
        ->and($result['team_notification_id'])->not->toBeNull()
        ->and($result['team_message_id'])->not->toBeNull()
        ->and($result['emails_queued'])->toBe(2);

    $proposal = AiProposal::findOrFail($result['proposal_id']);
    expect($proposal->source)->toBe('digest')
        ->and($proposal->status)->toBe('pending')
        ->and($proposal->target_path)->toStartWith('Digests/')
        ->and($proposal->proposed_content)->toContain('type: synkk-digest')
        ->and($proposal->proposed_content)->toContain('[[Architecture/API.md]]');

    $notification = TeamNotification::findOrFail($result['team_notification_id']);
    expect($notification->type)->toBe('ai_digest')
        ->and($notification->title)->toContain('Q4 Architecture & API Progress');

    $message = TeamMessage::findOrFail($result['team_message_id']);
    expect($message->type)->toBe('ai_digest')
        ->and($message->author_name)->toBe('Synkk AI Digest');

    Mail::assertQueued(VaultDigestMail::class, 2);

    expect(AiUsageLog::query()->where('team_id', $team->id)->where('feature', 'digest')->count())->toBe(1)
        ->and($vault->aiSetting()->firstOrFail()->last_digest_at)->not->toBeNull();
});

test('VaultDigestMail renders headline, metrics, summary, and markdown body', function () {
    ['vault' => $vault] = createDigestFixture();

    $mailable = new VaultDigestMail(
        vault: $vault,
        period: 'daily',
        headline: 'Daily Vault Roundup',
        summary: 'Two key architecture specs updated.',
        markdownBody: "## Notes\n- Updated [[Architecture/API.md]]",
        changesCount: 4,
        filesTouched: 2,
        vaultUrl: 'https://synkk.test/demo',
    );

    $mailable->assertSeeInHtml('Daily Vault Roundup')
        ->assertSeeInHtml('4 changes across 2 files')
        ->assertSeeInHtml('Two key architecture specs updated.')
        ->assertSeeInText('Daily Vault Roundup')
        ->assertSeeInText('Architecture/API.md');
});

test('VaultDigestService supports direct vault note delivery when via_proposal is disabled', function () {
    Mail::fake();
    fakeDigestChatResponse('Direct Note Digest');

    ['owner' => $owner, 'vault' => $vault] = createDigestFixture(delivery: [
        'vault_note' => true,
        'via_proposal' => false,
        'team_feed' => false,
        'email' => false,
    ]);

    app(SyncUploadAction::class)->execute($vault, $owner, 'Mac', 'Design.md', "# Design\nUpdated tokens.");

    $result = app(VaultDigestService::class)->generateDigest($vault, $owner);

    expect($result['proposal_id'])->toBeNull()
        ->and($result['vault_file_version'])->toBeGreaterThanOrEqual(2)
        ->and($result['team_notification_id'])->toBeNull()
        ->and($result['emails_queued'])->toBe(0);

    $savedNote = $vault->files()->where('path', $result['note_path'])->where('is_deleted', false)->first();
    expect($savedNote)->not->toBeNull()
        ->and($savedNote->getContents())->toContain('# Direct Note Digest');

    expect(AiProposal::where('vault_id', $vault->id)->count())->toBe(0);
    Mail::assertNothingQueued();
});

test('VaultDigestService filters excluded paths, hidden ACL paths, digest folder notes, and skips LLM on no changes', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.openai.com/v1/chat/completions' => function (HttpRequest $request) {
            $userContent = (string) ($request->data()['messages'][1]['content'] ?? '');
            expect($userContent)->not->toContain('PrivateKeyValue')
                ->and($userContent)->not->toContain('HiddenExecSecret')
                ->and($userContent)->not->toContain('PriorDigestContent')
                ->and($userContent)->toContain('Public/Note.md')
                ->and($userContent)->toContain('Public Release Notes');

            return Http::response([
                'model' => 'gpt-4o-mini',
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode([
                            'headline' => 'Filtered Digest',
                            'summary' => 'Summarized only public activity.',
                            'markdown_body' => '- [[Public/Note.md]] updated.',
                        ]),
                    ],
                    'finish_reason' => 'stop',
                ]],
                'usage' => ['prompt_tokens' => 40, 'completion_tokens' => 20],
            ], 200);
        },
    ]);

    ['owner' => $owner, 'member' => $member, 'vault' => $vault] = createDigestFixture(delivery: [
        'vault_note' => true,
        'via_proposal' => true,
        'team_feed' => false,
        'email' => false,
    ]);

    VaultPermission::create([
        'vault_id' => $vault->id,
        'user_id' => $member->id,
        'path' => 'Executive',
        'permission' => 'hidden',
        'is_folder' => true,
    ]);

    $sync = app(SyncUploadAction::class);
    $sync->execute($vault, $owner, 'Mac', 'Private/Keys.md', '# PrivateKeyValue');
    $sync->execute($vault, $owner, 'Mac', 'Executive/Strategy.md', '# HiddenExecSecret');
    $sync->execute($vault, $owner, 'Mac', 'Digests/2026-11-20.md', '# PriorDigestContent');

    // Only excluded/hidden/digest logs exist -> no LLM call
    $emptyResult = app(VaultDigestService::class)->generateDigest($vault, $owner);
    expect($emptyResult['status'])->toBe('no_changes');
    Http::assertNothingSent();

    // Add 1 eligible public note -> only that public note reaches LLM
    $sync->execute($vault, $owner, 'Mac', 'Public/Note.md', '# Public Release Notes');
    $result = app(VaultDigestService::class)->generateDigest($vault, $owner);
    expect($result['status'])->toBe('generated')
        ->and($result['files_touched'])->toBe(1)
        ->and($result['changes_count'])->toBe(1);
});

test('VaultDigestService enforces E2EE and plan feature guards', function () {
    $service = app(VaultDigestService::class);

    ['owner' => $freeOwner, 'vault' => $freeVault] = createDigestFixture(plan: 'free');
    expect(fn () => $service->generateDigest($freeVault, $freeOwner))
        ->toThrow(AiException::class, 'Your workspace plan does not include Automated Team Digests.');

    ['owner' => $e2eeOwner, 'vault' => $e2eeVault] = createDigestFixture(isE2ee: true);
    expect(fn () => $service->generateDigest($e2eeVault, $e2eeOwner))
        ->toThrow(AiException::class, 'Automated Team Digests are disabled on Zero-Knowledge E2EE vaults.');
});

test('ai:generate-digests command respects due schedule windows and supports --force and --vault flags', function () {
    Mail::fake();
    fakeDigestChatResponse('Scheduled Digest Run');

    ['owner' => $owner, 'vault' => $vault] = createDigestFixture(
        digestSchedule: 'daily',
        delivery: ['vault_note' => true, 'via_proposal' => true, 'team_feed' => false, 'email' => false],
    );

    app(SyncUploadAction::class)->execute($vault, $owner, 'Mac', 'Journal.md', '# Today');

    // 1. First run is due immediately because last_digest_at is null
    $this->artisan('ai:generate-digests --period=daily')
        ->expectsOutputToContain('Generated: 1, Skipped: 0, Failed: 0')
        ->assertSuccessful();

    // 2. Second immediate run without --force skips because < 23h elapsed
    $this->artisan('ai:generate-digests --period=daily')
        ->expectsOutputToContain('Generated: 0, Skipped: 1, Failed: 0')
        ->assertSuccessful();

    // 3. New edit + --force + --vault triggers generation cleanly
    app(SyncUploadAction::class)->execute($vault, $owner, 'Mac', 'Journal2.md', '# Update');
    $this->artisan("ai:generate-digests --vault={$vault->slug} --force")
        ->expectsOutputToContain('Generated: 1, Skipped: 0, Failed: 0')
        ->assertSuccessful();
});

test('manual digest API endpoint works for device tokens and blocks MCP tokens', function () {
    Mail::fake();
    fakeDigestChatResponse('API Triggered Digest');

    ['owner' => $owner, 'team' => $team, 'vault' => $vault] = createDigestFixture(delivery: [
        'vault_note' => true,
        'via_proposal' => true,
        'team_feed' => false,
        'email' => false,
    ]);

    app(SyncUploadAction::class)->execute($vault, $owner, 'Mac', 'API/Spec.md', '# Spec');

    $deviceToken = DeviceToken::createToken($owner, $team, 'Obsidian Mac', 'mac', 'read_write')['plain_token'];
    $mcpToken = DeviceToken::createToken($owner, $team, 'Claude MCP', DeviceToken::PLATFORM_MCP, 'mcp_propose')['plain_token'];

    $this->withHeader('Authorization', "Bearer {$mcpToken}")
        ->postJson("/api/v1/vaults/{$vault->slug}/ai/digests/generate")
        ->assertForbidden();

    $this->withHeader('Authorization', "Bearer {$deviceToken}")
        ->postJson("/api/v1/vaults/{$vault->slug}/ai/digests/generate", ['period' => 'weekly'])
        ->assertOk()
        ->assertJsonPath('status', 'generated')
        ->assertJsonPath('period', 'weekly')
        ->assertJsonPath('headline', 'API Triggered Digest');
});

test('team AI settings UI configures vault digest schedule and triggers generateDigestNow', function () {
    Mail::fake();
    fakeDigestChatResponse('UI Triggered Digest');

    ['owner' => $owner, 'vault' => $vault] = createDigestFixture(
        digestSchedule: 'off',
        delivery: ['vault_note' => true, 'via_proposal' => true, 'team_feed' => true, 'email' => false],
    );

    app(SyncUploadAction::class)->execute($vault, $owner, 'Mac', 'Meetings/Sync.md', '# Sync Meeting Notes');

    // Backdate last_digest_at briefly to verify clean overwrite after UI save/generate
    $vault->aiSetting()->update(['last_digest_at' => now()->subDays(2)]);

    Livewire::actingAs($owner)
        ->test('pages::teams.ai-addon-settings', ['team' => $vault->team])
        ->set("vaultSettings.{$vault->id}.ai_mode", 'server')
        ->set("vaultSettings.{$vault->id}.digest_schedule", 'weekly')
        ->set("vaultSettings.{$vault->id}.digest_folder", 'WeeklySummaries')
        ->set("vaultSettings.{$vault->id}.digest_vault_note", true)
        ->set("vaultSettings.{$vault->id}.digest_via_proposal", true)
        ->set("vaultSettings.{$vault->id}.digest_team_feed", true)
        ->set("vaultSettings.{$vault->id}.digest_email", true)
        ->call('saveVaultSetting', $vault->id)
        ->call('generateDigestNow', $vault->id)
        ->assertHasNoErrors();

    $setting = $vault->aiSetting()->firstOrFail();
    expect($setting->digest_schedule)->toBe('weekly')
        ->and($setting->digest_folder)->toBe('WeeklySummaries')
        ->and($setting->digest_delivery)->toBe([
            'vault_note' => true,
            'via_proposal' => true,
            'team_feed' => true,
            'email' => true,
        ]);

    $proposal = AiProposal::where('vault_id', $vault->id)->where('source', 'digest')->first();
    expect($proposal)->not->toBeNull()
        ->and($proposal->target_path)->toStartWith('WeeklySummaries/');

    Mail::assertQueued(VaultDigestMail::class, 2);
});
