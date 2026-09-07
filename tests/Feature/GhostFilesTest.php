<?php

use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use App\Services\GhostFileService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('GhostFileService detects ghost candidates and generates valid stubs', function () {
    $service = new GhostFileService;

    expect($service->isCandidateForGhost('attachments/video.mp4', 10 * 1024 * 1024, 5))->toBeTrue()
        ->and($service->isCandidateForGhost('attachments/manual.pdf', 8 * 1024 * 1024, 5))->toBeTrue()
        ->and($service->isCandidateForGhost('notes/daily.md', 10 * 1024 * 1024, 5))->toBeFalse()
        ->and($service->isCandidateForGhost('attachments/tiny.png', 1024, 5))->toBeFalse();

    $stub = $service->generateGhostStub(
        path: 'assets/keynote.mp4',
        originalSize: 52428800,
        mimeType: 'video/mp4',
        sha256: 'deadbeef12345678'
    );

    expect($service->isGhostStub($stub))->toBeTrue();

    $parsed = $service->parseGhostStub($stub);
    expect($parsed)->not->toBeNull()
        ->and($parsed['path'])->toBe('assets/keynote.mp4')
        ->and($parsed['size'])->toBe(52428800)
        ->and($parsed['mime'])->toBe('video/mp4')
        ->and($parsed['sha256'])->toBe('deadbeef12345678');
});

test('Ghost files can be downloaded as stubs and hydrated on-demand via API', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => 'owner']);
    $user->update(['current_team_id' => $team->id]);

    $vault = Vault::create([
        'team_id' => $team->id,
        'name' => 'Transport Vault',
        'default_permission' => 'read_write',
        'created_by' => $user->id,
    ]);

    $tokenResult = DeviceToken::createToken($user, $team, 'MacBook Pro', 'mac');
    $token = $tokenResult['plain_token'];

    // Put a large binary in storage
    $storagePath = 'vaults/'.$vault->id.'/videos/intro.mp4';
    $binaryContent = str_repeat('A', 5000);
    Storage::disk('local')->put($storagePath, $binaryContent);

    $file = $vault->files()->create([
        'path' => 'videos/intro.mp4',
        'storage_path' => $storagePath,
        'sha256' => hash('sha256', $binaryContent),
        'size' => 5000,
        'version' => 1,
        'is_deleted' => false,
        'is_ghost' => false,
        'original_size' => 5000,
        'mime_type' => 'video/mp4',
    ]);

    // 1. Download with ghost=1 returns ghost stub and X-Synkk-Ghost header
    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/vaults/{$vault->slug}/download?path=videos/intro.mp4&ghost=1");

    $response->assertOk()
        ->assertHeader('X-Synkk-Ghost', '1');
    expect($response->getContent())->toContain('<!-- synkk:ghost');

    // 2. Dehydrate file via API
    $dehydrateRes = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/vaults/{$vault->slug}/files/dehydrate", [
            'path' => 'videos/intro.mp4',
        ]);

    $dehydrateRes->assertOk()
        ->assertJson([
            'status' => 'dehydrated',
            'is_ghost' => true,
        ]);

    $file->refresh();
    expect($file->is_ghost)->toBeTrue();

    // 3. Hydrate file via API
    $hydrateRes = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/vaults/{$vault->slug}/files/hydrate", [
            'path' => 'videos/intro.mp4',
        ]);

    $hydrateRes->assertOk()
        ->assertJson([
            'status' => 'hydrated',
            'is_ghost' => false,
        ]);

    $file->refresh();
    expect($file->is_ghost)->toBeFalse()
        ->and($file->hydrated_at)->not->toBeNull();
});
