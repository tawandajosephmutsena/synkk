<?php

use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultFile;
use App\Models\VaultPortal;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = Team::factory()->create();
    $this->team->members()->attach($this->user, ['role' => 'owner']);
    $this->user->switchTeam($this->team);
    $this->actingAs($this->user);

    $this->vault = Vault::create([
        'team_id' => $this->team->id,
        'name' => 'Security Vault',
        'slug' => 'security-vault',
        'created_by' => $this->user->id,
        'default_permission' => 'read_write',
    ]);

    $disk = config('synkk.storage_disk', 'local');
    Storage::fake($disk);

    $content1 = "# Secret Note\nConfidential company data.";
    $storagePath1 = 'vaults/'.$this->vault->id.'/Secret.md';
    Storage::disk($disk)->put($storagePath1, $content1);

    $this->file1 = VaultFile::create([
        'vault_id' => $this->vault->id,
        'path' => 'Secret.md',
        'storage_path' => $storagePath1,
        'sha256' => hash('sha256', $content1),
        'size' => strlen($content1),
        'mime_type' => 'text/markdown',
        'version' => 1,
        'is_deleted' => false,
    ]);
});

// =========================================================================
// P0-01: Livewire Client-Controlled Unlock Flag Tampering
// =========================================================================

test('password protected portal does not expose data when locked and blocks computed property mutation (P0-01)', function () {
    $portal = VaultPortal::create([
        'team_id' => $this->team->id,
        'vault_id' => $this->vault->id,
        'name' => 'Protected Docs',
        'slug' => 'protected-docs',
        'layout' => 'docs',
        'theme' => 'obsidian-noir',
        'password' => 'StrongPassword123!',
        'is_public' => true,
        'created_by' => $this->user->id,
    ]);

    auth()->logout();

    $component = Livewire::test('pages::portals.show', ['slug' => 'protected-docs']);

    // isUnlocked must be false
    expect($component->get('isUnlocked'))->toBeFalse()
        ->and($component->get('accessibleFiles')->isEmpty())->toBeTrue()
        ->and($component->get('markdownFiles')->isEmpty())->toBeTrue()
        ->and($component->get('bentoCards'))->toBeEmpty()
        ->and($component->get('interactiveGraph')['nodes'])->toBeEmpty()
        ->and($component->get('renderedNote')['html'])->toContain('This portal is password protected.');

    // Tampering attempt: mutating computed property directly
    try {
        $component->set('isUnlocked', true);
        $this->fail('Expected Livewire to prevent mutating computed property isUnlocked');
    } catch (Throwable $e) {
        expect($e)->toBeInstanceOf(Throwable::class);
    }
});

test('locked portal does not reveal content even when selecting notes via Livewire action (P0-01)', function () {
    $portal = VaultPortal::create([
        'team_id' => $this->team->id,
        'vault_id' => $this->vault->id,
        'name' => 'Gated Portal',
        'slug' => 'gated-portal',
        'layout' => 'docs',
        'theme' => 'obsidian-noir',
        'password' => 'SecretPassphrase999',
        'is_public' => true,
        'created_by' => $this->user->id,
    ]);

    auth()->logout();

    $component = Livewire::test('pages::portals.show', ['slug' => 'gated-portal'])
        ->call('selectNote', 'Secret.md');

    // Rendered note should still return the locked notice, not note contents
    expect($component->get('renderedNote')['html'])
        ->toContain('This portal is password protected.')
        ->not->toContain('Confidential company data.');
});

// =========================================================================
// P0-02: Continuous Reauthorization Across Lifecycle Actions
// =========================================================================

test('removed team member is immediately denied on subsequent Livewire actions with 404 (P0-02)', function () {
    $portal = VaultPortal::create([
        'team_id' => $this->team->id,
        'vault_id' => $this->vault->id,
        'name' => 'Confidential Portal',
        'slug' => 'confidential-portal',
        'layout' => 'docs',
        'theme' => 'obsidian-noir',
        'is_public' => false,
        'created_by' => $this->user->id,
    ]);

    $member = User::factory()->create();
    $this->team->members()->attach($member, ['role' => 'member']);
    $member->switchTeam($this->team);

    $this->actingAs($member);

    // Initial load succeeds for active member
    $component = Livewire::test('pages::portals.show', ['slug' => 'confidential-portal']);
    $component->assertOk();

    // Member is removed from the team while viewing the portal
    $this->team->members()->detach($member->id);

    // Direct authorization check throws 404
    expect(fn () => $component->instance()->authorizePortalAccess())
        ->toThrow(NotFoundHttpException::class);

    // Subsequent Livewire action fails-closed and does not update notePath
    $component->call('selectNote', 'Secret.md');
    expect($component->get('notePath'))->not->toBe('Secret.md');

    // Subsequent full HTTP request returns 404
    $this->get(route('portal.show', ['slug' => 'confidential-portal']))
        ->assertNotFound();
});

