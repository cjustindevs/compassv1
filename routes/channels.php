<?php

use App\Models\Session;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Register the authorization callbacks for the application's broadcast
| channels. Only users who belong to a session may subscribe to its
| real-time channel.
|
*/

Broadcast::channel('session.{sessionId}', function ($user, $sessionId) {
    $session = Session::find($sessionId);

    if (! $session) {
        return false;
    }

    return ($session->seeker_id && $session->seeker_id === $user->helpSeeker?->id)
        || ($session->helper_id && $session->helper_id === $user->helper?->id);
});

Broadcast::channel('helper.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId && $user->role === 'helper';
});

Broadcast::channel('adviser.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId && $user->role === 'adviser';
});

Broadcast::channel('moderator.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId && $user->role === 'moderator';
});

Broadcast::channel('seeker.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
