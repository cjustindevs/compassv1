<?php

namespace Tests\Unit;

use App\Support\RoleNav;
use PHPUnit\Framework\TestCase;

class RoleNavTest extends TestCase
{
    public function test_only_moderator_and_adviser_get_an_emergency_strip(): void
    {
        foreach (['seeker', 'helper', 'professional', 'admin'] as $role) {
            $this->assertNull(RoleNav::emergencyStrip($role));
        }
        $this->assertFalse(method_exists(RoleNav::class, 'bottomItems'));
        $this->assertNull(RoleNav::emergencyStrip('seeker'));
        $this->assertSame(['route' => 'adviser.emergencies', 'hideOn' => ['adviser.emergencies*', 'adviser.emergency.*']], RoleNav::emergencyStrip('adviser'));
        $this->assertSame(['route' => 'moderator.emergency', 'hideOn' => 'moderator.emergency*'], RoleNav::emergencyStrip('moderator'));
    }
}
