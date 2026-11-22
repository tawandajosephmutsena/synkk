<?php

use App\Actions\Vaults\SyncUploadAction;
use App\Enums\TeamRole;
use App\Exceptions\AiException;
use App\Models\AiUsageLog;
use App\Models\Team;
use App\Models\TeamAiConfig;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultAiSetting;
use App\Models\VaultPermission;
use App\Models\VaultPortal;
use App\Services\Ai\PortalAiService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
});

function createPortalAiFixture(
    string $plan = 'pro_ltd',
    string $aiMode = 'server',
    bool $isE2ee = false,
    bool $portalAiEnabled = true,
    bool $llmsTxtEnabled = true,
    string $rootPath = '/Docs',
    bool $isPublic = true,
    ?string $password = null,
    int $dailyLimit = 100,
): array {
    $owner = User::factory()->create(['name' => 'Maya Owner']);
    $member = User::factory()->create(['name' => 'Noah Member']);
    $team = Team::factory()->create(['plan' => $plan]);
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $owner->switchTeam($team);

    TeamAiConfig::create([
        'team_id' => $team->id,
        'is_enabled' => true,
        'provider_mode' => 'byok',
        'chat_provider' => 'openai',
        'chat_model' => 'gpt-4o-mini',
        'fast_model' => 'gpt-4o-mini',
        'chat_api_key' => 'sk-test-'.str_repeat('d4', 12),
        'embedding_provider' => 'none',
    ]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Docs Vault',
        'default_permission' => 'read_write',
        'created_by' => $owner->id,
        'is_e2ee' => $isE2ee,
    ]);

    VaultAiSetting::create([
        'vault_id' => $vault->id,
        'ai_mode' => $aiMode,
        'excluded_paths' => ['Docs/Private/**', 'Secrets/**'],
    ]);

    $portal = VaultPortal::create([
        'team_id' => $team->id,
        'vault_id' => $vault->id,
        'created_by' => $owner->id,
        'name' => 'Acme Developer Docs',
        'slug' => 'acme-docs-'.substr(md5((string) microtime(true).(string) mt_rand()), 0, 6),
        'description' => 'Official developer documentation hub.',
        'layout' => 'docs',
        'theme' => 'obsidian-noir',
        'is_public' => $isPublic,
        'password' => $password,
        'root_path' => $rootPath,
        'ai_enabled' => $portalAiEnabled,
        'ai_daily_question_limit' => $dailyLimit,
        'llms_txt_enabled' => $llmsTxtEnabled,
    ]);

    return compact('owner', 'member', 'team', 'vault', 'portal');
}

test('PortalAiService answers strictly within root_path while filtering excluded and hidden notes and logs portal billing', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.openai.com/v1/chat/completions' => function (HttpRequest $request) {
            $userContent = (string) ($request->data()['messages'][1]['content'] ?? '');
            expect($userContent)->toContain('Docs/GettingStarted.md')
                ->and($userContent)->not->toContain('OutsideRootSecret')
                ->and($userContent)->not->toContain('ExcludedDocsSecret')
                ->and($userContent)->not->toContain('HiddenAclDocsSecret');

            return Http::response([
                'model' => 'gpt-4o-mini',
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'To authenticate requests, pass an Authorization Bearer token as shown in Getting Started.',
                    ],
                    'finish_reason' => 'stop',
                ]],
                'usage' => ['prompt_tokens' => 85, 'completion_tokens' => 30],
            ], 200);
        },
    ]);

    ['owner' => $owner, 'member' => $member, 'team' => $team, 'vault' => $vault, 'portal' => $portal] = createPortalAiFixture();

    VaultPermission::create([
        'vault_id' => $vault->id,
        'user_id' => $member->id,
        'path' => 'Docs/InternalOnly',
        'permission' => 'hidden',
        'is_folder' => true,
    ]);

    $sync = app(SyncUploadAction::class);
    $sync->execute($vault, $owner, 'Mac', 'Docs/GettingStarted.md', "# Getting Started\nUse Authorization: Bearer tokens to sync.");
    $sync->execute($vault, $owner, 'Mac', 'Docs/Private/Keys.md', '# ExcludedDocsSecret');
    $sync->execute($vault, $owner, 'Mac', 'Docs/InternalOnly/Plan.md', '# HiddenAclDocsSecret');
    $sync->execute($vault, $owner, 'Mac', 'Internal/Strategy.md', '# OutsideRootSecret');

    $result = app(PortalAiService::class)->ask($portal, 'How do I authenticate API requests?', '203.0.113.10');

    expect($result['answer'])->toContain('Authorization Bearer')
        ->and($result['citations'])->not->toBeEmpty()
        ->and($result['citations'][0]['path'])->toBe('Docs/GettingStarted.md')
        ->and($result['remaining_today'])->toBe(99);

    $log = AiUsageLog::query()->where('portal_id', $portal->id)->where('feature', 'portal')->first();
    expect($log)->not->toBeNull()
        ->and($log->team_id)->toBe($team->id)
        ->and($log->input_tokens)->toBe(85);
});

