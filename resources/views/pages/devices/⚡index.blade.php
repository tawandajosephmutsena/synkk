<?php

use App\Models\DeviceToken;
use App\Models\Team;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Devices & Sync Tokens')] class extends Component {
    public string $deviceName = '';
    public string $devicePlatform = 'mac';
    public ?string $generatedPlainToken = null;

    public ?int $editingTokenId = null;
    public string $editName = '';
    public string $editPlatform = 'mac';

    public function generateToken(): void
    {
        $team = Auth::user()->currentTeam;

        $this->validate([
            'deviceName' => ['required', 'string', 'max:255'],
            'devicePlatform' => ['required', 'in:mac,windows,ios,android,linux'],
        ]);

        $result = DeviceToken::createToken(
            Auth::user(),
            $team,
            $this->deviceName,
            $this->devicePlatform,
        );

        $this->generatedPlainToken = $result['plain_token'];
        $this->reset('deviceName');
        $this->devicePlatform = 'mac';
        $this->dispatch('modal-close', name: 'create-device-token');
        $this->dispatch('modal-show', name: 'show-token-modal');

        Flux::toast(variant: 'success', text: __('Sync token generated.'));
    }

    public function clearGeneratedToken(): void
    {
        $this->generatedPlainToken = null;
        $this->dispatch('modal-close', name: 'show-token-modal');
    }

    public function editToken(int $tokenId): void
    {
        $token = DeviceToken::where('id', $tokenId)
            ->where('team_id', Auth::user()->currentTeam?->id)
            ->firstOrFail();

        $this->editingTokenId = $token->id;
        $this->editName = $token->name;
        $this->editPlatform = $token->client_platform ?? 'mac';

        $this->dispatch('modal-show', name: 'edit-device-token');
    }

    public function updateToken(): void
    {
        $this->validate([
            'editName' => ['required', 'string', 'max:255'],
            'editPlatform' => ['required', 'in:mac,windows,ios,android,linux'],
        ]);

        DeviceToken::where('id', $this->editingTokenId)
            ->where('team_id', Auth::user()->currentTeam?->id)
            ->update([
                'name' => $this->editName,
                'client_platform' => $this->editPlatform,
            ]);

        $this->reset('editingTokenId', 'editName', 'editPlatform');
        $this->dispatch('modal-close', name: 'edit-device-token');

        Flux::toast(variant: 'success', text: __('Device updated.'));
    }

    public function revokeToken(int $tokenId): void
    {
        DeviceToken::where('id', $tokenId)
            ->where('team_id', Auth::user()->currentTeam?->id)
            ->delete();

        Flux::toast(variant: 'info', text: __('Device token revoked.'));
    }

    public function triggerRemoteWipe(int $tokenId): void
    {
        $token = DeviceToken::where('id', $tokenId)
            ->where('team_id', Auth::user()->currentTeam?->id)
            ->firstOrFail();

        $token->triggerRemoteWipe();

        Flux::toast(variant: 'warning', text: __('Remote wipe signal issued for device ":name".', ['name' => $token->name]));
    }

    #[Computed]
    public function team(): ?Team
    {
        return Auth::user()->currentTeam;
    }

    #[Computed]
    public function tokens(): Collection
    {
        if (! $this->team) {
            return collect();
        }

        return DeviceToken::where('team_id', $this->team->id)
            ->with('user')
            ->latest('created_at')
            ->get();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Breadcrumbs & Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:breadcrumbs class="mb-1">
                <flux:breadcrumbs.item href="{{ route('dashboard') }}">{{ $this->team?->name ?? __('Team') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>{{ __('Devices & Tokens') }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>
            <flux:heading size="xl" level="1" class="tracking-tight">{{ __('Connected Devices') }}</flux:heading>
        </div>

        <div class="flex items-center gap-2">
            <flux:modal.trigger name="create-device-token">
                <flux:button variant="primary" size="sm" icon="plus">
                    {{ __('Connect New Device') }}
                </flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    <!-- Endpoint Info Card -->
    <flux:card variant="soft" class="py-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="flex size-9 items-center justify-center rounded-lg bg-zinc-200/50 text-zinc-700 dark:bg-white/10 dark:text-zinc-300 shrink-0">
                    <flux:icon icon="link" class="size-4" />
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Obsidian Sync Server URL') }}</h4>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Enter this address in your Obsidian plugin on Mac, Windows, iOS, or Android:') }}</p>
                </div>
            </div>
            <div
                class="flex flex-wrap items-center justify-end gap-2"
                x-data="{ copyState: 'idle', async copyUrl() { this.copyState = 'copying'; const result = await window.SynkkClipboard.copy(@js(url('/api/v1'))); this.copyState = result.copied ? 'copied' : 'manual'; if (! result.copied) { this.$nextTick(() => { this.$refs.endpoint.focus(); this.$refs.endpoint.select(); }); } } }"
            >
                <input
                    x-ref="endpoint"
                    type="text"
                    readonly
                    value="{{ url('/api/v1') }}"
                    x-on:click="$el.select()"
                    class="w-full rounded-md border border-zinc-200 bg-white px-3 py-1.5 font-mono text-xs font-semibold text-indigo-600 sm:w-auto dark:border-white/10 dark:bg-zinc-800 dark:text-indigo-400"
                    aria-label="{{ __('Obsidian Sync Server URL') }}"
                />
                <flux:button
                    type="button"
                    size="sm"
                    icon="clipboard"
                    x-on:click="copyUrl()"
                    x-bind:disabled="copyState === 'copying'"
                >
                    <span x-text="copyState === 'copying' ? '{{ __('Copying…') }}' : (copyState === 'copied' ? '{{ __('Copied!') }}' : '{{ __('Copy URL') }}')"></span>
                </flux:button>
                <p class="w-full text-right text-xs text-amber-700" x-show="copyState === 'manual'" x-cloak role="status" aria-live="polite">
                    {{ __('Automatic copy was blocked. The URL is selected—press ⌘C or Ctrl+C.') }}
                </p>
            </div>
        </div>
    </flux:card>

    <!-- Tokens Table -->
    <flux:card class="p-0 overflow-hidden">
        @if ($this->tokens->isEmpty())
            <div class="p-12 text-center">
                <div class="flex size-12 mx-auto items-center justify-center rounded-full bg-zinc-100 text-zinc-500 dark:bg-white/10 dark:text-zinc-400">
                    <flux:icon icon="device-phone-mobile" class="size-6" />
                </div>
                <flux:heading size="md" class="mt-4">{{ __('No Devices Connected Yet') }}</flux:heading>
                <flux:subheading class="max-w-md mx-auto text-xs mt-1">{{ __('Connect your Mac, PC, iPhone, or Android device by generating a dedicated personal sync token.') }}</flux:subheading>
                <flux:modal.trigger name="create-device-token">
                    <flux:button variant="primary" size="sm" icon="plus" class="mt-4">
                        {{ __('Generate First Device Token') }}
                    </flux:button>
                </flux:modal.trigger>
            </div>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Device Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Platform') }}</flux:table.column>
                    <flux:table.column>{{ __('Token Preview') }}</flux:table.column>
                    <flux:table.column>{{ __('Owner') }}</flux:table.column>
                    <flux:table.column>{{ __('Last Active') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->tokens as $token)
                        <flux:table.row :key="$token->id">
                            <flux:table.cell class="font-medium text-xs">
                                <div class="flex items-center gap-2.5">
                                    @if ($token->client_platform === 'ios')
                                        <flux:icon icon="device-phone-mobile" class="size-4 text-blue-500" />
                                    @elseif ($token->client_platform === 'android')
                                        <flux:icon icon="device-phone-mobile" class="size-4 text-emerald-500" />
                                    @elseif ($token->client_platform === 'mac')
                                        <flux:icon icon="computer-desktop" class="size-4 text-zinc-700 dark:text-zinc-300" />
                                    @elseif ($token->client_platform === 'windows')
                                        <flux:icon icon="computer-desktop" class="size-4 text-sky-500" />
                                    @else
                                        <flux:icon icon="laptop" class="size-4 text-zinc-400" />
                                    @endif
                                    <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $token->name }}</span>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="flex items-center gap-1.5">
                                    <flux:badge color="zinc" size="sm" class="uppercase font-mono text-[10px]">{{ $token->client_platform ?? 'client' }}</flux:badge>
                                    @if ($token->is_wiped)
                                        <flux:badge color="red" size="sm" icon="no-symbol">{{ __('Wiped') }}</flux:badge>
                                    @endif
                                </div>
                            </flux:table.cell>

                            <flux:table.cell class="font-mono text-xs text-zinc-400">
                                {{ $token->token_preview }}
                            </flux:table.cell>

                            <flux:table.cell class="text-xs">
                                <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $token->user?->name ?? __('Unknown') }}</span>
                            </flux:table.cell>

                            <flux:table.cell class="text-xs text-zinc-500 dark:text-zinc-400">
                                @if ($token->last_used_at)
                                    <div>{{ $token->last_used_at->diffForHumans() }}</div>
                                    @if ($token->last_ip)
                                        <div class="text-[10px] font-mono text-zinc-400">{{ $token->last_ip }}</div>
                                    @endif
                                @else
                                    <span class="text-zinc-400 italic text-xs">{{ __('Never synced') }}</span>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell align="end">
                                <div class="flex items-center justify-end gap-1">
                                    @if (! $token->is_wiped)
                                        <flux:tooltip :content="__('Remote Wipe device')">
                                            <flux:button
                                                variant="ghost"
                                                size="sm"
                                                icon="no-symbol"
                                                wire:click="triggerRemoteWipe({{ $token->id }})"
                                                wire:confirm="Issue Remote Wipe signal for this device? Next time it connects, all local sync state will be purged."
                                                class="text-amber-600 hover:text-amber-700"
                                            />
                                        </flux:tooltip>
                                    @endif
                                    <flux:tooltip :content="__('Edit device')">
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            icon="pencil-square"
                                            wire:click="editToken({{ $token->id }})"
                                        />
                                    </flux:tooltip>
                                    <flux:tooltip :content="__('Revoke token')">
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            icon="trash"
                                            wire:click="revokeToken({{ $token->id }})"
                                            wire:confirm="Revoke this device token? The device will immediately lose sync access."
                                            class="text-red-500 hover:text-red-600"
                                        />
                                    </flux:tooltip>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>

    <!-- Create Device Token Modal -->
    <flux:modal name="create-device-token" focusable class="max-w-md">
        <form wire:submit="generateToken" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Connect Device') }}</flux:heading>
                <flux:subheading class="text-xs">{{ __('Generate a sync token for your Obsidian application.') }}</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:input wire:model="deviceName" :label="__('Device Name')" placeholder="e.g. MacBook Pro, iPhone 15, Home Desktop" required />

                <flux:select wire:model="devicePlatform" :label="__('Operating System')">
                    <flux:select.option value="mac">{{ __('macOS') }}</flux:select.option>
                    <flux:select.option value="windows">{{ __('Windows PC') }}</flux:select.option>
                    <flux:select.option value="ios">{{ __('iPhone / iPad (iOS)') }}</flux:select.option>
                    <flux:select.option value="android">{{ __('Android Phone / Tablet') }}</flux:select.option>
                    <flux:select.option value="linux">{{ __('Linux') }}</flux:select.option>
                </flux:select>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Generate Token') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Edit Device Token Modal -->
    <flux:modal name="edit-device-token" focusable class="max-w-md">
        <form wire:submit="updateToken" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Edit Device') }}</flux:heading>
                <flux:subheading class="text-xs">{{ __('Update the name or platform for this device.') }}</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:input wire:model="editName" :label="__('Device Name')" placeholder="e.g. MacBook Pro, iPhone 15" required />

                <flux:select wire:model="editPlatform" :label="__('Operating System')">
                    <flux:select.option value="mac">{{ __('macOS') }}</flux:select.option>
                    <flux:select.option value="windows">{{ __('Windows PC') }}</flux:select.option>
                    <flux:select.option value="ios">{{ __('iPhone / iPad (iOS)') }}</flux:select.option>
                    <flux:select.option value="android">{{ __('Android Phone / Tablet') }}</flux:select.option>
                    <flux:select.option value="linux">{{ __('Linux') }}</flux:select.option>
                </flux:select>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Save Changes') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Display Token Modal (Shows Plaintext Once) -->
    <flux:modal name="show-token-modal" focusable class="max-w-lg" :closable="false" :dismissible="false" :escapable="false">
        <div
            class="space-y-5"
            x-data="{
                copyState: 'idle',
                confirmed: false,
                resetCopyState() {
                    this.copyState = 'idle';
                    this.confirmed = false;
                    this.$nextTick(() => {
                        this.$refs.tokenInput?.focus();
                        this.$refs.tokenInput?.select();
                    });
                },
                async copyToken() {
                    this.copyState = 'copying';
                    const result = await window.SynkkClipboard.copy(this.$refs.tokenInput.value);

                    if (result.copied) {
                        this.copyState = 'copied';
                        this.confirmed = true;
                        return;
                    }

                    this.copyState = 'manual';
                    this.$nextTick(() => {
                        this.$refs.tokenInput.focus();
                        this.$refs.tokenInput.select();
                    });
                },
            }"
            x-on:modal-show.document="if ($event.detail.name === 'show-token-modal') { resetCopyState(); }"
        >
            <!-- Warning Banner -->
            <div class="rounded-lg border border-amber-300 bg-amber-50 p-3 dark:border-amber-500/30 dark:bg-amber-950/40">
                <div class="flex items-start gap-2.5">
                    <flux:icon icon="exclamation-triangle" class="size-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
                    <div>
                        <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">{{ __('Copy this token now — you won\'t see it again') }}</p>
                        <p class="text-xs text-amber-700 dark:text-amber-300 mt-0.5">{{ __('For security, the full token is only displayed once. Store it somewhere safe before closing this dialog.') }}</p>
                    </div>
                </div>
            </div>

            <!-- Token Display -->
            <div class="space-y-2">
                <label class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">{{ __('Device Sync Token') }}</label>
                <div class="flex items-center gap-2">
                    <input
                        type="text"
                        readonly
                        value="{{ $generatedPlainToken }}"
                        x-ref="tokenInput"
                        x-on:click="$el.focus(); $el.select()"
                        class="w-full rounded-md border border-zinc-300 bg-zinc-50 p-2.5 font-mono text-xs font-bold text-zinc-900 dark:border-white/10 dark:bg-zinc-800 dark:text-zinc-100 select-all"
                        aria-describedby="token-copy-status token-copy-help"
                    />
                    <flux:button
                        type="button"
                        variant="primary"
                        size="sm"
                        x-on:click="copyToken()"
                        x-bind:disabled="copyState === 'copying'"
                        class="shrink-0"
                    >
                        <span class="flex items-center gap-1.5">
                            <template x-if="copyState === 'copied'">
                                <span>
                                    <flux:icon icon="check" class="size-4" />
                                    {{ __('Copied!') }}
                                </span>
                            </template>
                            <template x-if="copyState !== 'copied'">
                                <span>
                                    <flux:icon icon="clipboard" class="size-4" />
                                    <span x-text="copyState === 'copying' ? '{{ __('Copying…') }}' : '{{ __('Copy Token') }}'"></span>
                                </span>
                            </template>
                        </span>
                    </flux:button>
                </div>
                <div id="token-copy-status" role="status" aria-live="polite">
                    <p class="text-xs font-semibold text-emerald-700" x-show="copyState === 'copied'" x-cloak>
                        {{ __('Token copied successfully.') }}
                    </p>
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-3" x-show="copyState === 'manual'" x-cloak>
                        <p class="text-xs font-semibold text-amber-900">
                            {{ __('Automatic copy is blocked in this browser.') }}
                        </p>
                        <p id="token-copy-help" class="mt-1 text-xs text-amber-800">
                            {{ __('If automatic copy is blocked, select the token and press ⌘C on Mac or Ctrl+C on Windows and Linux.') }}
                        </p>
                        <button type="button" class="mt-2 text-xs font-semibold text-amber-950 underline underline-offset-4" x-on:click="confirmed = true">
                            {{ __('I copied it manually') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Next Steps -->
            <div class="rounded-lg bg-zinc-50 p-3 text-xs text-zinc-600 dark:bg-white/5 dark:text-zinc-300 space-y-1">
                <p class="font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Next Steps in Obsidian:') }}</p>
                <ol class="list-decimal list-inside space-y-0.5 text-zinc-500 dark:text-zinc-400">
                    <li>{{ __('Open Settings -> Synkk Vault Sync') }}</li>
                    <li>{{ __('Paste the Server API URL shown on this page') }}</li>
                    <li>{{ __('Paste this Device Sync Token') }}</li>
                    <li>{{ __('Click "Verify & Load Vaults", then choose your Target Vault') }}</li>
                </ol>
            </div>

            <!-- Close Button (only enabled after copy) -->
            <div class="flex items-center justify-between">
                <p class="text-xs text-zinc-400" x-show="!confirmed">{{ __('Copy the token above to continue') }}</p>
                <p class="text-xs text-emerald-600 dark:text-emerald-400" x-show="confirmed" x-cloak>
                    <flux:icon icon="check-circle" class="size-3.5 inline -mt-0.5" />
                    {{ __('Token saved and ready to use') }}
                </p>
                <flux:button
                    type="button"
                    variant="primary"
                    size="sm"
                    wire:click="clearGeneratedToken"
                    x-bind:disabled="!confirmed"
                    x-bind:class="!confirmed && 'opacity-50 cursor-not-allowed'"
                >
                    {{ __('I\'ve Saved It — Close') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
