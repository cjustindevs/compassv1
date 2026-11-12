<?php

namespace Tests\Unit;

use App\Support\RoleNav;
use PHPUnit\Framework\TestCase;

class RoleNavTest extends TestCase
{
    public function test_moderator_items_match_the_original_bottom_bar(): void
    {
        $items = RoleNav::bottomItems('moderator');
        $this->assertSame(['moderator.dashboard', 'moderator.queue', 'moderator.emergency'], array_column($items, 'route'));
        $this->assertSame(['Dashboard', 'Queue', 'Emergency'], array_column($items, 'label'));
        $this->assertSame(['dashboard', 'hourglass', 'warning'], array_column($items, 'icon'));
        $this->assertSame([null, 'queueCount', 'emergencyCount'], array_column($items, 'badge'));
        $this->assertSame([null, null, 'danger'], array_column($items, 'tone'));
        $this->assertSame('moderator.queue*', $items[1]['match']);
    }

    public function test_adviser_items_are_the_four_destinations_in_order(): void
    {
        $items = RoleNav::bottomItems('adviser');
        $this->assertSame(['adviser.dashboard', 'adviser.evaluations', 'adviser.referrals', 'adviser.emergencies'], array_column($items, 'route'));
        $this->assertSame(['Dashboard', 'Evaluations', 'Referrals', 'Emergencies'], array_column($items, 'label'));
        $this->assertSame([null, 'evalBadge', 'referralBadge', 'emergencyCount'], array_column($items, 'badge'));
        $this->assertSame('danger', $items[3]['tone']);
    }

    public function test_other_roles_have_no_bottom_items_or_strip(): void
    {
        foreach (['seeker', 'helper', 'professional', 'admin'] as $role) {
            $this->assertSame([], RoleNav::bottomItems($role));
        }
        $this->assertNull(RoleNav::emergencyStrip('seeker'));
        $this->assertSame(['route' => 'adviser.emergencies', 'hideOn' => ['adviser.emergencies*', 'adviser.emergency.*']], RoleNav::emergencyStrip('adviser'));
        $this->assertSame(['route' => 'moderator.emergency', 'hideOn' => 'moderator.emergency*'], RoleNav::emergencyStrip('moderator'));
    }
}
