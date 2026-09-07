<?php

use App\Actions\Vaults\ResolveConflictAction;
use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use App\Services\ThreeWayDiffService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('ThreeWayDiffService identifies clean edits and conflicts accurately', function () {
    $service = new ThreeWayDiffService;

    // 1. Clean independent non-colliding changes
    $base = "Title\nSection A\nSection B\nFooter";
    $ours = "Title\nSection A modified by Us\nSection B\nFooter";
    $theirs = "Title\nSection A\nSection B modified by Them\nFooter";

    $res = $service->merge($base, $ours, $theirs);
    expect($res['has_conflicts'])->toBeFalse()
        ->and($res['conflict_count'])->toBe(0)
        ->and($res['merged_content'])->toContain('Section A modified by Us')
        ->and($res['merged_content'])->toContain('Section B modified by Them');

    // 2. Direct collision on same section -> conflict
    $oursConflict = "Title\nSection A by Us\nSection B\nFooter";
    $theirsConflict = "Title\nSection A by Them\nSection B\nFooter";

    $resConflict = $service->merge($base, $oursConflict, $theirsConflict);
    expect($resConflict['has_conflicts'])->toBeTrue()
        ->and($resConflict['conflict_count'])->toBe(1)
        ->and($resConflict['merged_content'])->toContain('<<<<<<< CURRENT (Ours)')
        ->and($resConflict['merged_content'])->toContain('>>>>>>> INCOMING (Theirs)');
});

test('ResolveConflictAction reconciles note, updates version, and removes conflict file', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);
    $user->update(['current_team_id' => $team->id]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Design Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    // Create canonical note
    $canonicalFile = $vault->files()->create([
        'path' => 'Roadmap.md',
        'storage_path' => 'vaults/'.$vault->id.'/roadmap.md',
        'sha256' => hash('sha256', 'Canonical content v1'),
        'size' => 20,
        'version' => 1,
        'is_deleted' => false,
        'last_modified_by' => $user->id,
    ]);
    Storage::disk('local')->put($canonicalFile->storage_path, 'Canonical content v1');

    // Create conflict copy
    $conflictPath = 'Roadmap.conflict-bob-20260907.md';
    $conflictFile = $vault->files()->create([
        'path' => $conflictPath,
        'storage_path' => 'vaults/'.$vault->id.'/roadmap_conflict.md',
        'sha256' => hash('sha256', 'Conflict content by Bob'),
        'size' => 22,
        'version' => 2,
        'is_deleted' => false,
        'last_modified_by' => $user->id,
    ]);
    Storage::disk('local')->put($conflictFile->storage_path, 'Conflict content by Bob');

    $action = app(ResolveConflictAction::class);
    $result = $action->execute(
        vault: $vault,
        user: $user,
        canonicalPath: 'Roadmap.md',
        conflictPath: $conflictPath,
        resolvedContent: "Unified Reconciled Roadmap\n- Feature A\n- Feature B"
    );

    expect($result['status'])->toBe('resolved')
        ->and($result['canonical_path'])->toBe('Roadmap.md')
        ->and($result['version'])->toBeGreaterThanOrEqual(2);

    // Canonical file should be updated with new content
    $canonicalFile->refresh();
    expect($canonicalFile->getContents())->toBe("Unified Reconciled Roadmap\n- Feature A\n- Feature B");

    // Conflict file should be soft-deleted
    $conflictFile->refresh();
    expect($conflictFile->is_deleted)->toBeTrue();

    // Changelog has deletion tombstone for conflict and reconciled action for canonical
    expect($vault->changeLogs()->where('path', $conflictPath)->where('action', 'deleted')->exists())->toBeTrue()
        ->and($vault->changeLogs()->where('path', 'Roadmap.md')->where('action', 'reconciled')->exists())->toBeTrue();
});

test('API endpoints can list conflicts, compute diff, and resolve conflicts', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);
    $user->update(['current_team_id' => $team->id]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Collab Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $tokenResult = DeviceToken::createToken($user, $team, 'MacBook Pro', 'mac');
    $token = $tokenResult['plain_token'];

    // Setup files
    $canonical = $vault->files()->create([
        'path' => 'Notes/Arch.md',
        'storage_path' => 'vaults/'.$vault->id.'/arch.md',
        'sha256' => hash('sha256', "Line 1\nLine 2 Ours\nLine 3"),
        'size' => 25,
        'version' => 1,
        'is_deleted' => false,
        'last_modified_by' => $user->id,
    ]);
    Storage::disk('local')->put($canonical->storage_path, "Line 1\nLine 2 Ours\nLine 3");

    $conflictPath = 'Notes/Arch.conflict-alex-2026.md';
    $conflict = $vault->files()->create([
        'path' => $conflictPath,
        'storage_path' => 'vaults/'.$vault->id.'/arch_conflict.md',
        'sha256' => hash('sha256', "Line 1\nLine 2 Theirs\nLine 3"),
        'size' => 27,
        'version' => 2,
        'is_deleted' => false,
        'last_modified_by' => $user->id,
    ]);
    Storage::disk('local')->put($conflict->storage_path, "Line 1\nLine 2 Theirs\nLine 3");

    // 1. List conflicts
    $listRes = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/vaults/{$vault->slug}/conflicts");

    $listRes->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonCount(1, 'conflicts')
        ->assertJsonPath('conflicts.0.conflict_path', $conflictPath)
        ->assertJsonPath('conflicts.0.canonical_path', 'Notes/Arch.md');

    // 2. Request 3-Way diff
    $diffRes = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/vaults/{$vault->slug}/conflicts/diff", [
            'conflict_path' => $conflictPath,
        ]);

    $diffRes->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('has_conflicts', true)
        ->assertJsonPath('conflict_count', 1);

    // 3. Resolve conflict
    $resolveRes = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/vaults/{$vault->slug}/conflicts/resolve", [
            'canonical_path' => 'Notes/Arch.md',
            'conflict_path' => $conflictPath,
            'resolved_content' => "Line 1\nLine 2 Reconciled\nLine 3",
        ]);

    $resolveRes->assertOk()
        ->assertJsonPath('status', 'resolved')
        ->assertJsonPath('canonical_path', 'Notes/Arch.md');

    // Verification
    $canonical->refresh();
    expect($canonical->getContents())->toBe("Line 1\nLine 2 Reconciled\nLine 3");
    $conflict->refresh();
    expect($conflict->is_deleted)->toBeTrue();
});
