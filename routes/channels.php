<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('personnel.{personnelId}', function ($user, int $personnelId): bool {
    return (int) $user->id === $personnelId
        || in_array($user->role, ['admin', 'superadmin'], true);
});

Broadcast::channel('App.Models.User.{id}', function ($user, int $id): bool {
    return (int) $user->id === $id;
});
