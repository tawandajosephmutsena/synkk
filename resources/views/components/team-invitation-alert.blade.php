@props([
    'invitation',
    'action',
])

<div data-test="team-invitation-alert">
    <div class="flex gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/50 dark:text-emerald-100">
        <flux:icon name="information-circle" class="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />

        <div>
            {{ __(':action to join the ":team" team.', ['action' => $action, 'team' => $invitation['teamName']]) }}
        </div>
    </div>
</div>