// =========================================================================
// P1-04: Password Gate Rate Limiting & Dynamic Expiry
// =========================================================================

test('portal unlock enforces rate limiting after 5 consecutive failed attempts (P1-04)', function () {
    $portal = VaultPortal::create([
        'team_id' => $this->team->id,
        'vault_id' => $this->vault->id,
        'name' => 'Rate Limited Portal',
        'slug' => 'rate-limited-portal',
        'layout' => 'docs',
        'theme' => 'obsidian-noir',
        'password' => 'ValidPass1234',
        'is_public' => true,
        'created_by' => $this->user->id,
    ]);

    auth()->logout();
    RateLimiter::clear('portal-unlock:'.$portal->id.':127.0.0.1');

    $component = Livewire::test('pages::portals.show', ['slug' => 'rate-limited-portal']);

    // Attempt 1 to 5: validation error 'Incorrect password.'
    for ($i = 1; $i <= 5; $i++) {
        $component->set('passwordInput', 'wrong-guess-'.$i)
            ->call('unlock')
            ->assertHasErrors(['passwordInput']);
    }

    // Attempt 6: rate limited
    $component->set('passwordInput', 'ValidPass1234')
        ->call('unlock')
        ->assertHasErrors(['passwordInput'])
        ->assertSee('Too many attempts.');
});

test('portal password change immediately invalidates active session grants (P1-04)', function () {
    $portal = VaultPortal::create([
        'team_id' => $this->team->id,
        'vault_id' => $this->vault->id,
        'name' => 'Dynamic Password Portal',
        'slug' => 'dynamic-portal',
        'layout' => 'docs',
        'theme' => 'obsidian-noir',
        'password' => 'OriginalPassword123',
        'is_public' => true,
        'created_by' => $this->user->id,
    ]);

    auth()->logout();

    $component = Livewire::test('pages::portals.show', ['slug' => 'dynamic-portal'])
        ->set('passwordInput', 'OriginalPassword123')
        ->call('unlock')
        ->assertHasNoErrors();

    expect($component->get('isUnlocked'))->toBeTrue();

    // Portal admin updates password in background
    $portal->update(['password' => 'NewRotatedPassword456']);

    // Create a new component instance sharing the same session
    $reloadedComponent = Livewire::test('pages::portals.show', ['slug' => 'dynamic-portal']);

    // Session grant fingerprint mismatch -> portal is immediately locked again
    expect($reloadedComponent->get('isUnlocked'))->toBeFalse();
});

test('expired portal session grant forces relock (P1-04)', function () {
    $portal = VaultPortal::create([
        'team_id' => $this->team->id,
        'vault_id' => $this->vault->id,
        'name' => 'Expiry Portal',
        'slug' => 'expiry-portal',
        'layout' => 'docs',
        'theme' => 'obsidian-noir',
        'password' => 'MyVaultPass123',
        'is_public' => true,
        'created_by' => $this->user->id,
    ]);

    auth()->logout();

    // Simulate an expired session grant
    $fingerprint = substr(hash('sha256', $portal->password), 0, 16);
    session()->put('portal_unlocked_'.$portal->id, [
        'fingerprint' => $fingerprint,
        'expires_at' => now()->subMinutes(10)->timestamp,
    ]);

    $component = Livewire::test('pages::portals.show', ['slug' => 'expiry-portal']);

    expect($component->get('isUnlocked'))->toBeFalse();
});

// =========================================================================
// P1-05: Model-Level Tenant Invariants
// =========================================================================

test('model prevents saving a portal with a vault belonging to a different team (P1-05)', function () {
    $foreignTeam = Team::factory()->create();
    $foreignVault = Vault::create([
        'team_id' => $foreignTeam->id,
        'name' => 'Foreign Vault',
        'slug' => 'foreign-vault',
        'created_by' => User::factory()->create()->id,
        'default_permission' => 'read_write',
    ]);

    expect(function () use ($foreignVault) {
        VaultPortal::create([
            'team_id' => $this->team->id,
            'vault_id' => $foreignVault->id, // Foreign vault!
            'name' => 'Illegal Portal',
            'slug' => 'illegal-portal',
            'layout' => 'docs',
            'theme' => 'obsidian-noir',
            'is_public' => true,
            'created_by' => $this->user->id,
        ]);
    })->toThrow(InvalidArgumentException::class, 'Cross-tenant violation: Vault does not belong to the portal team.');
});