test('PortalAiService enforces per-IP rate limiting and daily portal question caps', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'model' => 'gpt-4o-mini',
            'choices' => [[
                'message' => ['role' => 'assistant', 'content' => 'Answer'],
                'finish_reason' => 'stop',
            ]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
        ], 200),
    ]);

    ['owner' => $owner, 'vault' => $vault, 'portal' => $portal] = createPortalAiFixture(dailyLimit: 50);
    app(SyncUploadAction::class)->execute($vault, $owner, 'Mac', 'Docs/Faq.md', '# FAQ\nHelpful guide.');

    $service = app(PortalAiService::class);
    for ($i = 0; $i < PortalAiService::MAX_PER_IP_PER_MINUTE; $i++) {
        $service->ask($portal, "Question {$i}", '198.51.100.77');
    }

    expect(fn () => $service->ask($portal, 'Rate limited question', '198.51.100.77'))
        ->toThrow(AiException::class, 'Too many questions from your connection.');

    // Daily question cap across distinct IPs
    ['owner' => $owner2, 'vault' => $vault2, 'portal' => $cappedPortal] = createPortalAiFixture(dailyLimit: 2);
    app(SyncUploadAction::class)->execute($vault2, $owner2, 'Mac', 'Docs/Faq.md', '# FAQ\nHelpful guide.');

    $service->ask($cappedPortal, 'First daily question', '198.51.100.1');
    $service->ask($cappedPortal, 'Second daily question', '198.51.100.2');

    expect(fn () => $service->ask($cappedPortal, 'Third daily question', '198.51.100.3'))
        ->toThrow(AiException::class, 'This portal has reached its daily AI question limit.');
});

test('PortalAiService blocks opt-out portals, Free plans, E2EE vaults, and password-gated Livewire attempts while locked', function () {
    ['owner' => $ownerDisabled, 'vault' => $vaultDisabled, 'portal' => $portalDisabled] = createPortalAiFixture(portalAiEnabled: false);
    $service = app(PortalAiService::class);
    expect(fn () => $service->ask($portalDisabled, 'Hello?', '203.0.113.2'))
        ->toThrow(AiException::class, 'Ask This Portal AI is not enabled for this portal.');

    ['portal' => $freePortal] = createPortalAiFixture(plan: 'free');
    expect(fn () => $service->ask($freePortal, 'Hello?', '203.0.113.2'))
        ->toThrow(AiException::class, 'Your workspace plan does not include Portal AI Assistant.');

    ['portal' => $e2eePortal] = createPortalAiFixture(isE2ee: true);
    expect(fn () => $service->ask($e2eePortal, 'Hello?', '203.0.113.2'))
        ->toThrow(AiException::class, 'Portal AI is disabled on Zero-Knowledge E2EE vaults.');

    // Password-protected portal Livewire check
    Http::preventStrayRequests();
    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'model' => 'gpt-4o-mini',
            'choices' => [[
                'message' => ['role' => 'assistant', 'content' => 'Unlocked answer grounded in Docs/Welcome.md.'],
                'finish_reason' => 'stop',
            ]],
            'usage' => ['prompt_tokens' => 25, 'completion_tokens' => 15],
        ], 200),
    ]);

    ['owner' => $pwOwner, 'vault' => $pwVault, 'portal' => $pwPortal] = createPortalAiFixture(password: 'SecretPortalPass123!');
    app(SyncUploadAction::class)->execute($pwVault, $pwOwner, 'Mac', 'Docs/Welcome.md', '# Welcome\nPortal instructions.');

    auth()->logout();

    // Locked visitor cannot see Ask AI UI or invoke askPortalAi
    Livewire::test('pages::portals.show', ['slug' => $pwPortal->slug])
        ->assertSet('canAskPortalAi', false)
        ->assertDontSee('Ask this Portal')
        ->set('aiQuestionInput', 'Bypass attempt?')
        ->call('askPortalAi')
        ->assertForbidden();

    // After unlocking with valid password, Ask AI works cleanly
    Livewire::test('pages::portals.show', ['slug' => $pwPortal->slug])
        ->set('passwordInput', 'SecretPortalPass123!')
        ->call('unlock')
        ->assertSet('canAskPortalAi', true)
        ->assertSee('Ask this Portal')
        ->set('aiQuestionInput', 'What does Welcome say?')
        ->call('askPortalAi')
        ->assertSee('Unlocked answer grounded in Docs/Welcome.md.');
});

