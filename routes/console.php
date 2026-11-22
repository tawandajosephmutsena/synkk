<?php

use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');

Schedule::command('vaults:prune-deleted')->daily()->description('Prune soft-deleted vault files');
Schedule::command('vaults:prune-collaboration')->daily()->description('Prune superseded collaboration journal updates');

Schedule::command('ai:generate-digests --period=daily')
    ->dailyAt('08:00')
    ->withoutOverlapping(30)
    ->description('Generate daily AI team digests for opted-in vaults');

Schedule::command('ai:generate-digests --period=weekly')
    ->weeklyOn(1, '08:00')
    ->withoutOverlapping(30)
    ->description('Generate weekly AI team digests for opted-in vaults');

Schedule::command('ai:run-gardener')
    ->dailyAt('02:30')
    ->withoutOverlapping(60)
    ->description('Run nightly Vault Gardener sweep for opted-in vaults');
