<?php

namespace App\Support;

/**
 * Per-role phone navigation: bottom bar items and the emergency strip target.
 * Badge names refer to SidebarComposer view variables.
 */
class RoleNav
{
    /**
     * @return list<array{route:string, match:string|list<string>, label:string, icon:string, badge:?string, tone:?string}>
     */
    public static function bottomItems(string $role): array
    {
        return match ($role) {
            'moderator' => [
                ['route' => 'moderator.dashboard', 'match' => 'moderator.dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'badge' => null, 'tone' => null],
                ['route' => 'moderator.queue', 'match' => 'moderator.queue*', 'label' => 'Queue', 'icon' => 'hourglass', 'badge' => 'queueCount', 'tone' => null],
                ['route' => 'moderator.emergency', 'match' => 'moderator.emergency*', 'label' => 'Emergency', 'icon' => 'warning', 'badge' => 'emergencyCount', 'tone' => 'danger'],
            ],
            'adviser' => [
                ['route' => 'adviser.dashboard', 'match' => 'adviser.dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'badge' => null, 'tone' => null],
                ['route' => 'adviser.evaluations', 'match' => ['adviser.evaluations*', 'adviser.evaluate*'], 'label' => 'Evaluations', 'icon' => 'clipboard', 'badge' => 'evalBadge', 'tone' => null],
                ['route' => 'adviser.referrals', 'match' => ['adviser.referrals*', 'adviser.referral.*'], 'label' => 'Referrals', 'icon' => 'arrow-right', 'badge' => 'referralBadge', 'tone' => null],
                ['route' => 'adviser.emergencies', 'match' => 'adviser.emergencies*', 'label' => 'Emergencies', 'icon' => 'warning', 'badge' => 'emergencyCount', 'tone' => 'danger'],
            ],
            default => [],
        };
    }

    /**
     * @return array{route:string, hideOn:string}|null
     */
    public static function emergencyStrip(string $role): ?array
    {
        return match ($role) {
            'moderator' => ['route' => 'moderator.emergency', 'hideOn' => 'moderator.emergency*'],
            'adviser' => ['route' => 'adviser.emergencies', 'hideOn' => 'adviser.emergencies*'],
            default => null,
        };
    }
}