test('GET /p/{slug}/llms.txt enforces scoping, toggles, private membership, password gate, and E2EE', function () {
    ['owner' => $owner, 'member' => $member, 'vault' => $vault, 'portal' => $portal] = createPortalAiFixture();

    VaultPermission::create([
        'vault_id' => $vault->id,
        'user_id' => $member->id,
        'path' => 'Docs/Hidden',
        'permission' => 'hidden',
        'is_folder' => true,
    ]);

    $sync = app(SyncUploadAction::class);
    $sync->execute($vault, $owner, 'Mac', 'Docs/Intro.md', "# Welcome Guide\nSynkk publishes Obsidian hubs fast.");
    $sync->execute($vault, $owner, 'Mac', 'Docs/Private/Key.md', '# SecretExcluded');
    $sync->execute($vault, $owner, 'Mac', 'Docs/Hidden/Board.md', '# HiddenAcl');
    $sync->execute($vault, $owner, 'Mac', 'Outside/Note.md', '# OutsideRoot');

    // Public portal exposes filtered llms.txt index
    $response = $this->get("/p/{$portal->slug}/llms.txt")
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8');

    $body = (string) $response->getContent();
    expect($body)->toContain('# Acme Developer Docs')
        ->and($body)->toContain('Welcome Guide')
        ->and($body)->toContain('Docs/Intro.md')
        ->and($body)->not->toContain('SecretExcluded')
        ->and($body)->not->toContain('HiddenAcl')
        ->and($body)->not->toContain('OutsideRoot');

    // Opt-out toggle returns 404
    $portal->update(['llms_txt_enabled' => false]);
    $this->get("/p/{$portal->slug}/llms.txt")->assertNotFound();

    // Private portal requires authenticated team member
    $portal->update(['llms_txt_enabled' => true, 'is_public' => false]);
    auth()->logout();
    $this->get("/p/{$portal->slug}/llms.txt")->assertNotFound();

    $outsider = User::factory()->create();
    $this->actingAs($outsider)->get("/p/{$portal->slug}/llms.txt")->assertNotFound();
    $this->actingAs($member)->get("/p/{$portal->slug}/llms.txt")->assertOk();

    // Password-protected portal returns 403 before unlock, 200 after unlock
    $portal->update(['is_public' => true, 'password' => 'GatePass123!']);
    auth()->logout();
    $this->get("/p/{$portal->slug}/llms.txt")->assertForbidden();

    Livewire::test('pages::portals.show', ['slug' => $portal->slug])
        ->set('passwordInput', 'GatePass123!')
        ->call('unlock');

    $this->get("/p/{$portal->slug}/llms.txt")->assertOk();

    // E2EE vault blocks /llms.txt
    $vault->forceFill(['is_e2ee' => true])->save();
    $this->get("/p/{$portal->slug}/llms.txt")->assertNotFound();
});

test('portals studio persists AI and llms.txt settings and blocks AI on free plans and E2EE vaults', function () {
    ['owner' => $owner, 'team' => $team, 'vault' => $vault] = createPortalAiFixture();

    Livewire::actingAs($owner)
        ->test('pages::portals.index')
        ->set('vault_id', $vault->id)
        ->set('name', 'Customer Handbook')
        ->set('ai_enabled', true)
        ->set('ai_daily_question_limit', 250)
        ->set('llms_txt_enabled', false)
        ->call('savePortal')
        ->assertHasNoErrors();

    $saved = VaultPortal::query()->where('name', 'Customer Handbook')->firstOrFail();
    expect($saved->ai_enabled)->toBeTrue()
        ->and($saved->ai_daily_question_limit)->toBe(250)
        ->and($saved->llms_txt_enabled)->toBeFalse();

    // E2EE vault forces ai_enabled = false even if toggled
    $e2eeVault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Secret Vault',
        'default_permission' => 'read_write',
        'created_by' => $owner->id,
        'is_e2ee' => true,
    ]);

    Livewire::actingAs($owner)
        ->test('pages::portals.index')
        ->set('vault_id', $e2eeVault->id)
        ->set('name', 'E2EE Portal')
        ->set('ai_enabled', true)
        ->call('savePortal')
        ->assertHasNoErrors();

    $e2eePortal = VaultPortal::query()->where('name', 'E2EE Portal')->firstOrFail();
    expect($e2eePortal->ai_enabled)->toBeFalse();

    // Community Free plan forces ai_enabled = false even when passed directly
    ['owner' => $freeOwner, 'vault' => $freeVault] = createPortalAiFixture(plan: 'free', aiMode: 'server', portalAiEnabled: false);

    Livewire::actingAs($freeOwner)
        ->test('pages::portals.index')
        ->set('vault_id', $freeVault->id)
        ->set('name', 'Free Portal')
        ->set('ai_enabled', true)
        ->call('savePortal')
        ->assertHasNoErrors();

    $freePortal = VaultPortal::query()->where('name', 'Free Portal')->firstOrFail();
    expect($freePortal->ai_enabled)->toBeFalse();
});
