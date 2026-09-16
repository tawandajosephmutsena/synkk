<?php

use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultPortal;
use Illuminate\Support\Facades\Artisan;

test('artisan command publishes documentation vault and creates public portal', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);

    $exitCode = Artisan::call('synkk:publish-docs-vault', ['--team' => $team->id]);

    expect($exitCode)->toBe(0);

    $vault = Vault::where('slug', 'synkk-docs')->first();
    expect($vault)->not->toBeNull();
    expect($vault->name)->toBe('Synkk Documentation');
    expect($vault->files()->count())->toBeGreaterThan(0);

    $portal = VaultPortal::where('slug', 'synkk-docs')->first();
    expect($portal)->not->toBeNull();
    expect($portal->is_public)->toBeTrue();
    expect($portal->theme)->toBe('enterprise-docs');
    expect($portal->layout)->toBe('docs');

    // Test that the portal renders via HTTP
    $response = $this->get("/p/{$portal->slug}");
    $response->assertOk();
    $response->assertSee('Synkk');
});
