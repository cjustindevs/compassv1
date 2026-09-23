<?php

use App\Models\Referral;
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

    if (! $session || !$user->is_active) {
        return false;
    }

    // The seeker or helper participating in the session.
    if (($user->role==='seeker' && $session->helper_accepted_at && $session->isActive() && $session->seeker_id && $session->seeker_id === $user->helpSeeker?->id)
        || ($user->role==='helper' && $session->helper_accepted_at && $session->isActive() && $session->helper_id && $session->helper_id === $user->helper?->id)) {
        return true;
    }

    // Conversation broadcasts are restricted to active participants.
    return false;
});

Broadcast::channel('helper.{userId}', function ($user, $userId) {
    return $user->is_active && (int) $user->id === (int) $userId && $user->role === 'helper';
});

Broadcast::channel('adviser.{userId}', function ($user, $userId) {
    return $user->is_active && (int) $user->id === (int) $userId && $user->role === 'adviser';
});

Broadcast::channel('moderator.{userId}', function ($user, $userId) {
    return $user->is_active && (int) $user->id === (int) $userId && $user->role === 'moderator';
});

Broadcast::channel('seeker.{userId}', function ($user, $userId) {
    return $user->is_active && $user->role==='seeker' && (int) $user->id === (int) $userId;
});
