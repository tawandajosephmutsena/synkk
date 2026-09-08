<?php

use App\Broadcasting\VaultCollaborationChannel;
use App\Http\Middleware\AuthenticateDeviceToken;
use Illuminate\Support\Facades\Broadcast;

Broadcast::routes();
Broadcast::routes(['prefix' => 'api/v1', 'middleware' => ['throttle:api', AuthenticateDeviceToken::class]]);

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('vault-collaboration.{documentId}', VaultCollaborationChannel::class);
