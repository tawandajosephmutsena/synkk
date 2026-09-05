<?php

use App\Actions\Vaults\RestoreFileVersionAction;
use App\Actions\Vaults\SyncUploadAction;
use App\Models\Team;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultChangeLog;
use App\Models\VaultFile;
use App\Models\VaultFileVersion;
use App\Models\VaultPermission;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Vault Details')] class extends Component {
    public Vault $vault;

    #[Url]
    public string $activeTab = 'editor';

    #[Url]
    public ?string $path = null;

    #[Url]
    public ?int $file = null;

    // Active Editor File State
    public ?int $activeFileId = null;
    public ?VaultFile $activeFile = null;
    public string $editorContent = '';
    public string $editorTitle = '';
    public string $editorViewMode = 'split'; // 'split', 'source', 'preview'

    // New Note Modal State
    public string $newNotePath = '';

    // New Permission Rule Form
    public ?int $ruleUserId = null;
    public string $rulePath = '';
    public string $rulePermission = 'read_only';
    public bool $ruleIsFolder = true;

    // Vault Edit Form
    public string $editName = '';
    public string $editDescription = '';
    public string $editDefaultPermission = 'read_write';

    // Search
    public string $fileSearch = '';
    public string $editorSearch = '';

    // Version History Modal State
    public ?int $selectedFileId = null;
    public ?VaultFile $selectedFile = null;

    public function mount(Vault $vault): void
    {
        $this->vault = $vault;
        $this->editName = $vault->name;
        $this->editDescription = $vault->description ?? '';
        $this->editDefaultPermission = $vault->default_permission;

        $this->initializeActiveFile();
    }

    public function initializeActiveFile(): void
    {
        $user = Auth::user();

        if ($this->file) {
            $fileRecord = $this->vault->files()->where('is_deleted', false)->find($this->file);
            if ($fileRecord && $this->vault->permissionForPath($user, $fileRecord->path) !== 'hidden') {
                $this->selectFile($fileRecord->id);

                return;
            }
        }

        if ($this->path) {
            $fileRecord = $this->vault->files()->where('is_deleted', false)->where('path', $this->path)->first();
            if ($fileRecord && $this->vault->permissionForPath($user, $fileRecord->path) !== 'hidden') {
                $this->selectFile($fileRecord->id);

                return;
            }
        }

        // Default to first accessible markdown note
        $firstAccessible = $this->accessibleMarkdownFiles->first();
        if ($firstAccessible) {
            $this->selectFile($firstAccessible->id);
        } else {
            if ($this->activeTab === 'editor' && $this->accessibleFiles->isEmpty()) {
                $this->activeTab = 'files';
            }
        }
    }

    public function selectFile(int $fileId): void
    {
        $file = $this->vault->files()->where('is_deleted', false)->find($fileId);
        if (! $file) {
            return;
        }

        $user = Auth::user();
        $permission = $this->vault->permissionForPath($user, $file->path);
        if ($permission === 'hidden') {
            Flux::toast(variant: 'danger', text: __('You do not have permission to view this note.'));

            return;
        }

        $this->activeFileId = $file->id;
        $this->activeFile = $file;
        $this->file = $file->id;
        $this->path = $file->path;
        $this->editorContent = $file->getContents() ?? '';
        $this->editorTitle = pathinfo($file->path, PATHINFO_FILENAME);
    }

    public function saveFile(SyncUploadAction $uploader): void
    {
        if (! $this->activeFile) {
            return;
        }

        $user = Auth::user();
        $permission = $this->vault->permissionForPath($user, $this->activeFile->path);

        if ($permission !== 'read_write') {
            Flux::toast(variant: 'danger', text: __('You have read-only permissions for this note. Changes cannot be saved.'));

            return;
        }

        $uploader->execute(
            vault: $this->vault,
            user: $user,
            deviceName: 'Web Editor',
            path: $this->activeFile->path,
            contents: $this->editorContent,
            baseVersion: $this->activeFile->version,
        );

        $this->activeFile = $this->vault->files()->find($this->activeFile->id);

        Flux::toast(variant: 'success', text: __('Note saved. Revision v:version snapshot created.', ['version' => $this->activeFile->version]));
    }

    public function createNewNote(SyncUploadAction $uploader): void
    {
        $this->validate([
            'newNotePath' => ['required', 'string', 'max:500'],
        ]);

        $cleanPath = trim($this->newNotePath, '/');
        if (! str_ends_with(strtolower($cleanPath), '.md')) {
            $cleanPath .= '.md';
        }

        $user = Auth::user();
        $permission = $this->vault->permissionForPath($user, $cleanPath);

        if ($permission !== 'read_write') {
            Flux::toast(variant: 'danger', text: __('You do not have write permission to create files at this path.'));

            return;
        }

        $initialTitle = pathinfo($cleanPath, PATHINFO_FILENAME);
        $initialContent = "# {$initialTitle}\n\nStart typing your note here...\n";

        $uploader->execute(
            vault: $this->vault,
            user: $user,
            deviceName: 'Web Editor',
            path: $cleanPath,
            contents: $initialContent,
            baseVersion: 0,
        );

        $file = $this->vault->files()->where('path', $cleanPath)->first();
        $this->reset('newNotePath');
        $this->dispatch('close-modal', name: 'new-note-modal');

        if ($file) {
            $this->selectFile($file->id);
            $this->activeTab = 'editor';
        }

        Flux::toast(variant: 'success', text: __('Note ":path" created successfully.', ['path' => $cleanPath]));
    }

    public function showFileHistory(int $fileId): void
    {
        $this->selectedFileId = $fileId;
        $this->selectedFile = VaultFile::with(['versions.creator', 'lastModifier'])->find($fileId);
        $this->dispatch('open-modal', name: 'file-history');
    }

    public function restoreVersion(int $versionId, RestoreFileVersionAction $restoreAction): void
    {
        $versionRecord = VaultFileVersion::where('vault_id', $this->vault->id)->findOrFail($versionId);

        $restoreAction->execute($versionRecord, Auth::user());

        $this->selectedFile = VaultFile::with(['versions.creator', 'lastModifier'])->find($this->selectedFileId);

        if ($this->activeFileId === $this->selectedFileId) {
            $this->activeFile = $this->selectedFile;
            $this->editorContent = $this->selectedFile->getContents() ?? '';
        }

        Flux::toast(variant: 'success', text: __('Version v:version restored as current active note.', ['version' => $versionRecord->version]));
    }

    public function updateVaultSettings(): void
    {
        $this->validate([
            'editName' => ['required', 'string', 'max:255'],
            'editDescription' => ['nullable', 'string', 'max:1000'],
            'editDefaultPermission' => ['required', 'in:read_write,read_only,hidden'],
        ]);

        $this->vault->update([
            'name' => $this->editName,
            'description' => $this->editDescription,
            'default_permission' => $this->editDefaultPermission,
        ]);

        Flux::toast(variant: 'success', text: __('Vault settings updated.'));
    }

    public function addPathPermission(): void
    {
        $this->validate([
            'rulePath' => ['required', 'string', 'max:500'],
            'rulePermission' => ['required', 'in:read_write,read_only,hidden'],
            'ruleUserId' => ['nullable', 'exists:users,id'],
        ]);

        $cleanPath = trim($this->rulePath, '/');

        $existing = $this->vault->permissions()
            ->where('path', $cleanPath)
            ->where('user_id', $this->ruleUserId ?: null)
            ->first();

        if ($existing) {
            $existing->update([
                'permission' => $this->rulePermission,
                'is_folder' => $this->ruleIsFolder,
            ]);
            Flux::toast(variant: 'success', text: __('Rule for ":path" updated.', ['path' => $cleanPath]));
        } else {
            $this->vault->permissions()->create([
                'user_id' => $this->ruleUserId ?: null,
                'path' => $cleanPath,
                'permission' => $this->rulePermission,
                'is_folder' => $this->ruleIsFolder,
            ]);
            Flux::toast(variant: 'success', text: __('Permission rule added for ":path".', ['path' => $cleanPath]));
        }

        $this->reset('rulePath', 'ruleUserId');
        $this->rulePermission = 'read_only';
        $this->ruleIsFolder = true;
        $this->dispatch('close-modal', name: 'add-path-permission');
    }

    public function deletePermission(int $permissionId): void
    {
        $this->vault->permissions()->where('id', $permissionId)->delete();
        Flux::toast(variant: 'info', text: __('Permission rule removed.'));
    }

    public function deleteVault(): void
    {
        $name = $this->vault->name;
        $this->vault->delete();

        Flux::toast(variant: 'warning', text: __('Vault ":name" deleted.', ['name' => $name]));
        $this->redirectRoute('vaults.index', navigate: true);
    }

    #[Computed]
    public function accessibleFiles(): Collection
    {
        $user = Auth::user();

        return $this->vault->files()
            ->where('is_deleted', false)
            ->with('lastModifier')
            ->latest('updated_at')
            ->get()
            ->filter(fn (VaultFile $f) => $this->vault->permissionForPath($user, $f->path) !== 'hidden')
            ->values();
    }

    #[Computed]
    public function accessibleMarkdownFiles(): Collection
    {
        $user = Auth::user();

        return $this->accessibleFiles
            ->filter(fn (VaultFile $f) => $f->isMarkdown())
            ->values();
    }

    #[Computed]
    public function activeFilePermission(): string
    {
        if (! $this->activeFile) {
            return 'hidden';
        }

        return $this->vault->permissionForPath(Auth::user(), $this->activeFile->path);
    }

    #[Computed]
    public function canEditActiveFile(): bool
    {
        return $this->activeFilePermission === 'read_write';
    }

    #[Computed]
    public function teamMembers(): Collection
    {
        return $this->vault->team->members()->get();
    }

    #[Computed]
    public function permissionRules(): Collection
    {
        return $this->vault->permissions()
            ->with('user')
            ->orderBy('path')
            ->get();
    }

    #[Computed]
    public function files(): Collection
    {
        $query = $this->accessibleFiles;

        if (! empty($this->fileSearch)) {
            $search = strtolower($this->fileSearch);
            $query = $query->filter(fn (VaultFile $f) => str_contains(strtolower($f->path), $search));
        }

        return $query->take(100);
    }

    #[Computed]
    public function filteredEditorNotes(): Collection
    {
        $query = $this->accessibleMarkdownFiles;

        if (! empty($this->editorSearch)) {
            $search = strtolower($this->editorSearch);
            $query = $query->filter(fn (VaultFile $f) => str_contains(strtolower($f->path), $search));
        }

        return $query;
    }

    #[Computed]
    public function activities(): Collection
    {
        return $this->vault->changeLogs()
            ->with('user')
            ->latest('created_at')
            ->limit(50)
            ->get();
    }

    #[Computed]
    public function totalSizeFormatted(): string
    {
        $bytes = $this->vault->totalStorageBytes();

        return $bytes > 0 ? Number::fileSize($bytes, precision: 1) : '0 B';
    }

    #[Computed]
    public function graphData(): array
    {
        $markdownFiles = $this->accessibleMarkdownFiles;

        $nodes = [];
        $edges = [];
        $nodeMap = [];

        foreach ($markdownFiles as $file) {
            $basename = pathinfo($file->path, PATHINFO_FILENAME);
            $nodes[] = [
                'id' => $file->id,
                'name' => $basename,
                'path' => $file->path,
                'size' => $file->size,
                'version' => $file->version,
                'updated_at' => $file->updated_at?->diffForHumans() ?? '',
                'linksCount' => 0,
            ];
            $nodeIndex = count($nodes) - 1;
            $nodeMap[strtolower($file->path)] = $nodeIndex;
            $nodeMap[strtolower($basename)] = $nodeIndex;
            $nodeMap[strtolower($basename.'.md')] = $nodeIndex;
        }

        $createdEdges = [];
        foreach ($markdownFiles as $sourceIndex => $file) {
            $content = $file->getContents() ?? '';
            if (empty($content)) {
                continue;
            }

            preg_match_all('/\[\[(.*?)\]\]/', $content, $wikiMatches);
            $targets = [];
            if (! empty($wikiMatches[1])) {
                foreach ($wikiMatches[1] as $rawTarget) {
                    $cleanTarget = trim(explode('|', $rawTarget)[0]);
                    $cleanTarget = trim(explode('#', $cleanTarget)[0]);
                    if (! empty($cleanTarget)) {
                        $targets[] = strtolower($cleanTarget);
                    }
                }
            }

            preg_match_all('/\[.*?\]\((.*?\.md)\)/i', $content, $mdMatches);
            if (! empty($mdMatches[1])) {
                foreach ($mdMatches[1] as $rawMdTarget) {
                    $cleanMd = trim(urldecode($rawMdTarget));
                    $cleanMd = pathinfo($cleanMd, PATHINFO_FILENAME);
                    if (! empty($cleanMd)) {
                        $targets[] = strtolower($cleanMd);
                    }
                }
            }

            foreach ($targets as $targetName) {
                if (isset($nodeMap[$targetName])) {
                    $targetIndex = $nodeMap[$targetName];
                    if ($targetIndex !== $sourceIndex) {
                        $edgeKey = min($sourceIndex, $targetIndex).'-'.max($sourceIndex, $targetIndex);
                        if (! isset($createdEdges[$edgeKey])) {
                            $createdEdges[$edgeKey] = true;
                            $edges[] = [
                                'source' => $sourceIndex,
                                'target' => $targetIndex,
                            ];
                            $nodes[$sourceIndex]['linksCount']++;
                            $nodes[$targetIndex]['linksCount']++;
                        }
                    }
                }
            }
        }

        return [
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }

    #[Computed]
    public function renderedPreviewHtml(): string
    {
        if (empty($this->editorContent)) {
            return '<p class="text-zinc-400 italic text-xs">'.__('Empty note. Start typing to preview...').'</p>';
        }

        $content = $this->editorContent;

        // Convert Obsidian-style callouts: > [!TIP], > Tip:, etc.
        $content = preg_replace_callback('/^>\s*(?:\[!(TIP|NOTE|WARNING|IMPORTANT|CAUTION)\]|Tip:)\s*(.*?)$(?:\n(>(?:.*))*)?/mi', function ($matches) {
            $type = ! empty($matches[1]) ? strtoupper($matches[1]) : 'TIP';
            $title = ! empty(trim($matches[2])) && $type !== 'TIP' ? trim($matches[2]) : ($type === 'TIP' ? 'Tip' : ucfirst(strtolower($type)));
            $firstLineBody = ($type === 'TIP' && ! empty(trim($matches[2])) && trim($matches[2]) !== 'Tip') ? trim($matches[2]) : '';
            $body = '';
            if (! empty($matches[3])) {
                $bodyLines = explode("\n", trim($matches[3]));
                $cleanLines = array_map(fn ($line) => preg_replace('/^>\s?/', '', $line), $bodyLines);
                $body = implode("\n", $cleanLines);
            }
            if (! empty($firstLineBody)) {
                $body = $firstLineBody.(! empty($body) ? "\n".$body : '');
            }

            $containerClass = match ($type) {
                'TIP' => 'border-purple-600/50 bg-[#1E1630] text-purple-200',
                'WARNING', 'CAUTION' => 'border-amber-800/60 bg-[#2B2319] text-amber-200',
                'IMPORTANT' => 'border-red-800/60 bg-[#2B1919] text-red-200',
                default => 'border-blue-800/60 bg-[#19232B] text-blue-200',
            };

            $titleColor = match ($type) {
                'TIP' => 'text-[#C084FC]',
                'WARNING', 'CAUTION' => 'text-amber-300',
                'IMPORTANT' => 'text-red-300',
                default => 'text-blue-300',
            };

            return "<div class=\"callout-box my-5 rounded-2xl border p-5 {$containerClass} shadow-lg\"><div class=\"flex items-center gap-2 font-bold text-xs mb-2 {$titleColor}\"><span class=\"text-sm\">💡</span> <span class=\"tracking-wide\">{$title}</span></div><div class=\"text-xs leading-relaxed text-zinc-300\">{$body}</div></div>";
        }, $content);

        // Convert [[Wiki Links]]
        $content = preg_replace_callback('/\[\[(.*?)\]\]/', function ($matches) {
            $parts = explode('|', $matches[1]);
            $target = trim($parts[0]);
            $label = isset($parts[1]) ? trim($parts[1]) : $target;

            return "<span class=\"inline-flex items-center rounded bg-emerald-500/10 px-1.5 py-0.5 text-xs font-semibold text-emerald-400 border border-emerald-500/20\">[[{$label}]]</span>";
        }, $content);

        return Str::markdown($content);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Breadcrumb & Top Bar -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:breadcrumbs class="mb-1">
                <flux:breadcrumbs.item href="{{ route('vaults.index') }}">{{ __('Vaults') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>{{ $vault->name }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>

            <div class="flex items-center gap-3">
                <flux:heading size="xl" level="1" class="tracking-tight">{{ $vault->name }}</flux:heading>
                <code class="rounded bg-zinc-100 px-2 py-0.5 font-mono text-xs text-zinc-600 dark:bg-white/10 dark:text-zinc-300">{{ $vault->slug }}</code>

                @if ($vault->default_permission === 'read_write')
                    <flux:badge color="emerald" size="sm">{{ __('Default: Read & Write') }}</flux:badge>
                @elseif ($vault->default_permission === 'read_only')
                    <flux:badge color="amber" size="sm">{{ __('Default: Read Only') }}</flux:badge>
                @else
                    <flux:badge color="zinc" size="sm">{{ __('Default: Restricted') }}</flux:badge>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-2">
            <flux:modal.trigger name="new-note-modal">
                <flux:button variant="primary" size="sm" icon="plus" class="!bg-[#0D3B29] !text-white hover:!bg-[#0D3B29]/90 !rounded-full px-4 font-bold text-xs shadow-xs">
                    {{ __('New Note') }}
                </flux:button>
            </flux:modal.trigger>

            @if ($activeTab === 'permissions')
                <flux:modal.trigger name="add-path-permission">
                    <flux:button variant="primary" size="sm" icon="plus" class="!rounded-full px-4 text-xs font-bold">
                        {{ __('Add Path Rule') }}
                    </flux:button>
                </flux:modal.trigger>
            @endif

            <flux:dropdown>
                <flux:button variant="subtle" size="sm" icon="ellipsis-horizontal" aria-label="Vault actions" class="!rounded-full" />
                <flux:menu>
                    <flux:menu.item icon="arrow-path" wire:click="$refresh">{{ __('Refresh Data') }}</flux:menu.item>
                    <flux:menu.item icon="cog" wire:click="$set('activeTab', 'settings')">{{ __('Vault Settings') }}</flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </div>
    </div>

    <!-- Mini Stat Summary Row -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <flux:card variant="soft" class="py-3 px-4 rounded-2xl border-gray-200/80 dark:border-zinc-800">
            <div class="flex items-center justify-between">
                <flux:text class="text-xs text-zinc-400">{{ __('Accessible Notes') }}</flux:text>
                <flux:icon icon="document-text" class="size-4 text-emerald-600 dark:text-emerald-400" />
            </div>
            <div class="mt-1 flex items-baseline gap-2">
                <span class="text-xl font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($this->accessibleMarkdownFiles->count()) }}</span>
                <span class="text-xs text-zinc-400">{{ __('notes') }} ({{ number_format($this->accessibleFiles->count()) }} {{ __('total') }})</span>
            </div>
        </flux:card>

        <flux:card variant="soft" class="py-3 px-4 rounded-2xl border-gray-200/80 dark:border-zinc-800">
            <div class="flex items-center justify-between">
                <flux:text class="text-xs text-zinc-400">{{ __('Storage Footprint') }}</flux:text>
                <flux:icon icon="server-stack" class="size-4 text-zinc-400" />
            </div>
            <div class="mt-1 flex items-baseline gap-2">
                <span class="text-xl font-bold text-zinc-900 dark:text-zinc-100">{{ $this->totalSizeFormatted }}</span>
                <span class="text-xs text-zinc-400">{{ __('synced storage') }}</span>
            </div>
        </flux:card>

        <flux:card variant="soft" class="py-3 px-4 rounded-2xl border-gray-200/80 dark:border-zinc-800">
            <div class="flex items-center justify-between">
                <flux:text class="text-xs text-zinc-400">{{ __('Current Revision') }}</flux:text>
                <flux:icon icon="clock" class="size-4 text-zinc-400" />
            </div>
            <div class="mt-1 flex items-baseline gap-2">
                <span class="text-xl font-bold text-zinc-900 dark:text-zinc-100">v{{ $vault->latestVersion() }}</span>
                <span class="text-xs text-emerald-600 dark:text-emerald-400">{{ __('Latest sync state') }}</span>
            </div>
        </flux:card>
    </div>

    <!-- Donezo Signature Segmented Navigation -->
    <div class="flex items-center gap-2 border-b border-gray-200 pb-3 dark:border-zinc-800">
        <flux:radio.group wire:model.live="activeTab" variant="segmented" size="sm">
            <flux:radio value="editor">
                <span class="flex items-center gap-1.5 font-bold text-xs">
                    <flux:icon icon="document-text" class="size-4" />
                    <span>{{ __('Markdown Editor') }}</span>
                </span>
            </flux:radio>
            <flux:radio value="graph">
                <span class="flex items-center gap-1.5 font-bold text-xs">
                    <flux:icon icon="squares-2x2" class="size-4" />
                    <span>{{ __('Graph View') }}</span>
                </span>
            </flux:radio>
            <flux:radio value="files">
                <span class="flex items-center gap-1.5 font-bold text-xs">
                    <flux:icon icon="folder-open" class="size-4" />
                    <span>{{ __('Files Explorer') }}</span>
                </span>
            </flux:radio>
            <flux:radio value="permissions">
                <span class="flex items-center gap-1.5 font-bold text-xs">
                    <flux:icon icon="shield-check" class="size-4" />
                    <span>{{ __('Permissions Matrix') }}</span>
                    <flux:badge size="sm" color="zinc" rounded inset>{{ $this->permissionRules->count() }}</flux:badge>
                </span>
            </flux:radio>
            <flux:radio value="activity">
                <span class="flex items-center gap-1.5 font-bold text-xs">
                    <flux:icon icon="clock" class="size-4" />
                    <span>{{ __('Sync Audit Trail') }}</span>
                </span>
            </flux:radio>
            <flux:radio value="settings">
                <span class="flex items-center gap-1.5 font-bold text-xs">
                    <flux:icon icon="cog" class="size-4" />
                    <span>{{ __('Settings') }}</span>
                </span>
            </flux:radio>
        </flux:radio.group>
    </div>

    <!-- TAB 1: MARKDOWN EDITOR (Strictly matching user's Pandocs inspiration image) -->
    @if ($activeTab === 'editor')
        <div
            x-data="{
                content: @entangle('editorContent').live,
                viewMode: @entangle('editorViewMode'),
                sidebarOpen: true,
                lineCount: 1,
                wordCount: 0,
                charCount: 0,
                headings: [],
                lineTypes: [],
                isDirty: false,
                initialContent: '',
                activeHeading: '',
                copiedShareLink: false,
                quickInsertOpen: true,

                init() {
                    this.initialContent = this.content || '';
                    this.updateMetrics();
                    this.$watch('content', (val) => {
                        this.updateMetrics();
                        this.isDirty = (val !== this.initialContent);
                    });
                },

                updateMetrics() {
                    const text = this.content || '';
                    const lines = text.split('\n');
                    this.lineCount = Math.max(1, lines.length);
                    this.charCount = text.length;
                    const words = text.trim().split(/\s+/).filter(Boolean);
                    this.wordCount = words.length;

                    // Compute syntax categories for realistic Pandocs minimap strip
                    this.lineTypes = lines.slice(0, 42).map(line => {
                        const t = line.trim();
                        if (t.startsWith('# ')) return 'h1';
                        if (t.startsWith('## ')) return 'h2';
                        if (t.startsWith('### ')) return 'h3';
                        if (t.startsWith('```')) return 'code';
                        if (t.startsWith('> [!TIP]') || t.startsWith('> Tip:')) return 'tip';
                        if (t.startsWith('- ') || t.startsWith('1. ') || t.startsWith('* ')) return 'list';
                        if (t.startsWith('> ')) return 'quote';
                        if (t === '---' || t === '***') return 'hr';
                        if (t.length === 0) return 'empty';
                        return 'text';
                    });

                    this.parseHeadings();
                },

                parseHeadings() {
                    const text = this.content || '';
                    const lines = text.split('\n');
                    const list = [];
                    lines.forEach((line, idx) => {
                        const match = line.match(/^(#{1,3})\s+(.+)$/);
                        if (match) {
                            list.push({
                                level: match[1].length,
                                title: match[2].trim(),
                                line: idx + 1
                            });
                        }
                    });
                    this.headings = list;
                    if (list.length > 0 && !this.activeHeading) {
                        this.activeHeading = list[0].title;
                    }
                },

                insertFormat(prefix, suffix = '', defaultText = '') {
                    const textarea = this.$refs.editorTextarea;
                    if (!textarea) return;
                    const start = textarea.selectionStart;
                    const end = textarea.selectionEnd;
                    const selected = textarea.value.substring(start, end) || defaultText;
                    const replacement = prefix + selected + suffix;
                    textarea.setRangeText(replacement, start, end, 'select');
                    this.content = textarea.value;
                    this.updateMetrics();
                    textarea.focus();
                },

                insertLinePrefix(prefix) {
                    const textarea = this.$refs.editorTextarea;
                    if (!textarea) return;
                    const start = textarea.selectionStart;
                    const text = textarea.value;
                    const lineStart = text.lastIndexOf('\n', start - 1) + 1;
                    textarea.setRangeText(prefix, lineStart, lineStart, 'end');
                    this.content = textarea.value;
                    this.updateMetrics();
                    textarea.focus();
                },

                insertTable() {
                    const tableTemplate = '\n| Header 1 | Header 2 | Header 3 |\n| --- | --- | --- |\n| Cell 1 | Cell 2 | Cell 3 |\n| Cell 4 | Cell 5 | Cell 6 |\n\n';
                    this.insertFormat('', '', tableTemplate);
                },

                scrollToHeading(heading) {
                    this.activeHeading = heading.title;
                    const preview = this.$refs.previewPane;
                    if (!preview) return;
                    const elements = preview.querySelectorAll('h1, h2, h3');
                    for (let el of elements) {
                        if (el.textContent.includes(heading.title)) {
                            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            break;
                        }
                    }
                },

                handleEditorKeydown(e) {
                    if (e.key === 'Tab') {
                        e.preventDefault();
                        this.insertFormat('  ');
                        return;
                    }
                    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 's') {
                        e.preventDefault();
                        this.$wire.saveFile();
                        return;
                    }
                    const pairs = { '(': ')', '[': ']', '{': '}', '`': '`', '"': '"', '\'': '\'' };
                    if (pairs[e.key]) {
                        const textarea = this.$refs.editorTextarea;
                        if (!textarea) return;
                        const start = textarea.selectionStart;
                        const end = textarea.selectionEnd;
                        if (start !== end) {
                            e.preventDefault();
                            const selected = textarea.value.substring(start, end);
                            textarea.setRangeText(e.key + selected + pairs[e.key], start, end, 'select');
                            this.content = textarea.value;
                            this.updateMetrics();
                        }
                    }
                }
            }"
            class="flex flex-col rounded-3xl border border-zinc-800 bg-[#12131A] text-zinc-100 shadow-2xl overflow-hidden min-h-[760px] relative"
        >
            <!-- TOP HEADER (Pandocs style: Dropdown Title, Date, Collaborators, Save/Submit Button) -->
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#252836] bg-[#181A22] px-5 py-3">
                <div class="flex items-center gap-3 min-w-0">
                    <!-- Note Selector Dropdown -->
                    <flux:dropdown position="bottom" align="start">
                        <button type="button" class="group flex items-center gap-2 hover:bg-zinc-800/90 px-2.5 py-1.5 rounded-xl transition-all cursor-pointer text-left border border-transparent hover:border-zinc-700">
                            <flux:icon icon="document-text" class="size-4 text-zinc-300 group-hover:text-emerald-400 transition-colors" />
                            <span class="font-extrabold text-sm text-white tracking-tight truncate max-w-[180px] sm:max-w-xs">
                                {{ $this->editorTitle ?: __('Select or Create a Note') }}
                            </span>
                            <flux:icon icon="chevron-down" class="size-3 text-zinc-400 group-hover:text-white transition-colors" />
                        </button>
                        <flux:menu class="min-w-64 max-h-72 overflow-y-auto">
                            <flux:menu.heading class="text-[10px] font-bold uppercase tracking-wider text-zinc-400">{{ __('Vault Notes') }}</flux:menu.heading>
                            @foreach ($this->accessibleMarkdownFiles as $f)
                                <flux:menu.item wire:click="selectFile({{ $f->id }})" class="cursor-pointer py-1.5">
                                    <div class="flex w-full items-center justify-between gap-3">
                                        <span class="truncate text-xs {{ $this->activeFileId === $f->id ? 'font-bold text-emerald-400' : 'text-zinc-300' }}">{{ pathinfo($f->path, PATHINFO_FILENAME) }}</span>
                                        <span class="font-mono text-[10px] text-zinc-500">v{{ $f->version }}</span>
                                    </div>
                                </flux:menu.item>
                            @endforeach
                        </flux:menu>
                    </flux:dropdown>

                    @if ($this->activeFile)
                        <!-- Last Modified Date Pill -->
                        <div class="hidden sm:inline-flex items-center gap-1.5 rounded-full bg-zinc-800/90 px-3 py-1 text-[11px] text-zinc-300 border border-zinc-700/60 shadow-xs">
                            <flux:icon icon="calendar" class="size-3 text-zinc-400" />
                            <span>{{ $this->activeFile->updated_at?->format('M d, Y') }}</span>
                        </div>
                    @endif
                </div>

                <div class="flex items-center gap-2.5">
                    <!-- Collaborators Stack -->
                    <div class="hidden sm:flex items-center -space-x-1.5 overflow-hidden" title="{{ __('Collaborators') }}">
                        @foreach ($this->teamMembers->take(3) as $m)
                            <span class="inline-flex size-6 items-center justify-center rounded-full bg-[#0D3B29] ring-2 ring-[#181A22] text-[10px] font-bold text-emerald-200" title="{{ $m->name }}">
                                {{ $m->initials() }}
                            </span>
                        @endforeach
                        @if ($this->teamMembers->count() > 3)
                            <span class="inline-flex size-6 items-center justify-center rounded-full bg-zinc-700 ring-2 ring-[#181A22] text-[9px] font-bold text-white">
                                +{{ $this->teamMembers->count() - 3 }}
                            </span>
                        @endif
                    </div>

                    @if ($this->activeFile)
                        <!-- Share Button -->
                        <button
                            type="button"
                            @click="
                                navigator.clipboard.writeText(window.location.href);
                                copiedShareLink = true;
                                setTimeout(() => copiedShareLink = false, 2500);
                            "
                            class="hidden md:flex items-center gap-1.5 rounded-full border border-zinc-700/80 bg-zinc-800/80 px-3 py-1.5 text-xs font-semibold text-zinc-300 hover:bg-zinc-700 hover:text-white transition-colors cursor-pointer shadow-xs"
                            title="{{ __('Copy share link') }}"
                        >
                            <flux:icon icon="share" class="size-3.5" />
                            <span x-text="copiedShareLink ? '{{ __('Copied!') }}' : '{{ __('Share') }}'"></span>
                        </button>

                        <!-- Suggesting Changes / Permission Pill -->
                        @if ($this->canEditActiveFile)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-950/70 border border-emerald-600/40 px-2.5 py-1 text-[10px] font-bold text-emerald-400">
                                <flux:icon icon="pencil-square" class="size-3 text-emerald-400" />
                                <span>{{ __('Suggesting Changes') }}</span>
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-950/70 border border-amber-600/40 px-2.5 py-1 text-[10px] font-bold text-amber-400">
                                <flux:icon icon="lock-closed" class="size-3 text-amber-400" />
                                <span>{{ __('Read-Only') }}</span>
                            </span>
                        @endif

                        <!-- View Changes History Button -->
                        <button
                            type="button"
                            wire:click="showFileHistory({{ $this->activeFile->id }})"
                            class="flex items-center gap-1.5 rounded-full border border-zinc-700/80 bg-zinc-800/80 px-3 py-1.5 text-xs font-semibold text-zinc-300 hover:bg-zinc-700 hover:text-white transition-colors cursor-pointer shadow-xs"
                        >
                            <flux:icon icon="clock" class="size-3.5" />
                            <span>{{ __('View changes') }}</span>
                        </button>

                        <!-- Primary Save Button -->
                        <button
                            type="button"
                            wire:click="saveFile"
                            @if (! $this->canEditActiveFile) disabled @endif
                            class="{{ $this->canEditActiveFile ? 'bg-[#7C3AED] hover:bg-[#6D28D9] text-white shadow-xs active:scale-98 cursor-pointer' : 'bg-zinc-800 text-zinc-500 cursor-not-allowed border border-zinc-700' }} flex items-center gap-2 rounded-full px-4 sm:px-5 py-1.5 text-xs font-bold transition-all shadow-md"
                            title="{{ $this->canEditActiveFile ? __('Save Note (Cmd+S / Ctrl+S)') : __('You have read-only access to this file') }}"
                        >
                            <flux:icon icon="arrow-up-tray" class="size-3.5" />
                            <span>{{ __('Save Changes') }}</span>
                            <span x-show="isDirty" class="size-2 rounded-full bg-amber-400 animate-ping"></span>
                        </button>

                        <!-- Close Button -->
                        <button
                            type="button"
                            wire:click="$set('activeTab', 'files')"
                            class="size-7 flex items-center justify-center rounded-lg text-zinc-400 hover:bg-zinc-800 hover:text-white transition-colors cursor-pointer"
                            title="{{ __('Close Editor') }}"
                        >
                            <flux:icon icon="x-mark" class="size-4" />
                        </button>
                    @endif
                </div>
            </div>

            <!-- FORMATTING TOOLBAR (Matching Pandocs) -->
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[#252836] bg-[#161821] px-4 py-2">
                <div class="flex flex-wrap items-center gap-1 text-zinc-300">
                    <!-- Text Formatting -->
                    <button type="button" @click="insertFormat('**', '**', 'bold text')" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 font-black text-xs" title="Bold (Ctrl+B)">B</button>
                    <button type="button" @click="insertFormat('*', '*', 'italic text')" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 italic font-serif text-xs" title="Italic (Ctrl+I)">I</button>
                    <button type="button" @click="insertFormat('<u>', '</u>', 'underlined text')" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 underline text-xs" title="Underline">U</button>
                    <button type="button" @click="insertFormat('~~', '~~', 'strikethrough text')" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 line-through text-xs" title="Strikethrough">S</button>

                    <span class="h-4 w-px bg-zinc-700 mx-1"></span>

                    <!-- Alignment -->
                    <button type="button" @click="insertLinePrefix('')" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 text-xs" title="Left Align">≡</button>
                    <button type="button" @click="insertFormat('<center>', '</center>')" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 text-xs" title="Center Align">⩸</button>
                    <button type="button" @click="insertFormat('<div align=\'right\'>', '</div>')" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 text-xs" title="Right Align">⩹</button>

                    <span class="h-4 w-px bg-zinc-700 mx-1"></span>

                    <!-- Headings -->
                    <button type="button" @click="insertLinePrefix('# ')" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 font-bold text-xs" title="H1 Heading">H1</button>
                    <button type="button" @click="insertLinePrefix('## ')" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 font-bold text-xs" title="H2 Heading">H2</button>
                    <button type="button" @click="insertLinePrefix('### ')" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 font-bold text-xs" title="H3 Heading">H3</button>

                    <span class="h-4 w-px bg-zinc-700 mx-1"></span>

                    <!-- Lists -->
                    <button type="button" @click="insertLinePrefix('- ')" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 text-xs" title="Bullet List">•≡</button>
                    <button type="button" @click="insertLinePrefix('1. ')" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 text-xs font-mono" title="Numbered List">1≡</button>
                    <button type="button" @click="insertLinePrefix('- [ ] ')" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 text-xs" title="Task List">☑</button>

                    <span class="h-4 w-px bg-zinc-700 mx-1"></span>

                    <!-- Insert Links & Media -->
                    <button type="button" @click="insertFormat('[', '](https://)', 'Link Title')" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 text-xs" title="Insert Link">🔗</button>
                    <button type="button" @click="insertFormat('![', '](image-url.png)', 'Alt Text')" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 text-xs" title="Insert Image">🖼️</button>
                    <button type="button" @click="insertFormat('[📎 ', '](attachment.pdf)', 'Document')" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 text-xs" title="Attach File">📎</button>
                    <button type="button" @click="insertFormat('[[', ']]', 'Wiki Note Name')" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 text-xs font-bold text-emerald-400" title="Wiki Link [[Note]]">[[ ]]</button>

                    <span class="h-4 w-px bg-zinc-700 mx-1"></span>

                    <!-- Code & Block elements -->
                    <button type="button" @click="insertFormat('`', '`', 'code')" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 text-xs font-mono text-cyan-400" title="Inline Code">&lt;/&gt;</button>
                    <button type="button" @click="insertFormat('```javascript\n', '\n```', '// your code here')" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 text-xs font-mono" title="Code Block">{ }</button>
                    <button type="button" @click="insertTable()" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 text-xs" title="Insert Table">⊞</button>
                    <button type="button" @click="insertLinePrefix('> Tip: ')" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 text-xs text-purple-400" title="Tip Callout Box">💡</button>
                    <button type="button" @click="insertFormat('\n---\n')" class="size-7 flex items-center justify-center rounded hover:bg-zinc-800 text-xs" title="Horizontal Rule">—</button>
                </div>

                <!-- View Mode Switcher -->
                <div class="flex items-center rounded-lg bg-zinc-900/90 p-0.5 border border-zinc-800">
                    <button
                        type="button"
                        @click="viewMode = 'split'"
                        :class="viewMode === 'split' ? 'bg-zinc-700 text-white font-bold' : 'text-zinc-400 hover:text-white'"
                        class="px-2.5 py-1 text-[11px] rounded transition-colors cursor-pointer"
                    >
                        {{ __('Split') }}
                    </button>
                    <button
                        type="button"
                        @click="viewMode = 'source'"
                        :class="viewMode === 'source' ? 'bg-zinc-700 text-white font-bold' : 'text-zinc-400 hover:text-white'"
                        class="px-2.5 py-1 text-[11px] rounded transition-colors cursor-pointer"
                    >
                        {{ __('Source') }}
                    </button>
                    <button
                        type="button"
                        @click="viewMode = 'preview'"
                        :class="viewMode === 'preview' ? 'bg-zinc-700 text-white font-bold' : 'text-zinc-400 hover:text-white'"
                        class="px-2.5 py-1 text-[11px] rounded transition-colors cursor-pointer"
                    >
                        {{ __('Preview') }}
                    </button>
                </div>
            </div>

            <!-- READ ONLY NOTICE BANNER -->
            @if (! $this->canEditActiveFile && $this->activeFile)
                <div class="flex items-center gap-2 bg-amber-950/40 border-b border-amber-800/40 px-5 py-2 text-xs text-amber-300">
                    <flux:icon icon="lock-closed" class="size-4 text-amber-400 shrink-0" />
                    <span>{{ __('You have Read-Only permissions for this note. You may view and navigate the content, but changes cannot be saved.') }}</span>
                </div>
            @endif

            <!-- MAIN WORKSPACE -->
            <div class="flex flex-1 min-h-[580px] overflow-hidden relative">
                <!-- FLOATING EXPAND SIDEBAR BUTTON (Shown when outline sidebar is collapsed) -->
                <button
                    type="button"
                    x-show="!sidebarOpen"
                    @click="sidebarOpen = true"
                    class="absolute top-3 left-3 z-30 flex items-center gap-1.5 rounded-xl bg-zinc-900/95 border border-zinc-700 px-2.5 py-1.5 text-xs text-zinc-300 hover:text-white hover:border-purple-500 shadow-xl backdrop-blur transition-all cursor-pointer"
                    title="{{ __('Expand Outline & Notes') }}"
                >
                    <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.21 5.23a.75.75 0 011.06-.02l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.04-1.08L8.168 10 4.23 6.29a.75.75 0 01-.02-1.06zm6 0a.75.75 0 011.06-.02l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.04-1.08L14.168 10l-3.938-3.71a.75.75 0 01-.02-1.06z" clip-rule="evenodd" />
                    </svg>
                    <span class="font-medium text-[11px]">{{ __('Outline') }}</span>
                </button>

                <!-- LEFT COLLAPSIBLE SIDEBAR: Back to Docs, Search, Outline, Notes -->
                <div
                    x-show="sidebarOpen"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="-translate-x-full opacity-0"
                    x-transition:enter-end="translate-x-0 opacity-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="translate-x-0 opacity-100"
                    x-transition:leave-end="-translate-x-full opacity-0"
                    class="w-64 shrink-0 border-r border-[#252836] bg-[#12141C] flex flex-col justify-between hidden md:flex"
                >
                    <div class="p-3.5 space-y-3.5 overflow-y-auto max-h-[600px]">
                        <!-- Back to All Documents -->
                        <button
                            type="button"
                            wire:click="$set('activeTab', 'files')"
                            class="flex items-center gap-2 text-xs font-semibold text-zinc-400 hover:text-white transition-colors cursor-pointer w-full text-left"
                        >
                            <span>←</span>
                            <span>{{ __('Back to All Documents') }}</span>
                        </button>

                        <!-- Search Notes Input -->
                        <div class="relative">
                            <flux:icon icon="magnifying-glass" class="size-3.5 text-zinc-500 absolute left-2.5 top-2.5" />
                            <input
                                wire:model.live.debounce.250ms="editorSearch"
                                type="text"
                                placeholder="{{ __('Search...') }}"
                                class="w-full rounded-xl bg-zinc-900/90 border border-zinc-800 pl-8 pr-8 py-1.5 text-xs text-white placeholder:text-zinc-500 focus:outline-none focus:border-purple-500"
                            />
                            <span class="absolute right-2 top-2 text-[10px] font-mono text-zinc-500 bg-zinc-800 px-1 rounded">⌘K</span>
                        </div>

                        <!-- LIVE DOCUMENT OUTLINE (Pandocs signature headings navigator) -->
                        <div>
                            <div class="flex items-center justify-between text-[10px] font-bold uppercase tracking-wider text-zinc-500 mb-2">
                                <span>{{ __('Document Outline') }}</span>
                                <span class="text-[9px] font-mono" x-text="headings.length"></span>
                            </div>
                            <div class="space-y-0.5">
                                <template x-for="(h, idx) in headings" :key="idx">
                                    <button
                                        type="button"
                                        @click="scrollToHeading(h)"
                                        :class="activeHeading === h.title ? 'bg-purple-950/40 text-purple-300 font-bold border-l-2 border-purple-500 pl-2' : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800/40 pl-2.5 border-l-2 border-transparent'"
                                        class="block w-full text-left truncate text-xs transition-colors py-1 rounded-r-md cursor-pointer"
                                    >
                                        <span class="text-zinc-600 mr-1 font-mono text-[10px]" x-text="'#'.repeat(h.level)"></span>
                                        <span x-text="h.title"></span>
                                    </button>
                                </template>
                                <div x-show="headings.length === 0" class="text-[11px] text-zinc-600 italic py-1 pl-2">
                                    {{ __('Add headings (# H1, ## H2) to build outline') }}
                                </div>
                            </div>
                        </div>

                        <!-- VAULT NOTES LIST -->
                        <div class="pt-3 border-t border-[#252836]">
                            <div class="flex items-center justify-between text-[10px] font-bold uppercase tracking-wider text-zinc-500 mb-2">
                                <span>{{ __('Vault Notes') }}</span>
                                <span class="text-[10px]">{{ $this->filteredEditorNotes->count() }}</span>
                            </div>
                            <div class="space-y-1">
                                @forelse ($this->filteredEditorNotes as $f)
                                    <button
                                        type="button"
                                        wire:click="selectFile({{ $f->id }})"
                                        class="{{ $this->activeFileId === $f->id ? 'bg-[#0D3B29]/40 text-emerald-300 font-bold border-emerald-600/40' : 'text-zinc-400 hover:bg-zinc-800/50 hover:text-white border-transparent' }} flex items-center justify-between w-full rounded-lg px-2.5 py-1.5 text-xs text-left border transition-all cursor-pointer"
                                    >
                                        <div class="flex items-center gap-2 truncate">
                                            <flux:icon icon="document-text" class="size-3.5 shrink-0" />
                                            <span class="truncate">{{ pathinfo($f->path, PATHINFO_FILENAME) }}</span>
                                        </div>
                                        <span class="text-[10px] text-zinc-600 shrink-0 font-mono">v{{ $f->version }}</span>
                                    </button>
                                @empty
                                    <div class="py-3 text-center text-xs text-zinc-500">
                                        {{ __('No notes found.') }}
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Bar: Collapse Button + New Note Action -->
                    <div class="p-2.5 border-t border-[#252836] bg-[#0E1015] flex items-center justify-between gap-2">
                        <!-- Collapse Sidebar Toggle (Matching ⇤ in screenshot) -->
                        <button
                            type="button"
                            @click="sidebarOpen = false"
                            class="p-1.5 rounded-lg text-zinc-400 hover:text-white hover:bg-zinc-800 transition-colors cursor-pointer"
                            title="{{ __('Collapse Outline Sidebar') }}"
                        >
                            <svg class="size-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M15.79 14.77a.75.75 0 01-1.06.02l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 111.04 1.08L11.832 10l3.938 3.71a.75.75 0 01.02 1.06zm-6 0a.75.75 0 01-1.06.02l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 111.04 1.08L5.832 10l3.938 3.71a.75.75 0 01.02 1.06z" clip-rule="evenodd" />
                            </svg>
                        </button>

                        <flux:modal.trigger name="new-note-modal">
                            <button type="button" class="flex-1 flex items-center justify-center gap-1.5 rounded-xl bg-zinc-800 hover:bg-zinc-700 py-1.5 px-3 text-xs font-bold text-white transition-colors cursor-pointer">
                                <flux:icon icon="plus" class="size-3" />
                                <span>{{ __('New Note') }}</span>
                            </button>
                        </flux:modal.trigger>
                    </div>
                </div>

                <!-- SPLIT EDITOR CONTENT AREA -->
                <div class="flex flex-1 overflow-hidden relative">
                    <!-- LEFT PANE: SOURCE CODE EDITOR -->
                    <div
                        x-show="viewMode === 'split' || viewMode === 'source'"
                        :class="viewMode === 'split' ? 'w-1/2' : 'w-full'"
                        class="flex flex-1 border-r border-[#252836] bg-[#0E1015] overflow-hidden"
                    >
                        <!-- Code Minimap Strip on Far Left (Pandocs design with authentic syntax-colored bars) -->
                        <div class="w-8 shrink-0 border-r border-zinc-800/60 bg-[#0B0D12] py-3 px-1 flex flex-col gap-0.5 overflow-hidden opacity-80">
                            <template x-for="(type, idx) in lineTypes" :key="idx">
                                <div
                                    :class="{
                                        'bg-rose-500/90 h-[3px]': type === 'h1',
                                        'bg-sky-400/90 h-[3px]': type === 'h2',
                                        'bg-indigo-400/80 h-[2.5px]': type === 'h3',
                                        'bg-emerald-400/90 h-[2px]': type === 'code',
                                        'bg-purple-400/90 h-[2.5px]': type === 'tip',
                                        'bg-amber-400/70 h-[2px]': type === 'list',
                                        'bg-zinc-500/40 h-[2px]': type === 'text',
                                        'bg-transparent h-[2px]': type === 'empty',
                                        'bg-zinc-600/60 h-[1px]': type === 'hr',
                                        'bg-zinc-600/40 h-[2px]': type === 'quote'
                                    }"
                                    :style="`width: ${type === 'h1' ? '90%' : (type === 'h2' ? '75%' : (type === 'empty' ? '0%' : Math.max(30, (idx * 23) % 95) + '%'))}; border-radius: 1px; margin-bottom: 2px;`"
                                ></div>
                            </template>
                        </div>

                        <!-- Line Numbers Gutter -->
                        <div class="w-10 shrink-0 py-3 text-right pr-2 text-zinc-600 font-mono text-xs select-none border-r border-zinc-800/60 bg-[#0C0E13]">
                            <template x-for="n in lineCount" :key="n">
                                <div class="leading-relaxed text-[11px]" x-text="n"></div>
                            </template>
                        </div>

                        <!-- Textarea -->
                        <div class="flex-1 relative overflow-y-auto">
                            <textarea
                                x-ref="editorTextarea"
                                x-model="content"
                                @keydown="handleEditorKeydown($event)"
                                @if (! $this->canEditActiveFile) readonly @endif
                                placeholder="{{ __('Write your note in Markdown...') }}"
                                class="w-full h-full min-h-[580px] bg-transparent p-4 font-mono text-xs sm:text-sm leading-relaxed text-zinc-100 placeholder:text-zinc-600 focus:outline-none resize-none selection:bg-purple-900/60"
                                spellcheck="false"
                            >{{ $editorContent }}</textarea>
                        </div>
                    </div>

                    <!-- FLOATING QUICK-INSERT GUTTER (Iconic center strip in Pandocs) -->
                    <div
                        x-show="viewMode === 'split'"
                        class="absolute left-1/2 -translate-x-1/2 top-16 z-20 hidden lg:flex flex-col items-center gap-2"
                    >
                        <!-- Purple Toggle Button -->
                        <button
                            type="button"
                            @click="quickInsertOpen = !quickInsertOpen"
                            class="size-6 rounded-full bg-[#7C3AED] hover:bg-[#6D28D9] text-white flex items-center justify-center shadow-lg transition-transform cursor-pointer"
                            :class="quickInsertOpen ? 'rotate-45' : ''"
                            title="{{ __('Toggle quick insert tools') }}"
                        >
                            <flux:icon icon="plus" class="size-3" />
                        </button>

                        <div
                            x-show="quickInsertOpen"
                            x-transition
                            class="flex flex-col items-center gap-1.5 rounded-2xl border border-zinc-700/80 bg-zinc-900/95 p-1.5 shadow-2xl backdrop-blur"
                        >
                            <button type="button" @click="insertFormat('[', '](https://)', 'Link Title')" class="size-7 rounded-lg text-zinc-400 hover:text-white hover:bg-zinc-800 flex items-center justify-center text-xs" title="Link">🔗</button>
                            <button type="button" @click="insertFormat('![', '](image.png)', 'Alt')" class="size-7 rounded-lg text-zinc-400 hover:text-white hover:bg-zinc-800 flex items-center justify-center text-xs" title="Image">🖼️</button>
                            <button type="button" @click="insertFormat('[📎 ', '](file.pdf)', 'File')" class="size-7 rounded-lg text-zinc-400 hover:text-white hover:bg-zinc-800 flex items-center justify-center text-xs" title="Attachment">📎</button>
                            <button type="button" @click="insertFormat('`', '`', 'code')" class="size-7 rounded-lg text-cyan-400 hover:bg-zinc-800 flex items-center justify-center text-xs font-mono" title="Code">&lt;&gt;</button>
                            <button type="button" @click="insertTable()" class="size-7 rounded-lg text-zinc-400 hover:text-white hover:bg-zinc-800 flex items-center justify-center text-xs" title="Table">⊞</button>
                            <button type="button" @click="insertLinePrefix('> Tip: ')" class="size-7 rounded-lg text-purple-400 hover:bg-zinc-800 flex items-center justify-center text-xs" title="Tip Box">💡</button>
                        </div>
                    </div>

                    <!-- RIGHT PANE: LIVE RENDERED PREVIEW (Pandocs style typography & Callouts) -->
                    <div
                        x-show="viewMode === 'split' || viewMode === 'preview'"
                        x-ref="previewPane"
                        :class="viewMode === 'split' ? 'w-1/2' : 'w-full'"
                        class="flex-1 overflow-y-auto bg-[#12131A] p-6 lg:p-10"
                    >
                        <!-- Active block focus indicator line (matching Pandocs purple accent line) -->
                        <div class="h-0.5 w-full bg-gradient-to-r from-purple-500/80 via-purple-500/30 to-transparent mb-6"></div>

                        <div class="prose prose-invert max-w-none text-zinc-200 text-xs sm:text-sm leading-relaxed">
                            {!! $this->renderedPreviewHtml !!}
                        </div>
                    </div>
                </div>
            </div>

            <!-- BOTTOM STATUS BAR (Matching Pandocs author avatar & date) -->
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-[#252836] bg-[#101218] px-5 py-2.5 text-[11px] text-zinc-400">
                <div class="flex items-center gap-3">
                    @if ($this->activeFile)
                        <div class="flex items-center gap-2">
                            <flux:icon icon="clock" class="size-3.5 text-zinc-500" />
                            <span>{{ __('Last updated on') }} {{ $this->activeFile->updated_at?->format('M d, Y') }} {{ __('by') }}</span>
                            <div class="flex items-center gap-1.5">
                                <span class="inline-flex size-4 items-center justify-center rounded-full bg-purple-900 text-[9px] font-bold text-purple-200">
                                    {{ substr($this->activeFile->lastModifier?->name ?? 'S', 0, 1) }}
                                </span>
                                <strong class="text-white font-semibold">{{ $this->activeFile->lastModifier?->name ?? 'Sync' }}</strong>
                            </div>
                        </div>
                    @else
                        <span>{{ __('No active file selected') }}</span>
                    @endif
                </div>

                <div class="flex items-center gap-4 font-mono text-[10px]">
                    <span x-text="`${lineCount} {{ __('lines') }}`"></span>
                    <span class="text-zinc-600">•</span>
                    <span x-text="`${wordCount} {{ __('words') }}`"></span>
                    <span class="text-zinc-600">•</span>
                    <span x-text="`${charCount} {{ __('characters') }}`"></span>
                    <span class="text-zinc-600">•</span>
                    <span x-show="!isDirty" class="text-emerald-400 flex items-center gap-1">✓ {{ __('Synced') }}</span>
                    <span x-show="isDirty" class="text-amber-400 flex items-center gap-1">● {{ __('Unsaved') }}</span>
                </div>
            </div>
        </div>
    @endif

    <!-- TAB 2: VAULT GRAPH VIEW (Interactive Obsidian-style Force-Directed Network Graph) -->
    @if ($activeTab === 'graph')
        <div
            x-data="vaultGraph({
                nodes: {{ Js::from($this->graphData['nodes']) }},
                edges: {{ Js::from($this->graphData['edges']) }}
            })"
            class="relative w-full h-[660px] rounded-3xl border border-zinc-800 bg-[#0C0F12] overflow-hidden shadow-2xl"
        >
            <!-- Canvas -->
            <canvas x-ref="graphCanvas" class="w-full h-full cursor-grab active:cursor-grabbing"></canvas>

            <!-- Top Left Search & Stats Controls -->
            <div class="absolute top-4 left-4 flex flex-wrap items-center gap-3 z-20">
                <div class="flex items-center rounded-full border border-zinc-700/80 bg-zinc-900/90 px-3.5 py-1.5 shadow-lg backdrop-blur-md">
                    <flux:icon icon="magnifying-glass" class="size-3.5 text-zinc-400 mr-2" />
                    <input
                        x-model="search"
                        type="text"
                        placeholder="{{ __('Filter graph nodes...') }}"
                        class="bg-transparent text-xs text-white placeholder:text-zinc-500 focus:outline-none w-44"
                    />
                    <button x-show="search" @click="search = ''" class="text-zinc-400 hover:text-white text-xs ml-1">×</button>
                </div>

                <div class="rounded-full border border-zinc-700/80 bg-zinc-900/90 px-3.5 py-1.5 text-xs text-zinc-300 shadow-lg backdrop-blur-md flex items-center gap-2">
                    <span class="size-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span x-text="`${nodes.length} {{ __('Notes') }} • ${edges.length} {{ __('Wiki Connections') }}`"></span>
                </div>
            </div>

            <!-- Bottom Right Navigation & Zoom Controls -->
            <div class="absolute bottom-4 right-4 flex items-center gap-1.5 rounded-full border border-zinc-700/80 bg-zinc-900/90 p-1.5 shadow-lg backdrop-blur-md z-20">
                <button @click="zoomIn()" class="p-2 rounded-full text-zinc-300 hover:bg-zinc-800 hover:text-white transition-colors" title="{{ __('Zoom In') }}">
                    <flux:icon icon="plus" class="size-4" />
                </button>
                <button @click="zoomOut()" class="p-2 rounded-full text-zinc-300 hover:bg-zinc-800 hover:text-white transition-colors" title="{{ __('Zoom Out') }}">
                    <flux:icon icon="minus" class="size-4" />
                </button>
                <button @click="resetView()" class="p-2 rounded-full text-zinc-300 hover:bg-zinc-800 hover:text-white transition-colors" title="{{ __('Reset View') }}">
                    <flux:icon icon="arrow-path" class="size-4" />
                </button>
            </div>

            <!-- Floating Hover Tooltip -->
            <div
                x-show="hoveredNode"
                x-cloak
                :style="`left: ${tooltipX + 15}px; top: ${tooltipY + 15}px;`"
                class="pointer-events-none absolute z-30 max-w-xs rounded-2xl border border-zinc-700/80 bg-zinc-900/95 p-3.5 text-xs text-white shadow-2xl backdrop-blur-md"
            >
                <div class="font-extrabold text-sm text-emerald-400" x-text="hoveredNode?.name"></div>
                <div class="font-mono text-[10px] text-zinc-400 mt-0.5 truncate" x-text="hoveredNode?.path"></div>
                <div class="mt-2.5 flex items-center gap-3 text-[11px] text-zinc-300">
                    <span>{{ __('Connections') }}: <strong class="text-white font-bold" x-text="hoveredNode?.linksCount"></strong></span>
                    <span>{{ __('Revision') }}: <strong class="text-white font-bold" x-text="`v${hoveredNode?.version}`"></strong></span>
                </div>
                <div class="mt-1.5 text-[10px] text-emerald-400/90 font-medium">
                    {{ __('Click node to open in Markdown Editor ↗') }}
                </div>
            </div>
        </div>

        <script>
        function vaultGraph(data) {
            return {
                nodes: data.nodes || [],
                edges: data.edges || [],
                search: '',
                hoveredNode: null,
                tooltipX: 0,
                tooltipY: 0,
                zoom: 1,
                panX: 0,
                panY: 0,
                isDragging: false,
                dragNode: null,
                lastMouseX: 0,
                lastMouseY: 0,
                animId: null,
                init() {
                    const canvas = this.$refs.graphCanvas;
                    const ctx = canvas.getContext('2d');

                    const resize = () => {
                        const rect = canvas.getBoundingClientRect();
                        canvas.width = rect.width;
                        canvas.height = rect.height;
                        if (this.panX === 0 && this.panY === 0) {
                            this.panX = canvas.width / 2;
                            this.panY = canvas.height / 2;
                        }
                    };
                    resize();
                    window.addEventListener('resize', resize);

                    const radius = Math.min(canvas.width, canvas.height) * 0.35;
                    this.nodes.forEach((node, i) => {
                        const angle = (i / Math.max(1, this.nodes.length)) * Math.PI * 2;
                        node.x = Math.cos(angle) * radius + (Math.random() - 0.5) * 40;
                        node.y = Math.sin(angle) * radius + (Math.random() - 0.5) * 40;
                        node.vx = 0;
                        node.vy = 0;
                        node.radius = 4 + Math.min(node.linksCount * 2, 10);
                    });

                    canvas.addEventListener('mousemove', (e) => {
                        const rect = canvas.getBoundingClientRect();
                        const mx = e.clientX - rect.left;
                        const my = e.clientY - rect.top;

                        if (this.isDragging) {
                            const dx = mx - this.lastMouseX;
                            const dy = my - this.lastMouseY;
                            if (this.dragNode) {
                                this.dragNode.x += dx / this.zoom;
                                this.dragNode.y += dy / this.zoom;
                            } else {
                                this.panX += dx;
                                this.panY += dy;
                            }
                            this.lastMouseX = mx;
                            this.lastMouseY = my;
                            return;
                        }

                        const worldX = (mx - this.panX) / this.zoom;
                        const worldY = (my - this.panY) / this.zoom;
                        let found = null;

                        for (let node of this.nodes) {
                            const dist = Math.hypot(node.x - worldX, node.y - worldY);
                            if (dist < node.radius + 6) {
                                found = node;
                                break;
                            }
                        }

                        this.hoveredNode = found;
                        this.tooltipX = mx;
                        this.tooltipY = my;
                        this.lastMouseX = mx;
                        this.lastMouseY = my;
                    });

                    canvas.addEventListener('mousedown', (e) => {
                        const rect = canvas.getBoundingClientRect();
                        const mx = e.clientX - rect.left;
                        const my = e.clientY - rect.top;
                        const worldX = (mx - this.panX) / this.zoom;
                        const worldY = (my - this.panY) / this.zoom;

                        this.isDragging = true;
                        this.lastMouseX = mx;
                        this.lastMouseY = my;

                        for (let node of this.nodes) {
                            const dist = Math.hypot(node.x - worldX, node.y - worldY);
                            if (dist < node.radius + 6) {
                                this.dragNode = node;
                                return;
                            }
                        }
                        this.dragNode = null;
                    });

                    canvas.addEventListener('mouseup', () => {
                        this.isDragging = false;
                        this.dragNode = null;
                    });

                    canvas.addEventListener('click', (e) => {
                        const rect = canvas.getBoundingClientRect();
                        const mx = e.clientX - rect.left;
                        const my = e.clientY - rect.top;
                        const worldX = (mx - this.panX) / this.zoom;
                        const worldY = (my - this.panY) / this.zoom;

                        for (let node of this.nodes) {
                            const dist = Math.hypot(node.x - worldX, node.y - worldY);
                            if (dist < node.radius + 6) {
                                this.$wire.selectFile(node.id);
                                this.$wire.activeTab = 'editor';
                                return;
                            }
                        }
                    });

                    canvas.addEventListener('wheel', (e) => {
                        e.preventDefault();
                        const zoomFactor = e.deltaY < 0 ? 1.1 : 0.9;
                        this.zoom = Math.max(0.3, Math.min(3.0, this.zoom * zoomFactor));
                    }, { passive: false });

                    const tick = () => {
                        const kRepulse = 1200;
                        for (let i = 0; i < this.nodes.length; i++) {
                            for (let j = i + 1; j < this.nodes.length; j++) {
                                const n1 = this.nodes[i];
                                const n2 = this.nodes[j];
                                const dx = n2.x - n1.x;
                                const dy = n2.y - n1.y;
                                const dist = Math.hypot(dx, dy) || 1;
                                if (dist < 300) {
                                    const force = kRepulse / (dist * dist);
                                    const fx = (dx / dist) * force;
                                    const fy = (dy / dist) * force;
                                    n1.vx -= fx;
                                    n1.vy -= fy;
                                    n2.vx += fx;
                                    n2.vy += fy;
                                }
                            }
                        }

                        const kSpring = 0.04;
                        const restLength = 80;
                        for (let edge of this.edges) {
                            const n1 = this.nodes[edge.source];
                            const n2 = this.nodes[edge.target];
                            if (!n1 || !n2) continue;
                            const dx = n2.x - n1.x;
                            const dy = n2.y - n1.y;
                            const dist = Math.hypot(dx, dy) || 1;
                            const force = (dist - restLength) * kSpring;
                            const fx = (dx / dist) * force;
                            const fy = (dy / dist) * force;
                            n1.vx -= fx;
                            n1.vy -= fy;
                            n2.vx += fx;
                            n2.vy += fy;
                        }

                        this.nodes.forEach(node => {
                            if (node === this.dragNode) return;
                            node.vx -= node.x * 0.005;
                            node.vy -= node.y * 0.005;
                            node.vx *= 0.88;
                            node.vy *= 0.88;
                            node.x += node.vx;
                            node.y += node.vy;
                        });

                        ctx.clearRect(0, 0, canvas.width, canvas.height);
                        ctx.save();
                        ctx.translate(this.panX, this.panY);
                        ctx.scale(this.zoom, this.zoom);

                        for (let edge of this.edges) {
                            const n1 = this.nodes[edge.source];
                            const n2 = this.nodes[edge.target];
                            if (!n1 || !n2) continue;

                            const isConnectedToHover = this.hoveredNode && (this.hoveredNode === n1 || this.hoveredNode === n2);

                            ctx.beginPath();
                            ctx.moveTo(n1.x, n1.y);
                            ctx.lineTo(n2.x, n2.y);
                            if (isConnectedToHover) {
                                ctx.strokeStyle = 'rgba(16, 185, 129, 0.7)';
                                ctx.lineWidth = 2;
                            } else {
                                ctx.strokeStyle = 'rgba(255, 255, 255, 0.08)';
                                ctx.lineWidth = 1;
                            }
                            ctx.stroke();
                        }

                        for (let node of this.nodes) {
                            const isMatch = !this.search || node.name.toLowerCase().includes(this.search.toLowerCase());
                            const isHovered = (this.hoveredNode === node);

                            ctx.beginPath();
                            ctx.arc(node.x, node.y, node.radius * (isHovered ? 1.3 : 1), 0, Math.PI * 2);

                            if (isHovered) {
                                ctx.fillStyle = '#10B981';
                                ctx.shadowColor = '#10B981';
                                ctx.shadowBlur = 12;
                            } else if (isMatch) {
                                ctx.fillStyle = node.linksCount > 2 ? '#059669' : '#4B5563';
                                ctx.shadowBlur = 0;
                            } else {
                                ctx.fillStyle = 'rgba(75, 85, 99, 0.2)';
                                ctx.shadowBlur = 0;
                            }
                            ctx.fill();

                            if (isMatch || isHovered) {
                                ctx.font = isHovered ? 'bold 11px sans-serif' : '10px sans-serif';
                                ctx.fillStyle = isHovered ? '#FFFFFF' : 'rgba(209, 213, 219, 0.75)';
                                ctx.textAlign = 'center';
                                ctx.fillText(node.name, node.x, node.y + node.radius + 12);
                            }
                        }

                        ctx.restore();
                        this.animId = requestAnimationFrame(tick);
                    };

                    this.animId = requestAnimationFrame(tick);
                },
                zoomIn() {
                    this.zoom = Math.min(3.0, this.zoom * 1.2);
                },
                zoomOut() {
                    this.zoom = Math.max(0.3, this.zoom * 0.8);
                },
                resetView() {
                    const canvas = this.$refs.graphCanvas;
                    this.zoom = 1;
                    this.panX = canvas.width / 2;
                    this.panY = canvas.height / 2;
                    this.search = '';
                }
            };
        }
        </script>
    @endif

    <!-- TAB 3: Permissions Matrix (Granular Folder/File rules) -->
    @if ($activeTab === 'permissions')
        <div class="space-y-4">
            <!-- How it works Callout -->
            <flux:card variant="soft" class="border-blue-200/50 bg-blue-50/20 dark:border-blue-900/30 dark:bg-blue-950/20">
                <div class="flex items-start gap-3">
                    <flux:icon icon="information-circle" class="size-5 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" />
                    <div class="text-xs leading-relaxed text-zinc-600 dark:text-zinc-300">
                        <strong class="font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Hierarchical Folder & File Access Rules:') }}</strong>
                        {{ __('Rules cascade downwards into subfolders and notes unless a more specific child rule exists. Specific member rules override whole-team defaults. When a path is set to "Hidden", it is omitted from that member\'s sync manifest entirely and will never touch their device.') }}
                    </div>
                </div>
            </flux:card>

            <!-- Table of Rules -->
            <flux:card class="p-0 overflow-hidden">
                @if ($this->permissionRules->isEmpty())
                    <div class="p-12 text-center">
                        <div class="flex size-12 mx-auto items-center justify-center rounded-full bg-zinc-100 text-zinc-500 dark:bg-white/10 dark:text-zinc-400">
                            <flux:icon icon="shield-check" class="size-6" />
                        </div>
                        <flux:heading size="md" class="mt-4">{{ __('No Custom Path Rules Configured') }}</flux:heading>
                        <flux:subheading class="max-w-md mx-auto text-xs mt-1">{{ __('All notes in this vault follow the default permission (:permission). Add granular rules to lock down sensitive folders or grant write access.', ['permission' => $vault->default_permission]) }}</flux:subheading>
                        <flux:modal.trigger name="add-path-permission">
                            <flux:button variant="primary" size="sm" icon="plus" class="mt-4">
                                {{ __('Add Path Rule') }}
                            </flux:button>
                        </flux:modal.trigger>
                    </div>
                @else
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column class="py-3.5 px-4 text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ __('Path / Folder') }}</flux:table.column>
                            <flux:table.column class="py-3.5 px-4 text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ __('Target Scope') }}</flux:table.column>
                            <flux:table.column class="py-3.5 px-4 text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ __('Access Level') }}</flux:table.column>
                            <flux:table.column align="end" class="py-3.5 px-4 text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ __('Actions') }}</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($this->permissionRules as $rule)
                                <flux:table.row :key="$rule->id" class="hover:bg-zinc-50/50 dark:hover:bg-white/5 transition-colors">
                                    <flux:table.cell class="py-3.5 px-4 font-mono text-xs font-semibold">
                                        <div class="flex items-center gap-2.5">
                                            @if ($rule->is_folder)
                                                <flux:icon icon="folder" class="size-4 text-amber-500 shrink-0" />
                                            @else
                                                <flux:icon icon="document-text" class="size-4 text-blue-500 shrink-0" />
                                            @endif
                                            <span class="text-zinc-900 dark:text-zinc-100">{{ $rule->path }}</span>
                                            @if ($rule->is_folder)
                                                <span class="text-[10px] text-zinc-400 font-sans font-normal">({{ __('recursive') }})</span>
                                            @endif
                                        </div>
                                    </flux:table.cell>

                                    <flux:table.cell class="py-3.5 px-4">
                                        @if ($rule->user)
                                            <div class="flex items-center gap-2">
                                                <flux:avatar :name="$rule->user->name" size="xs" />
                                                <span class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">{{ $rule->user->name }}</span>
                                            </div>
                                        @else
                                            <flux:badge color="zinc" size="sm" class="font-medium">{{ __('All Team Members') }}</flux:badge>
                                        @endif
                                    </flux:table.cell>

                                    <flux:table.cell class="py-3.5 px-4">
                                        @if ($rule->permission === 'read_write')
                                            <flux:badge color="emerald" size="sm" icon="pencil-square" class="font-medium">{{ __('Read & Write (Full Sync)') }}</flux:badge>
                                        @elseif ($rule->permission === 'read_only')
                                            <flux:badge color="amber" size="sm" icon="eye" class="font-medium">{{ __('Read-Only (Download Only)') }}</flux:badge>
                                        @else
                                            <flux:badge color="red" size="sm" icon="eye-slash" class="font-medium">{{ __('Hidden (No Access / Omitted)') }}</flux:badge>
                                        @endif
                                    </flux:table.cell>

                                    <flux:table.cell align="end" class="py-3.5 px-4">
                                        <flux:button
                                            variant="subtle"
                                            size="sm"
                                            icon="trash"
                                            wire:click="deletePermission({{ $rule->id }})"
                                            wire:confirm="Remove this path permission rule?"
                                            class="text-red-500 hover:text-red-600"
                                        />
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </flux:card>
        </div>
    @endif

    <!-- TAB 2: Files Explorer -->
    @if ($activeTab === 'files')
        <div class="space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="w-full max-w-sm">
                    <flux:input wire:model.live.debounce.250ms="fileSearch" size="sm" icon="magnifying-glass" placeholder="Filter notes and files..." />
                </div>
                <flux:text class="text-xs text-zinc-400">
                    {{ __('Showing up to 100 recent vault notes') }}
                </flux:text>
            </div>

            <flux:card class="p-0 overflow-hidden">
                @if ($this->files->isEmpty())
                    <div class="p-12 text-center text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('No files uploaded to this vault yet. Connect Obsidian to start pushing notes.') }}
                    </div>
                @else
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Note / Path') }}</flux:table.column>
                            <flux:table.column>{{ __('Size') }}</flux:table.column>
                            <flux:table.column>{{ __('Revision') }}</flux:table.column>
                            <flux:table.column>{{ __('Modified By') }}</flux:table.column>
                            <flux:table.column>{{ __('Last Synced') }}</flux:table.column>
                            <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($this->files as $file)
                                <flux:table.row :key="$file->id">
                                    <flux:table.cell class="font-mono text-xs font-medium">
                                        <div class="flex items-center gap-2">
                                            @if ($file->isMarkdown())
                                                <flux:icon icon="document-text" class="size-4 text-blue-500 shrink-0" />
                                            @else
                                                <flux:icon icon="paper-clip" class="size-4 text-zinc-400 shrink-0" />
                                            @endif
                                            <span class="truncate max-w-sm">{{ $file->path }}</span>
                                        </div>
                                    </flux:table.cell>

                                    <flux:table.cell class="text-xs text-zinc-500">
                                        {{ Number::fileSize($file->size, precision: 1) }}
                                    </flux:table.cell>

                                    <flux:table.cell>
                                        <flux:badge color="zinc" size="sm">v{{ $file->version }}</flux:badge>
                                    </flux:table.cell>

                                    <flux:table.cell>
                                        <span class="text-xs text-zinc-700 dark:text-zinc-300">{{ $file->lastModifier?->name ?? __('Sync') }}</span>
                                    </flux:table.cell>

                                    <flux:table.cell class="text-xs text-zinc-400">
                                        {{ $file->updated_at->diffForHumans() }}
                                    </flux:table.cell>

                                    <flux:table.cell align="end">
                                        <flux:button
                                            variant="subtle"
                                            size="xs"
                                            icon="clock"
                                            wire:click="showFileHistory({{ $file->id }})"
                                        >
                                            {{ __('History') }}
                                        </flux:button>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </flux:card>
        </div>
    @endif

    <!-- TAB 3: Activity Logs -->
    @if ($activeTab === 'activity')
        <flux:card class="p-0 overflow-hidden">
            @if ($this->activities->isEmpty())
                <div class="p-12 text-center text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('No activity recorded for this vault yet.') }}
                </div>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Action') }}</flux:table.column>
                        <flux:table.column>{{ __('File Path') }}</flux:table.column>
                        <flux:table.column>{{ __('Member & Device') }}</flux:table.column>
                        <flux:table.column>{{ __('Revision') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Timestamp') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->activities as $act)
                            <flux:table.row :key="$act->id">
                                <flux:table.cell>
                                    @if ($act->action === 'created')
                                        <flux:badge color="emerald" size="sm">{{ __('Created') }}</flux:badge>
                                    @elseif ($act->action === 'updated')
                                        <flux:badge color="blue" size="sm">{{ __('Updated') }}</flux:badge>
                                    @elseif ($act->action === 'deleted')
                                        <flux:badge color="red" size="sm">{{ __('Deleted') }}</flux:badge>
                                    @elseif ($act->action === 'conflict')
                                        <flux:badge color="amber" size="sm">{{ __('Conflict Branched') }}</flux:badge>
                                    @endif
                                </flux:table.cell>

                                <flux:table.cell class="font-mono text-xs">
                                    {{ $act->path }}
                                </flux:table.cell>

                                <flux:table.cell>
                                    <div class="flex items-center gap-2">
                                        <flux:avatar :name="$act->user?->name ?? 'Device'" size="xs" />
                                        <span class="text-xs font-medium">{{ $act->user?->name ?? __('System') }}</span>
                                        @if ($act->device_name)
                                            <span class="text-xs text-zinc-400">({{ $act->device_name }})</span>
                                        @endif
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell class="text-xs font-mono text-zinc-400">
                                    v{{ $act->version }}
                                </flux:table.cell>

                                <flux:table.cell align="end" class="text-xs text-zinc-400">
                                    {{ $act->created_at->diffForHumans() }}
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>
    @endif

    <!-- TAB 4: Settings -->
    @if ($activeTab === 'settings')
        <div class="max-w-2xl space-y-6">
            <flux:card class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ __('Vault Configuration') }}</flux:heading>
                    <flux:subheading>{{ __('Update name, description, or baseline team permissions.') }}</flux:subheading>
                </div>

                <form wire:submit="updateVaultSettings" class="space-y-4">
                    <flux:input wire:model="editName" :label="__('Vault Name')" required />

                    <flux:textarea wire:model="editDescription" :label="__('Description')" rows="3" />

                    <flux:select wire:model="editDefaultPermission" :label="__('Default Team Permission')">
                        <flux:select.option value="read_write">{{ __('Read & Write (Full Two-Way Sync)') }}</flux:select.option>
                        <flux:select.option value="read_only">{{ __('Read-Only (Download Only)') }}</flux:select.option>
                        <flux:select.option value="hidden">{{ __('Restricted (Hidden unless specific rule granted)') }}</flux:select.option>
                    </flux:select>

                    <div class="pt-2">
                        <flux:button variant="primary" size="sm" type="submit">{{ __('Save Changes') }}</flux:button>
                    </div>
                </form>
            </flux:card>

            <!-- Danger Zone -->
            <flux:card variant="soft" class="border-red-200/50 bg-red-50/20 dark:border-red-900/30 dark:bg-red-950/10 space-y-3">
                <flux:heading size="md" class="text-red-600 dark:text-red-400">{{ __('Danger Zone') }}</flux:heading>
                <flux:subheading class="text-xs">{{ __('Permanently remove this vault and all server-side sync logs.') }}</flux:subheading>

                <flux:button
                    variant="danger"
                    size="sm"
                    wire:click="deleteVault"
                    wire:confirm="Permanently delete this vault? All synced notes on the server will be removed."
                >
                    {{ __('Delete This Vault') }}
                </flux:button>
            </flux:card>
        </div>
    @endif

    <!-- Add Path Permission Modal -->
    <flux:modal name="add-path-permission" focusable class="max-w-md">
        <form wire:submit="addPathPermission" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Add Folder or File Rule') }}</flux:heading>
                <flux:subheading class="text-xs">{{ __('Configure path-level permissions for your team members.') }}</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:select wire:model="ruleUserId" :label="__('Target Member')">
                    <flux:select.option value="">{{ __('🌐 All Team Members (Default Scope)') }}</flux:select.option>
                    @foreach ($this->teamMembers as $member)
                        <flux:select.option value="{{ $member->id }}">{{ $member->name }} ({{ $member->email }})</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input
                    wire:model="rulePath"
                    :label="__('Vault Path')"
                    placeholder="e.g. 01 - Projects or 02 - Finance/Salaries.md"
                    required
                />

                <flux:checkbox wire:model="ruleIsFolder" :label="__('Apply recursively to all notes in this directory')" />

                <flux:select wire:model="rulePermission" :label="__('Access Right')">
                    <flux:select.option value="read_write">{{ __('✅ Read & Write (Full Sync)') }}</flux:select.option>
                    <flux:select.option value="read_only">{{ __('👁️ Read-Only (Download Only)') }}</flux:select.option>
                    <flux:select.option value="hidden">{{ __('🔒 Hidden (Omitted from Sync Completely)') }}</flux:select.option>
                </flux:select>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Save Permission') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Note Revision History Modal -->
    <flux:modal name="file-history" focusable class="max-w-xl">
        @if ($selectedFile)
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg" class="flex items-center gap-2">
                        <flux:icon icon="clock" class="size-5 text-blue-500" />
                        <span>{{ __('Note Revision History') }}</span>
                    </flux:heading>
                    <flux:subheading class="font-mono text-xs text-zinc-500 truncate mt-1">
                        {{ $selectedFile->path }}
                    </flux:subheading>
                </div>

                <div class="rounded-lg border border-zinc-200 p-3 bg-zinc-50 dark:border-white/10 dark:bg-white/5 space-y-1">
                    <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">
                        {{ __('Current Active Version:') }} <flux:badge color="emerald" size="sm">v{{ $selectedFile->version }}</flux:badge>
                    </div>
                    <div class="text-xs text-zinc-500">
                        {{ __('Last modified:') }} {{ $selectedFile->updated_at?->diffForHumans() }} {{ __('by') }} {{ $selectedFile->lastModifier?->name ?? __('Sync') }}
                    </div>
                </div>

                <div>
                    <flux:heading size="sm" class="mb-2">{{ __('Historical Version Snapshots') }}</flux:heading>

                    @if ($selectedFile->versions->isEmpty())
                        <div class="py-8 text-center text-xs text-zinc-500 dark:text-zinc-400 border rounded-lg border-dashed">
                            {{ __('No previous historical versions stored for this note yet. Modifying this file will generate automatic snapshot revisions.') }}
                        </div>
                    @else
                        <div class="space-y-2 max-h-72 overflow-y-auto pr-1">
                            @foreach ($selectedFile->versions as $version)
                                <div class="flex items-center justify-between p-3 rounded-lg border border-zinc-200 dark:border-white/10 bg-white dark:bg-zinc-900">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <flux:badge color="zinc" size="sm">v{{ $version->version }}</flux:badge>
                                            <span class="text-xs font-mono text-zinc-600 dark:text-zinc-400">{{ Number::fileSize($version->size, precision: 1) }}</span>
                                        </div>
                                        <div class="text-[11px] text-zinc-400 mt-0.5">
                                            {{ $version->created_at?->format('M j, Y g:i A') }} ({{ $version->created_at?->diffForHumans() }})
                                        </div>
                                    </div>

                                    <flux:button
                                        variant="subtle"
                                        size="xs"
                                        icon="arrow-path"
                                        wire:click="restoreVersion({{ $version->id }})"
                                        wire:confirm="Restore version v{{ $version->version }} as the active version of this note?"
                                    >
                                        {{ __('Restore') }}
                                    </flux:button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="flex justify-end pt-2">
                    <flux:modal.close>
                        <flux:button variant="filled">{{ __('Close') }}</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>

    <!-- Create New Note Modal -->
    <flux:modal name="new-note-modal" focusable class="max-w-md">
        <form wire:submit="createNewNote" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Create New Note') }}</flux:heading>
                <flux:subheading class="text-xs">{{ __('Create a new markdown note in this vault. Markdown will open in the editor immediately.') }}</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:input
                    wire:model="newNotePath"
                    :label="__('Note Path / Name')"
                    placeholder="e.g. Daily/2026-09-05.md or Ideas.md"
                    description="Subdirectories will be created automatically. Extension (.md) is added if omitted."
                    required
                />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit" class="!bg-[#0D3B29] !text-white hover:!bg-[#0D3B29]/90">{{ __('Create Note') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
