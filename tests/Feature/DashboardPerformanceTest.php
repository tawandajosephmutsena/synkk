<?php

use App\Models\User;
use App\Models\Vault;
use App\Models\VaultFile;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

test('storage analytics are aggregated without hydrating every vault file', function () {
    $user = User::factory()->create();
    $vault = Vault::query()->create([
        'team_id' => $user->currentTeam->id,
        'name' => 'Performance vault',
        'description' => 'A representative mixed vault.',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    foreach (range(1, 12) as $index) {
        VaultFile::query()->create([
            'vault_id' => $vault->id,
            'path' => "notes/note-{$index}.md",
            'storage_path' => "vaults/{$vault->id}/notes/note-{$index}.md",
            'sha256' => hash('sha256', "note-{$index}"),
            'size' => 100,
            'version' => 1,
            'is_deleted' => false,
            'last_modified_by' => $user->id,
        ]);
    }

    $retrievedVaultFiles = 0;
    Event::listen('eloquent.retrieved: '.VaultFile::class, function () use (&$retrievedVaultFiles): void {
        $retrievedVaultFiles++;
    });

    $component = Livewire::actingAs($user)->test('pages::dashboard.index');

    expect($component->get('storageBreakdown.markdown.count'))->toBe(12)
        ->and($retrievedVaultFiles)->toBe(0);
});
