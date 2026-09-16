<?php

use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = Team::factory()->create([
        'storage_limit_mb' => 50, // 50 MB limit for test
    ]);
    $this->team->members()->attach($this->user, ['role' => 'owner']);

    $this->vault = Vault::create([
        'team_id' => $this->team->id,
        'name' => 'Research Vault',
        'slug' => 'research-vault',
        'created_by' => $this->user->id,
        'default_permission' => 'read_write',
    ]);

    $tokenResult = DeviceToken::createToken($this->user, $this->team, 'MacBook Pro');
    $this->token = $tokenResult['plain_token'];

    $disk = config('synkk.storage_disk', 'local');
    Storage::fake($disk);

    // Seed one existing file on server
    $content = "# Existing Server Note\nContent on server.";
    $storagePath = 'vaults/'.$this->vault->id.'/Notes/Server.md';
    Storage::disk($disk)->put($storagePath, $content);

    $this->existingFile = VaultFile::create([
        'vault_id' => $this->vault->id,
        'path' => 'Notes/Server.md',
        'storage_path' => $storagePath,
        'sha256' => hash('sha256', $content),
        'size' => strlen($content),
        'version' => 1,
        'is_deleted' => false,
    ]);
});

test('preflight check succeeds within quota and calculates dry run simulation', function () {
    $existingContent = "# Existing Server Note\nContent on server.";
    $identicalSha = hash('sha256', $existingContent);

    $payload = [
        'total_files' => 3,
        'total_bytes' => 1024 * 1024, // 1 MB
        'categories' => [
            'markdown' => 2,
            'canvas' => 1,
        ],
        'files' => [
            [
                'path' => 'Notes/Server.md',
                'size' => strlen($existingContent),
                'sha256' => $identicalSha, // Matching sha: identical skip
            ],
            [
                'path' => 'Notes/NewLocal.md',
                'size' => 500,
                'sha256' => hash('sha256', 'New local note'),
            ],
            [
                'path' => 'Diagrams/System.canvas',
                'size' => 1200,
                'sha256' => hash('sha256', 'Canvas data'),
            ],
        ],
    ];

    $response = $this->withToken($this->token)
        ->postJson(route('api.vaults.preflight', ['vault' => $this->vault->slug]), $payload);

    $response->assertOk()
        ->assertJson([
            'status' => 'ready',
            'authorized' => true,
            'vault' => [
                'slug' => 'research-vault',
                'name' => 'Research Vault',
                'server_files_count' => 1,
            ],
            'quota' => [
                'allowed' => true,
            ],
            'simulation' => [
                'to_upload_count' => 2,
                'to_download_count' => 0,
                'identical_skipped_count' => 1,
                'bandwidth_saved_bytes' => strlen($existingContent),
            ],
            'safety' => [
                'atomic_shield_active' => true,
                'max_deletion_threshold_percent' => 20,
            ],
        ]);
});

test('preflight check rejects payload exceeding team storage limit with 422', function () {
    $oversizedBytes = 60 * 1024 * 1024; // 60 MB (exceeds 50 MB limit)

    $payload = [
        'total_files' => 100,
        'total_bytes' => $oversizedBytes,
    ];

    $response = $this->withToken($this->token)
        ->postJson(route('api.vaults.preflight', ['vault' => $this->vault->slug]), $payload);

    $response->assertStatus(422)
        ->assertJson([
            'status' => 'quota_exceeded',
            'authorized' => false,
            'quota' => [
                'allowed' => false,
            ],
        ])
        ->assertJsonStructure([
            'status',
            'message',
            'quota' => ['deficit_bytes', 'storage_limit_bytes'],
            'recommendation',
        ]);
});

test('unauthenticated request is rejected by preflight endpoint', function () {
    $response = $this->postJson(route('api.vaults.preflight', ['vault' => $this->vault->slug]), [
        'total_files' => 10,
        'total_bytes' => 5000,
    ]);

    $response->assertStatus(401);
});
