<?php

use App\Models\DeviceToken;
use App\Models\Team;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Devices & Sync Tokens')] class extends Component {
    public string $deviceName = '';
    public string $devicePlatform = 'mac';
    public string $accessScope = 'full_access';
    public string $allowedIpSubnets = '';
    public ?string $generatedPlainToken = null;
    public ?string $generatedQrCodeSvg = null;
    public ?string $pairingSessionId = null;

    public ?int $editingTokenId = null;
    public string $editName = '';
    public string $editPlatform = 'mac';
    public string $editAccessScope = 'full_access';
    public string $editAllowedSubnets = '';

    public function generateToken(): void
    {
        $team = $this->team;

        $planService = app(\App\Services\PlanService::class);
        if (! $planService->canAddDevice($team)) {
            Flux::toast(
                variant: 'danger',
                text: __('Device limit reached (:limit devices). Upgrade to Pro LTD or Synkk Cloud to pair more devices.', [
                    'limit' => $planService->getDeviceLimit($team),
                ]),
            );

            return;
        }

        $this->validate([
            'deviceName' => ['required', 'string', 'max:255'],
            'devicePlatform' => ['required', 'in:mac,windows,ios,android,linux'],
            'accessScope' => ['required', 'in:full_access,read_only'],
            'allowedIpSubnets' => ['nullable', 'string'],
        ]);

        if (filled($this->allowedIpSubnets) && ! $planService->hasFeature($team, 'ip_whitelisting')) {
            $this->addError('allowedIpSubnets', __('IP Whitelisting & Subnet filtering requires a Pro LTD or Cloud license.'));
            Flux::toast(variant: 'danger', text: __('IP Whitelisting requires a Pro LTD or Cloud license.'));

            return;
        }

        if ($this->accessScope === 'read_only' && ! $planService->hasFeature($team, 'read_only_tokens')) {
            $this->addError('accessScope', __('Read-Only Device Tokens require a Pro LTD or Cloud license.'));
            Flux::toast(variant: 'danger', text: __('Read-Only Device Tokens require a Pro LTD or Cloud license.'));

            return;
        }

        $subnets = null;
        if (filled($this->allowedIpSubnets)) {
            $subnets = array_values(array_filter(array_map('trim', explode(',', $this->allowedIpSubnets))));
        }

        $result = DeviceToken::createToken(
            Auth::user(),
            $team,
            $this->deviceName,
            $this->devicePlatform,
            $this->accessScope,
            $subnets,
        );

        $this->generatedPlainToken = $result['plain_token'];

        // Generate scoped one-scan pairing session & QR SVG
        try {
            $pairingService = app(\App\Services\QrPairingService::class);
            $sessionData = $pairingService->createPairingSession(
                user: Auth::user(),
                team: $team,
                vault: $team->vaults()->first(),
                accessScope: $this->accessScope,
            );
            $this->generatedQrCodeSvg = $sessionData['qr_svg'];
            $this->pairingSessionId = $sessionData['session'];
        } catch (\Throwable $e) {
            $this->generatedQrCodeSvg = null;
            $this->pairingSessionId = null;
        }

        $this->reset('deviceName', 'allowedIpSubnets');
        $this->devicePlatform = 'mac';
        $this->accessScope = 'full_access';
        $this->dispatch('modal-close', name: 'create-device-token');
        $this->dispatch('modal-show', name: 'show-token-modal');

        Flux::toast(variant: 'success', text: __('Sync token & instant QR pairing generated.'));
    }

    public function clearGeneratedToken(): void
    {
        $this->generatedPlainToken = null;
        $this->generatedQrCodeSvg = null;
        $this->pairingSessionId = null;
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
        $this->editAccessScope = $token->access_scope ?? 'full_access';
        $this->editAllowedSubnets = ! empty($token->allowed_ip_subnets) ? implode(', ', $token->allowed_ip_subnets) : '';

        $this->dispatch('modal-show', name: 'edit-device-token');
    }

    public function updateToken(): void
    {
        $team = $this->team;
        $planService = app(\App\Services\PlanService::class);

        $this->validate([
            'editName' => ['required', 'string', 'max:255'],
            'editPlatform' => ['required', 'in:mac,windows,ios,android,linux'],
            'editAccessScope' => ['required', 'in:full_access,read_only'],
            'editAllowedSubnets' => ['nullable', 'string'],
        ]);

        if (filled($this->editAllowedSubnets) && ! $planService->hasFeature($team, 'ip_whitelisting')) {
            $this->addError('editAllowedSubnets', __('IP Whitelisting & Subnet filtering requires a Pro LTD or Cloud license.'));
            Flux::toast(variant: 'danger', text: __('IP Whitelisting requires a Pro LTD or Cloud license.'));

            return;
        }

        if ($this->editAccessScope === 'read_only' && ! $planService->hasFeature($team, 'read_only_tokens')) {
            $this->addError('editAccessScope', __('Read-Only Device Tokens require a Pro LTD or Cloud license.'));
            Flux::toast(variant: 'danger', text: __('Read-Only Device Tokens require a Pro LTD or Cloud license.'));

            return;
        }

        $subnets = null;
        if (filled($this->editAllowedSubnets)) {
            $subnets = array_values(array_filter(array_map('trim', explode(',', $this->editAllowedSubnets))));
        }

        DeviceToken::where('id', $this->editingTokenId)
            ->where('team_id', Auth::user()->currentTeam?->id)
            ->update([
                'name' => $this->editName,
                'client_platform' => $this->editPlatform,
                'access_scope' => $this->editAccessScope,
                'allowed_ip_subnets' => $subnets,
            ]);

        $this->reset('editingTokenId', 'editName', 'editPlatform', 'editAccessScope', 'editAllowedSubnets');
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
        $planService = app(\App\Services\PlanService::class);
        if (! $planService->hasFeature($this->team, 'remote_wipe')) {
            Flux::toast(
                variant: 'danger',
                text: __('Instant Remote Wipe is a Pro LTD and Cloud feature. Please upgrade to remotely wipe devices.'),
            );

            return;
        }

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
                    class="w-full rounded-md border border-zinc-200 bg-white px-3 py-1.5 font-mono text-xs font-semibold text-emerald-700 sm:w-auto dark:border-white/10 dark:bg-zinc-800 dark:text-emerald-400"
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
            <div class="overflow-x-auto">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column class="py-3.5 px-4 text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ __('Device Name') }}</flux:table.column>
                        <flux:table.column class="py-3.5 px-4 text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ __('Platform & State') }}</flux:table.column>
                        <flux:table.column class="py-3.5 px-4 text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ __('Token Preview') }}</flux:table.column>
                        <flux:table.column class="py-3.5 px-4 text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ __('Owner') }}</flux:table.column>
                        <flux:table.column class="py-3.5 px-4 text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ __('Last Active') }}</flux:table.column>
                        <flux:table.column align="end" class="py-3.5 px-4 text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->tokens as $token)
                            <flux:table.row :key="$token->id" class="hover:bg-zinc-50/50 dark:hover:bg-white/5 transition-colors">
                                <flux:table.cell class="py-3.5 px-4 font-medium text-xs">
                                    <div class="flex items-center gap-3">
                                        <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-700 dark:bg-white/10 dark:text-zinc-300">
                                            @if ($token->client_platform === 'ios')
                                                <flux:icon icon="device-phone-mobile" class="size-4 text-zinc-700 dark:text-zinc-300" />
                                            @elseif ($token->client_platform === 'android')
                                                <flux:icon icon="device-phone-mobile" class="size-4 text-emerald-500" />
                                            @elseif ($token->client_platform === 'mac')
                                                <flux:icon icon="computer-desktop" class="size-4 text-zinc-700 dark:text-zinc-300" />
                                            @elseif ($token->client_platform === 'windows')
                                                <flux:icon icon="computer-desktop" class="size-4 text-teal-600 dark:text-teal-400" />
                                            @else
                                                <flux:icon icon="computer-desktop" class="size-4 text-zinc-400" />
                                            @endif
                                        </div>
                                        <div>
                                            <span class="font-semibold text-sm text-zinc-900 dark:text-zinc-100 block">{{ $token->name }}</span>
                                            <span class="text-[10px] text-zinc-400 font-mono">{{ __('ID:') }} {{ $token->id }}</span>
                                        </div>
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell class="py-3.5 px-4">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <flux:badge color="zinc" size="sm" class="uppercase font-mono text-[10px] font-bold">{{ $token->client_platform ?? 'client' }}</flux:badge>
                                        @if ($token->access_scope === 'read_only')
                                            <flux:badge color="amber" size="sm" class="font-semibold">{{ __('Read-Only') }}</flux:badge>
                                        @else
                                            <flux:badge color="zinc" size="sm" class="font-semibold">{{ __('Read/Write') }}</flux:badge>
                                        @endif
                                        @if ($token->is_wiped)
                                            <flux:badge color="red" size="sm" icon="no-symbol" class="font-bold">{{ __('Wiped') }}</flux:badge>
                                        @else
                                            <flux:badge color="emerald" size="sm" class="font-semibold">{{ __('Active') }}</flux:badge>
                                        @endif
                                        @if (! empty($token->allowed_ip_subnets))
                                            <flux:badge color="purple" size="sm" class="font-mono text-[10px]">{{ __('IP Guarded') }}</flux:badge>
                                        @endif
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell class="py-3.5 px-4 font-mono text-xs text-zinc-500">
                                    <span class="rounded bg-zinc-100 dark:bg-white/10 px-2 py-1 font-bold text-zinc-700 dark:text-zinc-300">{{ $token->token_preview }}</span>
                                </flux:table.cell>

                                <flux:table.cell class="py-3.5 px-4 text-xs">
                                    <div class="flex items-center gap-2">
                                        <flux:avatar :name="$token->user?->name ?? 'User'" size="xs" />
                                        <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $token->user?->name ?? __('Unknown') }}</span>
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell class="py-3.5 px-4 text-xs text-zinc-500 dark:text-zinc-400">
                                    @if ($token->last_used_at)
                                        <div class="font-medium text-zinc-700 dark:text-zinc-300">{{ $token->last_used_at->diffForHumans() }}</div>
                                        @if ($token->last_ip)
                                            <div class="text-[10px] font-mono text-zinc-400">{{ $token->last_ip }}</div>
                                        @endif
                                    @else
                                        <span class="text-zinc-400 italic text-xs">{{ __('Never synced') }}</span>
                                    @endif
                                </flux:table.cell>

                                <flux:table.cell align="end" class="py-3.5 px-4">
                                    <div class="flex items-center justify-end gap-1.5">
                                        @if (! $token->is_wiped)
                                            <flux:tooltip :content="__('Remote Wipe device')">
                                                <flux:button
                                                    variant="subtle"
                                                    size="sm"
                                                    icon="no-symbol"
                                                    wire:click="triggerRemoteWipe({{ $token->id }})"
                                                    wire:confirm="Issue Remote Wipe signal for this device? Next time it connects, all local sync state will be purged."
                                                    class="text-amber-600 hover:text-amber-700"
                                                />
                                            </flux:tooltip>
                                        @endif
                                        <flux:tooltip :content="__('Edit device settings')">
                                            <flux:button
                                                variant="subtle"
                                                size="sm"
                                                icon="pencil-square"
                                                wire:click="editToken({{ $token->id }})"
                                            />
                                        </flux:tooltip>
                                        <flux:tooltip :content="__('Revoke sync token')">
                                            <flux:button
                                                variant="subtle"
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
            </div>
        @endif
    </flux:card>


    <!-- Create Device Token Modal -->
    <flux:modal name="create-device-token" focusable class="max-w-md">
        <form wire:submit="generateToken" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Connect Device') }}</flux:heading>
                <flux:subheading class="text-xs">{{ __('Generate a secure sync token or instant QR code for your Obsidian device.') }}</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:input wire:model="deviceName" :label="__('Device Name')" placeholder="e.g. MacBook Pro, iPhone 15, Work PC" required />

                <flux:select wire:model="devicePlatform" :label="__('Operating System')">
                    <flux:select.option value="mac">{{ __('macOS') }}</flux:select.option>
                    <flux:select.option value="windows">{{ __('Windows PC') }}</flux:select.option>
                    <flux:select.option value="ios">{{ __('iPhone / iPad (iOS)') }}</flux:select.option>
                    <flux:select.option value="android">{{ __('Android Phone / Tablet') }}</flux:select.option>
                    <flux:select.option value="linux">{{ __('Linux') }}</flux:select.option>
                </flux:select>

                <flux:select wire:model="accessScope" :label="__('Access Scope')">
                    <flux:select.option value="full_access">{{ __('Full Read & Write Access') }}</flux:select.option>
                    <flux:select.option value="read_only">{{ __('Read-Only (Viewer / Contractor Device)') }}</flux:select.option>
                </flux:select>

                <flux:input wire:model="allowedIpSubnets" :label="__('Allowed IP Subnets (Optional)')" placeholder="e.g. 192.168.1.*, 10.0.* (leave empty for any IP)" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Generate Token & QR') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Edit Device Token Modal -->
    <flux:modal name="edit-device-token" focusable class="max-w-md">
        <form wire:submit="updateToken" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Edit Device') }}</flux:heading>
                <flux:subheading class="text-xs">{{ __('Update governance settings, scope, or platform for this device.') }}</flux:subheading>
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

                <flux:select wire:model="editAccessScope" :label="__('Access Scope')">
                    <flux:select.option value="full_access">{{ __('Full Read & Write Access') }}</flux:select.option>
                    <flux:select.option value="read_only">{{ __('Read-Only (Viewer / Contractor Device)') }}</flux:select.option>
                </flux:select>

                <flux:input wire:model="editAllowedSubnets" :label="__('Allowed IP Subnets (Optional)')" placeholder="e.g. 192.168.1.*, 10.0.* (leave empty for any IP)" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Save Changes') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Display Token Modal (Shows Plaintext Once + Instant QR Pairing) -->
    <flux:modal name="show-token-modal" focusable class="max-w-lg" :closable="false" :dismissible="false" :escapable="false">
        <div
            class="space-y-5"
            x-data="{
                activeTab: 'qr',
                copyState: 'idle',
                confirmed: false,
                sessionId: @js($pairingSessionId),
                pairStatus: 'pending',
                claimedDevice: '',
                pollTimer: null,
                startPolling() {
                    if (!this.sessionId) return;
                    if (this.pollTimer) clearInterval(this.pollTimer);
                    this.pollTimer = setInterval(async () => {
                        try {
                            const res = await fetch(`/api/v1/pairing/status?session=${encodeURIComponent(this.sessionId)}`);
                            if (!res.ok) return;
                            const data = await res.json();
                            this.pairStatus = data.status;
                            if (data.status === 'paired') {
                                this.confirmed = true;
                                this.claimedDevice = data.claimed_device_name || '';
                                clearInterval(this.pollTimer);
                                $wire.$refresh();
                            } else if (data.status === 'expired') {
                                clearInterval(this.pollTimer);
                            }
                        } catch (e) {}
                    }, 2000);
                },
                stopPolling() {
                    if (this.pollTimer) {
                        clearInterval(this.pollTimer);
                        this.pollTimer = null;
                    }
                },
                resetCopyState() {
                    this.copyState = 'idle';
                    this.confirmed = false;
                    this.activeTab = 'qr';
                    this.pairStatus = 'pending';
                    this.claimedDevice = '';
                    this.sessionId = @js($pairingSessionId);
                    this.startPolling();
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
            x-on:modal-close.document="if ($event.detail.name === 'show-token-modal') { stopPolling(); }"
        >
            <!-- Warning Banner -->
            <div class="rounded-lg border border-amber-300 bg-amber-50 p-3 dark:border-amber-500/30 dark:bg-amber-950/40">
                <div class="flex items-start gap-2.5">
                    <flux:icon icon="exclamation-triangle" class="size-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
                    <div>
                        <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">{{ __('Save this token or scan QR now — it won\'t be shown again') }}</p>
                        <p class="text-xs text-amber-700 dark:text-amber-300 mt-0.5">{{ __('For maximum cryptographic safety, the QR code uses one-scan scoped exchange and the manual token is only displayed once.') }}</p>
                    </div>
                </div>
            </div>

            <!-- Tab Switcher: QR Code vs Manual Token -->
            <div class="flex rounded-lg bg-zinc-100 p-1 dark:bg-zinc-800">
                <button
                    type="button"
                    class="flex-1 rounded-md py-1.5 text-xs font-semibold transition-colors"
                    x-bind:class="activeTab === 'qr' ? 'bg-white shadow text-zinc-900 dark:bg-zinc-700 dark:text-white' : 'text-zinc-500 hover:text-zinc-900 dark:text-zinc-400'"
                    x-on:click="activeTab = 'qr'"
                >
                    <span class="flex items-center justify-center gap-1.5">
                        <flux:icon icon="qr-code" class="size-4" />
                        {{ __('One-Scan QR Pairing') }}
                    </span>
                </button>
                <button
                    type="button"
                    class="flex-1 rounded-md py-1.5 text-xs font-semibold transition-colors"
                    x-bind:class="activeTab === 'token' ? 'bg-white shadow text-zinc-900 dark:bg-zinc-700 dark:text-white' : 'text-zinc-500 hover:text-zinc-900 dark:text-zinc-400'"
                    x-on:click="activeTab = 'token'"
                >
                    <span class="flex items-center justify-center gap-1.5">
                        <flux:icon icon="key" class="size-4" />
                        {{ __('Manual Token String') }}
                    </span>
                </button>
            </div>

            <!-- View 1: Instant QR Code Pairing -->
            <div x-show="activeTab === 'qr'" class="space-y-4 text-center">
                @if ($generatedQrCodeSvg)
                    <div class="inline-block rounded-xl bg-white p-4 shadow-sm border border-zinc-200 dark:border-zinc-700">
                        <div class="size-48 mx-auto flex items-center justify-center [&>svg]:size-full">
                            {!! $generatedQrCodeSvg !!}
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Scan with Obsidian Mobile Camera') }}</p>
                        <p class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-0.5">{{ __('Instantly pairs your server endpoint, target vault, and scoped device token via obsidian://synkk-pair.') }}</p>
                    </div>

                    <!-- Dynamic Pairing Status Badge -->
                    <div class="pt-1">
                        <template x-if="pairStatus === 'pending'">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700 dark:bg-amber-950/50 dark:text-amber-300">
                                <span class="size-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                {{ __('Waiting for mobile device to scan…') }}
                            </span>
                        </template>
                        <template x-if="pairStatus === 'paired'">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">
                                <flux:icon icon="check-circle" class="size-3.5" />
                                <span x-text="claimedDevice ? '{{ __('Paired with ') }}' + claimedDevice : '{{ __('Paired successfully!') }}'"></span>
                            </span>
                        </template>
                        <template x-if="pairStatus === 'expired'">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-1 text-xs font-medium text-rose-700 dark:bg-rose-950/50 dark:text-rose-300">
                                <flux:icon icon="x-circle" class="size-3.5" />
                                {{ __('Pairing session expired. Please generate a new QR code.') }}
                            </span>
                        </template>
                    </div>
                @else
                    <div class="p-6 text-center text-xs text-zinc-400">
                        {{ __('QR generation unavailable. Use manual token below.') }}
                    </div>
                @endif
                <button type="button" class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold underline underline-offset-4" x-on:click="confirmed = true">
                    {{ __('I have scanned the QR code') }}
                </button>
            </div>

            <!-- View 2: Manual Token Display -->
            <div x-show="activeTab === 'token'" class="space-y-3">
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

                <!-- Next Steps in Obsidian -->
                <div class="rounded-lg bg-zinc-50 p-3 text-xs text-zinc-600 dark:bg-white/5 dark:text-zinc-300 space-y-1">
                    <p class="font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Next Steps in Obsidian:') }}</p>
                    <ol class="list-decimal list-inside space-y-0.5 text-zinc-500 dark:text-zinc-400">
                        <li>{{ __('Open Settings -> Synkk Vault Sync') }}</li>
                        <li>{{ __('Paste the Server API URL: ') }} <code class="font-mono font-bold">{{ url('/api/v1') }}</code></li>
                        <li>{{ __('Paste this Device Sync Token') }}</li>
                        <li>{{ __('Click "Verify & Load Vaults", then choose your Target Vault') }}</li>
                    </ol>
                </div>
            </div>

            <!-- Close Button (only enabled after copy or QR scan) -->
            <div class="flex items-center justify-between pt-2 border-t border-zinc-100 dark:border-white/10">
                <p class="text-xs text-zinc-400" x-show="!confirmed">{{ __('Copy token or scan QR above to finish') }}</p>
                <p class="text-xs text-emerald-600 dark:text-emerald-400 font-medium" x-show="confirmed" x-cloak>
                    <flux:icon icon="check-circle" class="size-3.5 inline -mt-0.5" />
                    {{ __('Device configuration confirmed') }}
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
