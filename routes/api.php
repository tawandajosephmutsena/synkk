<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\VaultCollaborationController;
use App\Http\Controllers\Api\VaultPreflightController;
use App\Http\Controllers\Api\VaultSyncController;
use App\Http\Controllers\Billing\DodoWebhookController;
use App\Http\Middleware\AuthenticateDeviceToken;
use Illuminate\Support\Facades\Route;

// Public Dodo webhook endpoint. Authenticity is enforced by Standard Webhooks
// signature validation in the controller, so no session or CSRF token is used.
Route::post('v1/billing/dodo/webhook', DodoWebhookController::class)
    ->name('api.billing.dodo.webhook');

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
            Route::post('preflight', [VaultPreflightController::class, 'preflight'])->name('api.vaults.preflight');
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
            Route::post('collab/join', [VaultCollaborationController::class, 'join'])->name('api.vaults.collab.join');
            Route::get('collab/catch-up', [VaultCollaborationController::class, 'catchUp'])->name('api.vaults.collab.catch_up');
            Route::get('collab/updates', [VaultCollaborationController::class, 'catchUp'])->name('api.vaults.collab.updates');
            Route::post('collab/append', [VaultCollaborationController::class, 'append'])->name('api.vaults.collab.append');
            Route::post('collab/sync', [VaultCollaborationController::class, 'syncLegacyOrAppend'])->name('api.vaults.collab.sync');
            Route::post('collab/checkpoint', [VaultCollaborationController::class, 'checkpoint'])->name('api.vaults.collab.checkpoint');
            Route::post('collab/leave', [VaultCollaborationController::class, 'leave'])->name('api.vaults.collab.leave');
            Route::get('collab/presence', [VaultCollaborationController::class, 'presence'])->name('api.vaults.collab.presence');

            // Ghost files (Selective Transport & On-Demand Hydration)
            Route::post('files/hydrate', [VaultSyncController::class, 'hydrateFile'])->name('api.vaults.files.hydrate');
            Route::post('files/dehydrate', [VaultSyncController::class, 'dehydrateFile'])->name('api.vaults.files.dehydrate');

            // Zero-Knowledge End-to-End Encryption (E2EE)
            Route::post('e2ee/enable', [VaultSyncController::class, 'enableE2ee'])->name('api.vaults.e2ee.enable');
            Route::get('e2ee/status', [VaultSyncController::class, 'e2eeStatus'])->name('api.vaults.e2ee.status');

            // Native Mobile Background Sync & Transport Relay Status
            Route::get('transport/status', [VaultSyncController::class, 'transportStatus'])->name('api.vaults.transport.status');

            // Agentic Knowledge Graph & Local RAG Server
            Route::post('rag/query', [VaultSyncController::class, 'ragQuery'])->name('api.vaults.rag.query');
            Route::post('rag/search', [VaultSyncController::class, 'ragSearch'])->name('api.vaults.rag.search');
            Route::post('rag/index', [VaultSyncController::class, 'ragIndex'])->name('api.vaults.rag.index');
            Route::get('rag/progress', [VaultSyncController::class, 'ragProgress'])->name('api.vaults.rag.progress');
            Route::get('rag/status', [VaultSyncController::class, 'ragStatus'])->name('api.vaults.rag.status');
        });
    });
