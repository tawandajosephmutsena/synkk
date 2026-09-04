<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\VaultSyncController;
use App\Http\Middleware\AuthenticateDeviceToken;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware([AuthenticateDeviceToken::class])
    ->group(function () {
        // Device & Auth verification
        Route::get('auth/verify', [AuthController::class, 'verify'])->name('api.auth.verify');

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
        });
    });
