<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class RoleDashboard
{
    /**
     * Resolve the dashboard route from the authenticated account's stored role.
     *
     * @throws AuthorizationException
     */
    public static function routeNameFor(User $user): string
    {
        return match ($user->role) {
            'admin' => 'admin.dashboard',
            'moderator' => 'moderator.dashboard',
            'adviser' => 'adviser.dashboard',
            'professional' => 'professional.dashboard',
            'helper' => 'helper.dashboard',
            'seeker' => 'seeker.dashboard',
            default => throw new AuthorizationException('The account role is not authorized for a COMPASS portal.'),
        };
    }

    /**
     * Resolve the absolute portal URL for an authenticated account.
     *
     * @throws AuthorizationException
     */
    public static function urlFor(User $user): string
    {
        return route(self::routeNameFor($user));
    }
}
