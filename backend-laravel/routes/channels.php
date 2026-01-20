<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Public channel for inventory updates (no auth required for now)
// You can change this to a private channel with authentication if needed
Broadcast::channel('inventory', function ($user = null) {
    // Public channel - allow everyone, including unauthenticated users
    return true; // Allow all users to listen to inventory updates
});

// Public channel for notifications (no auth required for now)
// You can change this to a private channel with authentication if needed
Broadcast::channel('notifications', function ($user = null) {
    // Public channel - allow everyone, including unauthenticated users
    return true; // Allow all users to listen to notification updates
});

// User-specific channel for supply request approvals
Broadcast::channel('user.{userId}', function ($user, $userId) {
    // Only allow the user to listen to their own channel
    return (int) $user->id === (int) $userId;
});

