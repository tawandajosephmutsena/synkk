<?php

use App\Exceptions\AiException;
use App\Jobs\RunVaultAgentTurnJob;
use App\Models\AiMessage;
use App\Models\AiRun;
use App\Models\AiThread;
use App\Models\User;
use App\Models\Vault;
use App\Services\Ai\VaultAgentService;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public Vault $vault;

    public ?int $activeThreadId = null;

    public string $promptInput = '';

    public ?string $activeRunId = null;

    public string $streamingContent = '';

    public ?string $statusNote = null;

    public function mount(Vault $vault): void
    {
        $this->vault = $vault;

        $firstThread = $this->threads->first();
        if ($firstThread) {
            $this->activeThreadId = $firstThread->id;
        }
    }

    public function createThread(): void
    {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->can('view', $this->vault), 403);

        try {
            app(VaultAgentService::class)->assertVaultServerAiReady($this->vault, $user);
        } catch (AiException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $thread = AiThread::query()->create([
            'team_id' => $this->vault->team_id,
            'vault_id' => $this->vault->id,
            'user_id' => $user->id,
            'title' => 'New Conversation',
            'is_shared_with_team' => false,
            'last_message_at' => now(),
        ]);

        $this->activeThreadId = $thread->id;
        $this->activeRunId = null;
        $this->streamingContent = '';
        $this->statusNote = null;
        unset($this->threads, $this->activeThread, $this->messages);
    }

    public function selectThread(int $threadId): void
    {
        /** @var User $user */
        $user = Auth::user();

        $thread = AiThread::query()
            ->where('vault_id', $this->vault->id)
            ->where(function ($q) use ($user): void {
                $q->where('user_id', $user->id)->orWhere('is_shared_with_team', true);
            })
            ->findOrFail($threadId);

        $this->activeThreadId = $thread->id;
        $this->activeRunId = null;
        $this->streamingContent = '';
        $this->statusNote = null;
        unset($this->activeThread, $this->messages);
    }

    public function toggleShareThread(): void
    {
        /** @var User $user */
        $user = Auth::user();
        $thread = $this->activeThread;

        if (! $thread || $thread->user_id !== $user->id) {
            Flux::toast(variant: 'danger', text: __('Only the thread creator can change sharing settings.'));

            return;
        }

        $thread->forceFill([
            'is_shared_with_team' => ! $thread->is_shared_with_team,
        ])->save();

        unset($this->threads, $this->activeThread);
        Flux::toast(
            variant: 'success',
            text: $thread->is_shared_with_team ? __('Thread shared with team members.') : __('Thread made private.'),
        );
    }

    public function saveThreadAsNote(): void
    {
        /** @var User $user */
        $user = Auth::user();
        $thread = $this->activeThread;

        if (! $thread) {
            return;
        }

        try {
            $proposal = app(VaultAgentService::class)->saveThreadAsNote($thread, $user);
            $this->dispatch('vault-proposals-updated');
            Flux::toast(variant: 'success', text: __('Staged thread note proposal at :path in AI Inbox.', ['path' => $proposal->target_path]));
        } catch (AiException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function sendMessage(): void
    {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->can('view', $this->vault), 403);

        $prompt = trim($this->promptInput);
        if ($prompt === '') {
            return;
        }

        try {
            app(VaultAgentService::class)->assertVaultServerAiReady($this->vault, $user);
        } catch (AiException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $thread = $this->activeThread;
        if (! $thread) {
            $thread = AiThread::query()->create([
                'team_id' => $this->vault->team_id,
                'vault_id' => $this->vault->id,
                'user_id' => $user->id,
                'title' => 'New Conversation',
                'is_shared_with_team' => false,
                'last_message_at' => now(),
            ]);
            $this->activeThreadId = $thread->id;
        }

        $run = AiRun::query()->create([
            'thread_id' => $thread->id,
            'vault_id' => $this->vault->id,
            'user_id' => $user->id,
            'status' => 'queued',
            'partial_content' => '',
            'status_note' => __('Thinking...'),
        ]);

        $this->promptInput = '';
        $this->activeRunId = $run->id;
        $this->streamingContent = '';
        $this->statusNote = __('Thinking...');

        RunVaultAgentTurnJob::dispatch($run->id, $thread->id, $user->id, $prompt);

        $this->pollActiveRun();
        unset($this->threads, $this->activeThread, $this->messages);
    }

    public function pollActiveRun(): void
    {
        if ($this->activeRunId === null) {
            return;
        }

        $run = AiRun::query()->find($this->activeRunId);
        if (! $run) {
            $this->activeRunId = null;

            return;
        }

        $this->streamingContent = (string) ($run->partial_content ?? '');
        $this->statusNote = $run->status_note;

        if ($run->status === 'completed') {
            $this->activeRunId = null;
            $this->streamingContent = '';
            $this->statusNote = null;
            unset($this->threads, $this->activeThread, $this->messages);
            $this->dispatch('vault-proposals-updated');
        } elseif ($run->status === 'failed') {
            $this->activeRunId = null;
            $this->statusNote = null;
            Flux::toast(variant: 'danger', text: $run->error_message ?? __('Vault Agent failed to complete response.'));
        }
    }

    public function openCitation(string $path): void
    {
        $this->dispatch('open-citation-note', notePath: $path);
    }

    public function openAiInbox(): void
    {
        $this->dispatch('switch-vault-tab', tab: 'ai-inbox');
    }

    /**
     * @return Collection<int, AiThread>
     */
    #[Computed]
    public function threads(): Collection
    {
        /** @var User $user */
        $user = Auth::user();

        return AiThread::query()
            ->where('vault_id', $this->vault->id)
            ->where(function ($q) use ($user): void {
                $q->where('user_id', $user->id)->orWhere('is_shared_with_team', true);
            })
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(25)
            ->get();
    }

    #[Computed]
    public function activeThread(): ?AiThread
    {
        if ($this->activeThreadId === null) {
            return null;
        }

        /** @var User $user */
        $user = Auth::user();

        return AiThread::query()
            ->where('vault_id', $this->vault->id)
            ->where(function ($q) use ($user): void {
                $q->where('user_id', $user->id)->orWhere('is_shared_with_team', true);
            })
            ->find($this->activeThreadId);
    }

    /**
     * @return Collection<int, AiMessage>
     */
    #[Computed]
    public function messages(): Collection
    {
        if ($this->activeThreadId === null) {
            return collect();
        }

        return AiMessage::query()
            ->where('thread_id', $this->activeThreadId)
            ->orderBy('id')
            ->get();
    }
}; ?>

<div
    class="rounded-2xl border border-emerald-200/70 bg-white p-5 shadow-2xs dark:border-emerald-900/40 dark:bg-zinc-900 space-y-4"
    data-test="vault-ai-agent-panel"
    @if ($activeRunId !== null)
        wire:poll.700ms="pollActiveRun"
    @endif
>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-gray-100 dark:border-zinc-800 pb-3">
        <div>
            <div class="flex items-center gap-2">
                <flux:heading size="md">{{ __('Tool-Calling Vault Agent') }}</flux:heading>
                <flux:badge color="emerald" size="sm">{{ __('Threads + Proposals') }}</flux:badge>
            </div>
            <flux:subheading class="text-xs">
                {{ __('Multi-turn assistant with hybrid search, note reading, graph traversal, activity diffs, and staged note proposals.') }}
            </flux:subheading>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <flux:button size="xs" variant="subtle" type="button" wire:click="createThread" data-test="agent-new-thread">
                {{ __('+ New Thread') }}
            </flux:button>

            @if ($this->activeThread)
                <flux:button size="xs" variant="subtle" type="button" wire:click="toggleShareThread" data-test="agent-share-thread">
                    {{ $this->activeThread->is_shared_with_team ? __('Shared with Team') : __('Private Thread') }}
                </flux:button>

                <flux:button size="xs" variant="subtle" type="button" wire:click="saveThreadAsNote" data-test="agent-save-note">
                    {{ __('Save as Note') }}
                </flux:button>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
        <!-- Threads Sidebar -->
        <div class="lg:col-span-3 space-y-1.5 max-h-96 overflow-y-auto pr-1">
            @forelse ($this->threads as $thread)
                <button
                    type="button"
                    wire:click="selectThread({{ $thread->id }})"
                    class="w-full text-left rounded-xl px-3 py-2 text-xs transition-colors cursor-pointer {{ $activeThreadId === $thread->id ? 'bg-[#0D3B29] text-white font-bold' : 'bg-gray-50 hover:bg-gray-100 text-gray-700 dark:bg-zinc-800/60 dark:text-zinc-300 dark:hover:bg-zinc-800' }}"
                >
                    <div class="truncate">{{ $thread->title }}</div>
                    <div class="text-[10px] opacity-75 mt-0.5">
                        {{ $thread->is_shared_with_team ? __('Team') : __('Private') }} · {{ $thread->last_message_at?->diffForHumans() ?? __('New') }}
                    </div>
                </button>
            @empty
                <div class="rounded-xl border border-dashed border-gray-200 p-3 text-xs text-gray-500 dark:border-zinc-800 dark:text-zinc-400">
                    {{ __('Send a message below to start your first Vault Agent thread.') }}
                </div>
            @endforelse
        </div>

        <!-- Conversation Canvas -->
        <div class="lg:col-span-9 flex flex-col space-y-4">
            <div class="space-y-3 max-h-[28rem] overflow-y-auto pr-1">
                @foreach ($this->messages as $msg)
                    @if ($msg->role === 'user')
                        <div class="flex justify-end">
                            <div class="max-w-xl rounded-2xl bg-[#0D3B29] px-4 py-2.5 text-xs text-white shadow-2xs">
                                {{ $msg->content }}
                            </div>
                        </div>
                    @else
                        <div class="rounded-2xl border border-gray-200 bg-[#FBFBFA] p-4 text-xs text-gray-800 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-200 space-y-3">
                            <div class="whitespace-pre-wrap leading-relaxed">{{ $msg->content }}</div>

                            @if (! empty($msg->proposal_ids))
                                <div class="flex flex-wrap items-center gap-2 pt-1">
                                    <button
                                        type="button"
                                        wire:click="openAiInbox"
                                        class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-[11px] font-bold text-amber-900 hover:bg-amber-200 dark:bg-amber-950/60 dark:text-amber-300 cursor-pointer"
                                    >
                                        <span>{{ __('Review :count Staged Proposal(s) in AI Inbox →', ['count' => count($msg->proposal_ids)]) }}</span>
                                    </button>
                                </div>
                            @endif

                            @if (! empty($msg->citations))
                                <div class="flex flex-wrap gap-1.5 border-t border-gray-200/80 dark:border-zinc-800 pt-2">
                                    @foreach ($msg->citations as $cit)
                                        <button
                                            type="button"
                                            wire:click="openCitation('{{ addslashes((string) ($cit['note'] ?? '')) }}')"
                                            class="rounded-full border border-gray-200 bg-white px-2.5 py-0.5 text-[11px] font-mono text-gray-700 hover:border-emerald-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 cursor-pointer"
                                        >
                                            [[{{ $cit['note'] ?? '' }}]]
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif
                @endforeach

                @if ($activeRunId !== null)
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50/40 p-4 text-xs dark:border-emerald-900/40 dark:bg-emerald-950/20 space-y-2">
                        <div class="flex items-center gap-2 text-emerald-700 dark:text-emerald-400 font-semibold">
                            <flux:icon.loading class="size-3.5" />
                            <span>{{ $statusNote ?: __('Streaming response...') }}</span>
                        </div>
                        @if ($streamingContent !== '')
                            <div class="whitespace-pre-wrap text-gray-800 dark:text-zinc-200">{{ $streamingContent }}</div>
                        @endif
                    </div>
                @endif
            </div>

            <form wire:submit="sendMessage" class="flex flex-col sm:flex-row gap-2">
                <div class="flex-1">
                    <flux:input
                        wire:model="promptInput"
                        :placeholder="__('Ask the agent to research notes, summarize recent changes, or propose a draft...')"
                        data-test="agent-prompt-input"
                    />
                </div>
                <flux:button
                    variant="primary"
                    type="submit"
                    data-test="agent-send-button"
                    class="!bg-[#0D3B29] !text-white hover:!bg-[#0D3B29]/90 !rounded-full px-5 text-xs font-bold"
                >
                    {{ __('Send') }}
                </flux:button>
            </form>
        </div>
    </div>
</div>
