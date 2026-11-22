<?php

use App\Actions\Vaults\SyncUploadAction;
use App\Enums\TeamRole;
use App\Exceptions\AiException;
use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultAiSetting;
use App\Models\VaultPermission;
use App\Services\Ai\AiProposalService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('AiProposalService stages, previews, approves, and rejects proposals safely', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['plan' => 'pro_ltd']);
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Proposals Vault',
        'default_permission' => 'read_write',
        'created_by' => $owner->id,
    ]);

    $proposalService = app(AiProposalService::class);

    // Stage new note creation
    $createProposal = $proposalService->stageProposal($vault, $owner, [
        'target_path' => 'Clients/Acme',
        'proposed_content' => "# Acme Brief\nInitial summary.",
        'rationale' => 'Draft brief from agent',
        'source' => 'copilot',
    ]);

    expect($createProposal->target_path)->toBe('Clients/Acme.md')
        ->and($createProposal->action)->toBe('create')
        ->and($createProposal->status)->toBe('pending');

    $approved = $proposalService->approve($createProposal, $owner);
    expect($approved['proposal']->status)->toBe('approved')
        ->and($approved['stale_merged'])->toBeFalse();

    $file = $vault->files()->where('path', 'Clients/Acme.md')->firstOrFail();
    expect($file->getContents())->toBe("# Acme Brief\nInitial summary.");

    // Stage append and reject
    $appendProposal = $proposalService->stageProposal($vault, $owner, [
        'target_path' => 'Clients/Acme.md',
        'proposed_content' => '## Next Steps',
        'action' => 'append',
    ]);

    expect($appendProposal->proposed_content)->toContain("# Acme Brief\nInitial summary.\n\n## Next Steps");

    $rejected = $proposalService->reject($appendProposal, $owner);
    expect($rejected->status)->toBe('rejected')
        ->and($file->fresh()->getContents())->toBe("# Acme Brief\nInitial summary.");
});

test('AiProposalService automatically 3-way merges stale proposals when file changed since staging', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['plan' => 'pro_ltd']);
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Stale Merge Vault',
        'default_permission' => 'read_write',
        'created_by' => $owner->id,
    ]);

    $sync = app(SyncUploadAction::class);
    $v1Content = "# Spec\nLine 1\nLine 2\nLine 3\nLine 4";
    $sync->execute($vault, $owner, 'Mac', 'Specs/Architecture.md', $v1Content, baseVersion: 0);

    $proposalService = app(AiProposalService::class);
    // AI stages edit to Line 4 based on v1
    $proposal = $proposalService->stageProposal($vault, $owner, [
        'target_path' => 'Specs/Architecture.md',
        'proposed_content' => "# Spec\nLine 1\nLine 2\nLine 3\nLine 4 (AI summary added)",
        'rationale' => 'Add summary at end',
    ]);

    // Meanwhile human edits Line 1 to v2
    $v2Content = "# Spec\nLine 1 (Human edit)\nLine 2\nLine 3\nLine 4";
    $sync->execute($vault, $owner, 'Laptop', 'Specs/Architecture.md', $v2Content, baseVersion: 1);

    $preview = $proposalService->buildDiffPreview($proposal);
    expect($preview['is_stale'])->toBeTrue()
        ->and($preview['base_version'])->toBe(1)
        ->and($preview['current_version'])->toBe(2)
        ->and($preview['diff']['has_conflicts'])->toBeFalse();

    $approved = $proposalService->approve($proposal, $owner);
    $mergedFile = $vault->files()->where('path', 'Specs/Architecture.md')->firstOrFail();

    expect($approved['stale_merged'])->toBeTrue()
        ->and($mergedFile->getContents())->toContain('Line 1 (Human edit)')
        ->and($mergedFile->getContents())->toContain('Line 4 (AI summary added)');
});

test('AiProposalService enforces E2EE, excluded paths, traversal, and path write permissions', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create(['plan' => 'pro_ltd']);
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Guarded Vault',
        'default_permission' => 'read_write',
        'created_by' => $owner->id,
    ]);

    VaultAiSetting::create([
        'vault_id' => $vault->id,
        'ai_mode' => 'server',
        'excluded_paths' => ['Private/**'],
    ]);

    VaultPermission::create([
        'vault_id' => $vault->id,
        'user_id' => $member->id,
        'path' => 'Docs',
        'permission' => 'read_only',
        'is_folder' => true,
    ]);

    $proposalService = app(AiProposalService::class);

    expect(fn () => $proposalService->stageProposal($vault, $owner, [
        'target_path' => '../secrets.md',
        'proposed_content' => 'oops',
    ]))->toThrow(AiException::class);

    expect(fn () => $proposalService->stageProposal($vault, $owner, [
        'target_path' => 'Private/Salary.md',
        'proposed_content' => 'oops',
    ]))->toThrow(AiException::class);

    expect(fn () => $proposalService->stageProposal($vault, $member, [
        'target_path' => 'Docs/Guide.md',
        'proposed_content' => 'oops',
    ]))->toThrow(AiException::class);

    $e2eeVault = Vault::create([
        'team_id' => $team->id,
        'name' => 'E2EE Vault',
        'default_permission' => 'read_write',
        'created_by' => $owner->id,
        'is_e2ee' => true,
    ]);

    expect(fn () => $proposalService->stageProposal($e2eeVault, $owner, [
        'target_path' => 'Notes/Test.md',
        'proposed_content' => 'oops',
    ]))->toThrow(AiException::class);
});
