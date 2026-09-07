<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\VaultSyncController;
use App\Http\Middleware\AuthenticateDeviceToken;
use Illuminate\Support\Facades\Route;

// Public Mobile Pairing endpoints
Route::prefix('v1')
    ->middleware(['throttle:api'])
    ->group(function () {
        Route::post('pairing/exchange', [AuthController::class, 'pairingExchange'])->name('api.pairing.exchange');
        Route::get('pairing/status', [AuthController::class, 'pairingStatus'])->name('api.pairing.status');
    });

Route::prefix('v1')
    ->middleware(['throttle:api', AuthenticateDeviceToken::class])
    ->group(function () {
        // Device & Auth verification
        Route::get('auth/verify', [AuthController::class, 'verify'])->name('api.auth.verify');
        Route::post('pairing/session', [AuthController::class, 'createPairingSession'])->name('api.pairing.session');

        // Vaults
        Route::get('vaults', [VaultSyncController::class, 'index'])->name('api.vaults.index');

        // Vault Sync Endpoints
        Route::prefix('vaults/{vault:slug}')->group(function () {
            Route::get('manifest', [VaultSyncController::class, 'manifest'])->name('api.vaults.manifest');
            Route::get('changes', [VaultSyncController::class, 'changes'])->name('api.vaults.changes');
            Route::get('download', [VaultSyncController::class, 'download'])->name('api.vaults.download');
            Route::post('upload', [VaultSyncController::class, 'upload'])->name('api.vaults.upload');
            Route::post('delete', [VaultSyncController::class, 'delete'])->name('api.vaults.delete');
            Route::post('batch-sync', [VaultSyncController::class, 'batchSync'])->name('api.vaults.batch_sync');

            // Conflict Resolution & 3-Way Diff Sandbox
            Route::get('conflicts', [VaultSyncController::class, 'conflicts'])->name('api.vaults.conflicts.index');
            Route::post('conflicts/diff', [VaultSyncController::class, 'diffConflict'])->name('api.vaults.conflicts.diff');
            Route::post('conflicts/resolve', [VaultSyncController::class, 'resolveConflict'])->name('api.vaults.conflicts.resolve');

            // CRDT Multiplayer Collaboration & Real-Time Relays
            Route::post('collab/join', [VaultSyncController::class, 'collabJoin'])->name('api.vaults.collab.join');
            Route::post('collab/sync', [VaultSyncController::class, 'collabSync'])->name('api.vaults.collab.sync');
            Route::post('collab/leave', [VaultSyncController::class, 'collabLeave'])->name('api.vaults.collab.leave');
            Route::get('collab/presence', [VaultSyncController::class, 'collabPresence'])->name('api.vaults.collab.presence');

            // Ghost files (Selective Transport & On-Demand Hydration)
            Route::post('files/hydrate', [VaultSyncController::class, 'hydrateFile'])->name('api.vaults.files.hydrate');
            Route::post('files/dehydrate', [VaultSyncController::class, 'dehydrateFile'])->name('api.vaults.files.dehydrate');

            // Zero-Knowledge End-to-End Encryption (E2EE)
            Route::post('e2ee/enable', [VaultSyncController::class, 'enableE2ee'])->name('api.vaults.e2ee.enable');
            Route::get('e2ee/status', [VaultSyncController::class, 'e2eeStatus'])->name('api.vaults.e2ee.status');

            // Native Mobile Background Sync & Transport Relay Status
            Route::get('transport/status', [VaultSyncController::class, 'transportStatus'])->name('api.vaults.transport.status');
        });
    });
