<?php

use App\Exceptions\AiException;
use App\Models\AiProposal;
use App\Models\User;
use App\Models\Vault;
use App\Services\Ai\AiProposalService;
use App\Services\Ai\VaultGardenerService;
use App\Services\PlanService;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public Vault $vault;

    public string $statusFilter = 'pending';

    public ?int $expandedProposalId = null;

    public string $overrideContent = '';

    /** @var array<string, mixed>|null */
    public ?array $expandedPreview = null;

    public function mount(Vault $vault): void
    {
        $this->vault = $vault;
    }

    #[On('vault-proposals-updated')]
    public function refreshInbox(): void
    {
        unset($this->proposals);
    }

    public function togglePreview(int $proposalId): void
    {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->can('view', $this->vault), 403);

        if ($this->expandedProposalId === $proposalId) {
            $this->expandedProposalId = null;
            $this->expandedPreview = null;
            $this->overrideContent = '';

            return;
        }

        $proposal = AiProposal::query()
            ->where('vault_id', $this->vault->id)
            ->findOrFail($proposalId);

        abort_if($this->vault->permissionForPath($user, $proposal->target_path) === 'hidden', 403);

        $preview = app(AiProposalService::class)->buildDiffPreview($proposal);
        $this->expandedProposalId = $proposalId;
        $this->expandedPreview = $preview;
        $this->overrideContent = $preview['is_stale']
            ? (string) $preview['diff']['merged_content']
            : $proposal->proposed_content;
    }

    public function approveProposal(int $proposalId): void
    {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->can('view', $this->vault), 403);

        $proposal = AiProposal::query()
            ->where('vault_id', $this->vault->id)
            ->findOrFail($proposalId);

        try {
            $override = $this->expandedProposalId === $proposalId && trim($this->overrideContent) !== ''
                ? $this->overrideContent
                : null;

            $result = app(AiProposalService::class)->approve($proposal, $user, $override);

            $this->expandedProposalId = null;
            $this->expandedPreview = null;
            $this->overrideContent = '';
            unset($this->proposals);

            $this->dispatch('vault-proposals-updated');

            $msg = $result['stale_merged']
                ? __('Proposal auto-merged with newer changes and synced to :path.', ['path' => $proposal->target_path])
                : __('Approved and synced changes to :path.', ['path' => $proposal->target_path]);

            Flux::toast(variant: 'success', text: $msg);
        } catch (AiException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function rejectProposal(int $proposalId): void
    {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->can('view', $this->vault), 403);

        $proposal = AiProposal::query()
            ->where('vault_id', $this->vault->id)
            ->findOrFail($proposalId);

        if ($this->vault->permissionForPath($user, $proposal->target_path) !== 'read_write') {
            Flux::toast(variant: 'danger', text: __('Write permission is required to reject proposals for this path.'));

            return;
        }

        try {
            app(AiProposalService::class)->reject($proposal, $user);

            if ($this->expandedProposalId === $proposalId) {
                $this->expandedProposalId = null;
                $this->expandedPreview = null;
                $this->overrideContent = '';
            }

            unset($this->proposals);
            $this->dispatch('vault-proposals-updated');

            Flux::toast(variant: 'warning', text: __('Proposal dismissed.'));
        } catch (AiException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    #[Computed]
    public function canRunGardener(): bool
    {
        return ! $this->vault->is_e2ee
            && $this->vault->team->aiConfig?->is_enabled === true
            && $this->vault->aiSetting?->ai_mode === 'server'
            && app(PlanService::class)->hasFeature($this->vault->team, 'ai_gardener');
    }

    public function runGardenerNow(): void
    {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->can('view', $this->vault), 403);

        try {
            $result = app(VaultGardenerService::class)->runForVault(
                vault: $this->vault,
                triggeredBy: $user,
                maxFiles: 10,
                force: true,
            );

            unset($this->proposals);
            $this->dispatch('vault-proposals-updated');

            Flux::toast(variant: 'success', text: __('Vault Gardener scanned :files files and staged :proposals proposals.', [
                'files' => $result['files_scanned'],
                'proposals' => $result['proposals_created'],
            ]));
        } catch (AiException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    /**
     * @return Collection<int, AiProposal>
     */
    #[Computed]
    public function proposals(): Collection
    {
        /** @var User $user */
        $user = Auth::user();

        $query = AiProposal::query()
            ->with(['creator', 'reviewer'])
            ->where('vault_id', $this->vault->id)
            ->orderByDesc('id');

        if (in_array($this->statusFilter, ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $this->statusFilter);
        }

        return $query->limit(50)->get()->filter(
            fn (AiProposal $proposal): bool => $this->vault->permissionForPath($user, $proposal->target_path) !== 'hidden'
        )->values();
    }
}; ?>

<div class="space-y-5" data-test="ai-proposals-inbox">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <flux:heading size="lg">{{ __('Vault AI Diff Approval Inbox') }}</flux:heading>
            <flux:subheading class="text-xs">
                {{ __('AI assistants and MCP agents stage edits here first. Review diffs and apply cleanly with 3-way stale protection.') }}
            </flux:subheading>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if ($this->canRunGardener)
                <flux:button
                    variant="subtle"
                    size="sm"
                    type="button"
                    wire:click="runGardenerNow"
                    wire:loading.attr="disabled"
                    data-test="inbox-run-gardener-button"
                >
                    <flux:icon icon="sparkles" class="size-3.5 mr-1 text-emerald-600 dark:text-emerald-400" />
                    {{ __('Run Gardener Now') }}
                </flux:button>
            @endif

            <div class="w-44">
                <flux:select wire:model.live="statusFilter" size="sm" data-test="ai-inbox-filter">
                    <flux:select.option value="pending">{{ __('Pending Review') }}</flux:select.option>
                    <flux:select.option value="approved">{{ __('Approved') }}</flux:select.option>
                    <flux:select.option value="rejected">{{ __('Rejected') }}</flux:select.option>
                    <flux:select.option value="all">{{ __('All History') }}</flux:select.option>
                </flux:select>
            </div>
        </div>
    </div>

    <div class="space-y-3">
        @forelse ($this->proposals as $proposal)
            @php
                $sourceColor = match ($proposal->source) {
                    'mcp' => 'indigo',
                    'digest' => 'sky',
                    'gardener_triage' => 'purple',
                    'gardener_links' => 'teal',
                    'gardener_drift' => 'rose',
                    default => 'emerald',
                };
            @endphp
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-2xs dark:border-zinc-800 dark:bg-zinc-900 space-y-3" data-test="ai-proposal-card-{{ $proposal->id }}">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-xs font-bold text-gray-900 dark:text-white">
                                {{ $proposal->target_path }}
                                @if ($proposal->action === 'move' && $proposal->destination_path)
                                    <span class="text-purple-600 dark:text-purple-400"> → {{ $proposal->destination_path }}</span>
                                @endif
                            </span>
                            <flux:badge size="sm" color="zinc">{{ strtoupper($proposal->action) }}</flux:badge>
                            <flux:badge size="sm" color="{{ $sourceColor }}">{{ strtoupper($proposal->source) }}</flux:badge>
                            <flux:badge size="sm" color="{{ $proposal->status === 'pending' ? 'amber' : ($proposal->status === 'approved' ? 'emerald' : 'zinc') }}">
                                {{ ucfirst($proposal->status) }}
                            </flux:badge>
                        </div>

                        @if ($proposal->rationale)
                            <p class="text-xs text-gray-600 dark:text-zinc-400">{{ $proposal->rationale }}</p>
                        @endif

                        <p class="text-[11px] text-gray-400 dark:text-zinc-500">
                            {{ __('Staged :time', ['time' => $proposal->created_at?->diffForHumans() ?? 'just now']) }}
                            @if ($proposal->creator)
                                · {{ __('via :name', ['name' => $proposal->creator->name]) }}
                            @endif
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        <flux:button
                            size="xs"
                            variant="subtle"
                            type="button"
                            wire:click="togglePreview({{ $proposal->id }})"
                            data-test="ai-proposal-preview-{{ $proposal->id }}"
                        >
                            {{ $expandedProposalId === $proposal->id ? __('Hide Diff') : __('Inspect Diff') }}
                        </flux:button>

                        @if ($proposal->status === 'pending')
                            <flux:button
                                size="xs"
                                variant="primary"
                                type="button"
                                wire:click="approveProposal({{ $proposal->id }})"
                                data-test="ai-proposal-approve-{{ $proposal->id }}"
                                class="!bg-[#0D3B29] !text-white hover:!bg-[#0D3B29]/90"
                            >
                                {{ __('Approve & Sync') }}
                            </flux:button>

                            <flux:button
                                size="xs"
                                variant="danger"
                                type="button"
                                wire:click="rejectProposal({{ $proposal->id }})"
                                data-test="ai-proposal-reject-{{ $proposal->id }}"
                            >
                                {{ __('Reject') }}
                            </flux:button>
                        @endif
                    </div>
                </div>

                @if ($expandedProposalId === $proposal->id && $expandedPreview !== null)
                    <div class="border-t border-gray-100 dark:border-zinc-800 pt-3 space-y-3">
                        @if ($expandedPreview['is_stale'])
                            <flux:callout
                                variant="warning"
                                icon="exclamation-triangle"
                                :heading="__('Base version v:base changed on disk (now v:curr). Showing 3-way merged preview below.', ['base' => $expandedPreview['base_version'], 'curr' => $expandedPreview['current_version']])"
                            />
                        @endif

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                            <div>
                                <div class="text-[11px] font-bold uppercase tracking-wider text-gray-500 mb-1">
                                    {{ __('Current Vault Note') }}
                                </div>
                                <pre class="max-h-64 overflow-auto rounded-xl border border-gray-200 bg-gray-50 p-3 text-xs font-mono text-gray-800 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-300 whitespace-pre-wrap">{{ $expandedPreview['current_content'] !== '' ? $expandedPreview['current_content'] : __('(New file — does not exist yet)') }}</pre>
                            </div>

                            <div>
                                <div class="text-[11px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400 mb-1">
                                    {{ $proposal->status === 'pending' ? __('Proposed / Editable Result') : __('Proposed Content') }}
                                </div>
                                @if ($proposal->status === 'pending')
                                    <flux:textarea
                                        wire:model="overrideContent"
                                        rows="10"
                                        class="font-mono text-xs"
                                    />
                                @else
                                    <pre class="max-h-64 overflow-auto rounded-xl border border-emerald-200 bg-emerald-50/30 p-3 text-xs font-mono text-gray-800 dark:border-emerald-900/40 dark:bg-zinc-950 dark:text-zinc-200 whitespace-pre-wrap">{{ $proposal->proposed_content }}</pre>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <flux:card class="py-10 text-center border-dashed">
                <flux:heading size="sm">{{ __('No :status proposals', ['status' => $statusFilter === 'all' ? '' : $statusFilter]) }}</flux:heading>
                <flux:subheading class="text-xs mt-1">
                    {{ __('When the Vault Agent or an external MCP agent proposes a note change, it appears here for diff review.') }}
                </flux:subheading>
            </flux:card>
        @endforelse
    </div>
</div>