test('model prevents saving a portal with a primary file belonging to a different vault (P1-05)', function () {
    $otherVault = Vault::create([
        'team_id' => $this->team->id,
        'name' => 'Other Vault',
        'slug' => 'other-vault',
        'created_by' => $this->user->id,
        'default_permission' => 'read_write',
    ]);

    $foreignFile = VaultFile::create([
        'vault_id' => $otherVault->id,
        'path' => 'Foreign.md',
        'storage_path' => 'vaults/'.$otherVault->id.'/Foreign.md',
        'sha256' => hash('sha256', 'test'),
        'size' => 4,
        'mime_type' => 'text/markdown',
        'version' => 1,
        'is_deleted' => false,
    ]);

    expect(function () use ($foreignFile) {
        VaultPortal::create([
            'team_id' => $this->team->id,
            'vault_id' => $this->vault->id,
            'primary_file_id' => $foreignFile->id, // File is in $otherVault, not $this->vault
            'name' => 'Mismatched File Portal',
            'slug' => 'mismatched-file-portal',
            'layout' => 'docs',
            'theme' => 'obsidian-noir',
            'is_public' => true,
            'created_by' => $this->user->id,
        ]);
    })->toThrow(InvalidArgumentException::class, 'Vault integrity violation: Primary file does not belong to the portal vault.');
});

test('model revalidates tenant isolation after a vault relation was loaded', function () {
    $portal = VaultPortal::create([
        'team_id' => $this->team->id,
        'vault_id' => $this->vault->id,
        'name' => 'Loaded Relation Portal',
        'slug' => 'loaded-relation-portal',
        'layout' => 'docs',
        'theme' => 'obsidian-noir',
        'created_by' => $this->user->id,
    ]);
    $portal->load('vault');

    $foreignTeam = Team::factory()->create();
    $foreignVault = Vault::create([
        'team_id' => $foreignTeam->id,
        'name' => 'Foreign Vault',
        'slug' => 'foreign-loaded-vault',
        'created_by' => User::factory()->create()->id,
    ]);

    expect(fn () => $portal->forceFill(['vault_id' => $foreignVault->id])->save())
        ->toThrow(InvalidArgumentException::class, 'Cross-tenant violation');
});

test('model revalidates file integrity after a primary file relation was loaded', function () {
    $portal = VaultPortal::create([
        'team_id' => $this->team->id,
        'vault_id' => $this->vault->id,
        'primary_file_id' => $this->file1->id,
        'name' => 'Loaded File Portal',
        'slug' => 'loaded-file-portal',
        'layout' => 'docs',
        'theme' => 'obsidian-noir',
        'created_by' => $this->user->id,
    ]);
    $portal->load('primaryFile');

    $otherVault = Vault::create([
        'team_id' => $this->team->id,
        'name' => 'Other Vault',
        'slug' => 'other-loaded-vault',
        'created_by' => $this->user->id,
    ]);
    $foreignFile = VaultFile::create([
        'vault_id' => $otherVault->id,
        'path' => 'Foreign.md',
        'storage_path' => 'vaults/'.$otherVault->id.'/Foreign.md',
        'sha256' => hash('sha256', 'foreign'),
        'size' => 7,
        'mime_type' => 'text/markdown',
        'version' => 1,
        'is_deleted' => false,
    ]);

    expect(fn () => $portal->forceFill(['primary_file_id' => $foreignFile->id])->save())
        ->toThrow(InvalidArgumentException::class, 'Vault integrity violation');
});

// =========================================================================
// P1-08: Customer Legal & Privacy Surface Endpoints
// =========================================================================

test('customer legal and privacy surfaces return 200 with required disclosures (P1-08)', function () {
    // Privacy policy
    $this->get('/privacy')
        ->assertOk()
        ->assertSee('Privacy Policy')
        ->assertSee('GDPR')
        ->assertSee('Controller vs. Processor Roles')
        ->assertSee('Subprocessors');

    // Terms of Service
    $this->get('/terms')
        ->assertOk()
        ->assertSee('Terms of Service')
        ->assertSee('Licensing and Software Editions')
        ->assertSee('Billing, Trials, and Refunds')
        ->assertSee('30-day money-back guarantee');

    // Security & VDP
    $this->get('/security')
        ->assertOk()
        ->assertSee('Security &amp; Vulnerability Disclosure', escape: false)
        ->assertSee('Vulnerability Disclosure Policy')
        ->assertSee('Safe Harbor')
        ->assertSee('security@synkk.space');
});
