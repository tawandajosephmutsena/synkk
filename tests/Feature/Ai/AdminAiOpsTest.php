<?php

use App\Exceptions\AiException;
use App\Models\AiPlatformSetting;
use App\Models\AiUsageLog;
use App\Models\Team;
use App\Models\TeamAiConfig;
use App\Models\User;
use App\Services\Ai\AiQuotaService;
use Livewire\Livewire;

test('super admin AI Ops tab renders spend telemetry, toggles global kill switch, updates defaults, and grants credits', function () {
    $admin = User::factory()->asSuperAdmin()->create();
    $team = Team::factory()->create(['plan' => 'cloud_pro', 'name' => 'Acme Cloud Team']);

    TeamAiConfig::create([
        'team_id' => $team->id,
        'is_enabled' => true,
        'provider_mode' => 'managed',
        'trial_credits_microcents' => 200_000_000,
        'trial_granted_at' => now(),
    ]);

    AiUsageLog::create([
        'team_id' => $team->id,
        'feature' => 'copilot',
        'provider_mode' => 'managed',
        'provider' => 'openai_compatible',
        'model' => 'openai/gpt-4o-mini',
        'input_tokens' => 1000,
        'output_tokens' => 500,
        'cost_microcents' => 45_000_000,
        'redacted_secrets_count' => 2,
        'created_at' => now(),
    ]);

    $component = Livewire::actingAs($admin)
        ->test('pages::admin.dashboard')
        ->call('setTab', 'ai-ops')
        ->assertSee('Global Managed AI Kill Switch')
        ->assertSee('Acme Cloud Team')
        ->assertSee('copilot')
        ->assertSee('2 DLP scrubs');

    // Toggle global kill switch ON and verify AiQuotaService immediately blocks Managed inference
    $component->call('toggleManagedAiKillSwitch');
    expect(AiPlatformSetting::current()->managed_ai_kill_switch)->toBeTrue();

    expect(fn () => app(AiQuotaService::class)->resolveChatRoute($team->fresh(), feature: 'copilot'))
        ->toThrow(AiException::class, 'temporarily paused by the platform administrator');

    // Toggle OFF + save platform trial & daily cap controls
    $component->call('toggleManagedAiKillSwitch')
        ->set('aiDefaultTrialUsd', '5.00')
        ->set('aiGlobalDailyCapUsd', '150')
        ->call('saveAiPlatformSettings')
        ->assertHasNoErrors();

    $platform = AiPlatformSetting::current();
    expect($platform->managed_ai_kill_switch)->toBeFalse()
        ->and($platform->default_trial_credits_microcents)->toBe(500_000_000)
        ->and($platform->global_daily_spend_cap_cents)->toBe(15_000);

    // Grant trial & managed credits atomically
    $component->set('grantCreditsTeamId', $team->id)
        ->set('grantCreditsAmountUsd', '10.00')
        ->set('grantCreditsBucket', 'trial')
        ->call('grantTenantAiCredits')
        ->set('grantCreditsAmountUsd', '25.00')
        ->set('grantCreditsBucket', 'managed')
        ->call('grantTenantAiCredits')
        ->assertHasNoErrors();

    $freshConfig = $team->aiConfig()->firstOrFail();
    expect($freshConfig->trial_credits_microcents)->toBe(1_200_000_000)
        ->and($freshConfig->managed_credits_microcents)->toBe(2_500_000_000);

    // Toggle tenant AI enabled status
    $component->call('toggleTenantAiStatus', $team->id);
    expect($team->aiConfig()->firstOrFail()->is_enabled)->toBeFalse();

    $component->call('toggleTenantAiStatus', $team->id);
    expect($team->aiConfig()->firstOrFail()->is_enabled)->toBeTrue();
});

test('non-super-admin users cannot access super admin AI Ops controls', function () {
    $regular = User::factory()->create();

    $this->actingAs($regular)
        ->get('/admin')
        ->assertForbidden();
});
