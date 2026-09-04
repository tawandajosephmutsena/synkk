<?php

use App\Actions\Vaults\RestoreFileVersionAction;
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
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Vault Details')] class extends Component {
    public Vault $vault;
    public string $activeTab = 'permissions';

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

    // Version History Modal State
    public ?int $selectedFileId = null;
    public ?VaultFile $selectedFile = null;

    public function mount(Vault $vault): void
    {
        $this->vault = $vault;
        $this->editName = $vault->name;
        $this->editDescription = $vault->description ?? '';
        $this->editDefaultPermission = $vault->default_permission;
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

        // Check if rule already exists for this path & user
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
        $query = $this->vault->files()
            ->where('is_deleted', false)
            ->with('lastModifier')
            ->latest('updated_at');

        if (! empty($this->fileSearch)) {
            $query->where('path', 'like', '%' . $this->fileSearch . '%');
        }

        return $query->limit(100)->get();
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
            @if ($activeTab === 'permissions')
                <flux:modal.trigger name="add-path-permission">
                    <flux:button variant="primary" size="sm" icon="plus">
                        {{ __('Add Path Rule') }}
                    </flux:button>
                </flux:modal.trigger>
            @endif

            <flux:dropdown>
                <flux:button variant="subtle" size="sm" icon="ellipsis-horizontal" aria-label="Vault actions" />
                <flux:menu>
                    <flux:menu.item icon="arrow-path" wire:click="$refresh">{{ __('Refresh Data') }}</flux:menu.item>
                    <flux:menu.item icon="cog" wire:click="$set('activeTab', 'settings')">{{ __('Vault Settings') }}</flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </div>
    </div>

    <!-- Mini Stat Summary Row -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <flux:card variant="soft" class="py-3 px-4">
            <div class="flex items-center justify-between">
                <flux:text class="text-xs text-zinc-400">{{ __('Total Synced Files') }}</flux:text>
                <flux:icon icon="document-text" class="size-4 text-zinc-400" />
            </div>
            <div class="mt-1 flex items-baseline gap-2">
                <span class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">{{ number_format($vault->files()->where('is_deleted', false)->count()) }}</span>
                <span class="text-xs text-zinc-400">{{ __('notes & assets') }}</span>
            </div>
        </flux:card>

        <flux:card variant="soft" class="py-3 px-4">
            <div class="flex items-center justify-between">
                <flux:text class="text-xs text-zinc-400">{{ __('Storage Footprint') }}</flux:text>
                <flux:icon icon="server-stack" class="size-4 text-zinc-400" />
            </div>
            <div class="mt-1 flex items-baseline gap-2">
                <span class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $this->totalSizeFormatted }}</span>
                <span class="text-xs text-zinc-400">{{ __('cPanel storage') }}</span>
            </div>
        </flux:card>

        <flux:card variant="soft" class="py-3 px-4">
            <div class="flex items-center justify-between">
                <flux:text class="text-xs text-zinc-400">{{ __('Current Revision') }}</flux:text>
                <flux:icon icon="clock" class="size-4 text-zinc-400" />
            </div>
            <div class="mt-1 flex items-baseline gap-2">
                <span class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">v{{ $vault->latestVersion() }}</span>
                <span class="text-xs text-emerald-600 dark:text-emerald-400">{{ __('Latest sync state') }}</span>
            </div>
        </flux:card>
    </div>

    <!-- High-End Segmented Navigation -->
    <div class="flex items-center gap-2 border-b border-zinc-200 pb-3 dark:border-white/10">
        <flux:radio.group wire:model.live="activeTab" variant="segmented" size="sm">
            <flux:radio value="permissions">
                <span class="flex items-center gap-1.5">
                    <flux:icon icon="shield-check" class="size-4" />
                    <span>{{ __('Permissions Matrix') }}</span>
                    <flux:badge size="sm" color="zinc" rounded inset>{{ $this->permissionRules->count() }}</flux:badge>
                </span>
            </flux:radio>
            <flux:radio value="files">
                <span class="flex items-center gap-1.5">
                    <flux:icon icon="folder-open" class="size-4" />
                    <span>{{ __('Files & Notes Explorer') }}</span>
                </span>
            </flux:radio>
            <flux:radio value="activity">
                <span class="flex items-center gap-1.5">
                    <flux:icon icon="clock" class="size-4" />
                    <span>{{ __('Sync Audit Trail') }}</span>
                </span>
            </flux:radio>
            <flux:radio value="settings">
                <span class="flex items-center gap-1.5">
                    <flux:icon icon="cog" class="size-4" />
                    <span>{{ __('Settings') }}</span>
                </span>
            </flux:radio>
        </flux:radio.group>
    </div>

    <!-- TAB 1: Permissions Matrix (Granular Folder/File rules) -->
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
</div>
