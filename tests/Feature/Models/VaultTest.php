<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultPermission;
use Illuminate\Support\Facades\DB;

it('resolves many paths with a constant number of permission queries', function () {
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $vault = Vault::query()->create([
        'team_id' => $team->id,
        'name' => 'Shared knowledge',
        'description' => null,
        'default_permission' => 'read_write',
        'created_by' => $member->id,
    ]);
    $vault->setRelation('team', $team);

    VaultPermission::query()->create([
        'vault_id' => $vault->id,
        'user_id' => null,
        'path' => 'Projects/Private',
        'permission' => 'hidden',
        'is_folder' => true,
    ]);
    VaultPermission::query()->create([
        'vault_id' => $vault->id,
        'user_id' => $member->id,
        'path' => 'Projects',
        'permission' => 'read_only',
        'is_folder' => true,
    ]);
    VaultPermission::query()->create([
        'vault_id' => $vault->id,
        'user_id' => $member->id,
        'path' => 'Projects/Editable',
        'permission' => 'read_write',
        'is_folder' => true,
    ]);

    DB::enableQueryLog();
    DB::flushQueryLog();

    $resolvedPermissions = [];

    foreach (range(1, 25) as $index) {
        $resolvedPermissions[] = $vault->permissionForPath($member, "Projects/Private/plan-{$index}.md");
        $resolvedPermissions[] = $vault->permissionForPath($member, "Projects/Editable/note-{$index}.md");
        $resolvedPermissions[] = $vault->permissionForPath($member, "Public/note-{$index}.md");
    }

    $queries = collect(DB::getQueryLog())->pluck('query');

    expect(array_count_values($resolvedPermissions))->toBe([
        'read_only' => 25,
        'read_write' => 50,
    ]);
    expect($queries)->toHaveCount(2);
    expect($queries->filter(fn (string $query): bool => str_contains($query, 'team_members')))->toHaveCount(1);
    expect($queries->filter(fn (string $query): bool => str_contains($query, 'vault_permissions')))->toHaveCount(1);
});

it('keeps cached permission rules isolated for each user', function () {
    $firstMember = User::factory()->create();
    $secondMember = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($firstMember, ['role' => TeamRole::Member->value]);
    $team->members()->attach($secondMember, ['role' => TeamRole::Member->value]);

    $vault = Vault::query()->create([
        'team_id' => $team->id,
        'name' => 'Shared knowledge',
        'description' => null,
        'default_permission' => 'read_write',
        'created_by' => $firstMember->id,
    ]);
    $vault->setRelation('team', $team);

    VaultPermission::query()->create([
        'vault_id' => $vault->id,
        'user_id' => $firstMember->id,
        'path' => 'Leadership',
        'permission' => 'read_only',
        'is_folder' => true,
    ]);
    VaultPermission::query()->create([
        'vault_id' => $vault->id,
        'user_id' => $secondMember->id,
        'path' => 'Leadership',
        'permission' => 'hidden',
        'is_folder' => true,
    ]);

    DB::enableQueryLog();
    DB::flushQueryLog();

    $firstResult = null;
    $secondResult = null;

    foreach (range(1, 20) as $index) {
        $firstResult = $vault->permissionForPath($firstMember, "Leadership/note-{$index}.md");
        $secondResult = $vault->permissionForPath($secondMember, "Leadership/note-{$index}.md");
    }

    $queries = collect(DB::getQueryLog())->pluck('query');

    expect($firstResult)->toBe('read_only');
    expect($secondResult)->toBe('hidden');
    expect($queries)->toHaveCount(3);
    expect($queries->filter(fn (string $query): bool => str_contains($query, 'team_members')))->toHaveCount(2);
    expect($queries->filter(fn (string $query): bool => str_contains($query, 'vault_permissions')))->toHaveCount(1);
});
