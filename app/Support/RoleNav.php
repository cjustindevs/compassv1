<?php

namespace App\Support;

/**
 * Per-role emergency strip target (phones use the collapsible sidebar for navigation).
 */
class RoleNav
{
    /**
     * @return array{route:string, hideOn:string|array<int,string>}|null
     */
    public static function emergencyStrip(string $role): ?array
    {
        return match ($role) {
            'moderator' => ['route' => 'moderator.emergency', 'hideOn' => 'moderator.emergency*'],
            'adviser' => ['route' => 'adviser.emergencies', 'hideOn' => ['adviser.emergencies*', 'adviser.emergency.*']],
            default => null,
        };
    }
}
