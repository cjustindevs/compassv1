<?php

namespace Tests\Feature;

use App\Models\Adviser;
use App\Models\Helper;
use App\Models\Moderator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\HelperModuleSeeder::class);
    }

    public function test_adviser_module_views_render(): void
    {
        $user = User::where('role', 'adviser')->firstOrFail();

        $routes = [
            'adviser.dashboard',
            'adviser.evaluations',
            'adviser.referrals',
            'adviser.helpers',
            'adviser.reports',
            'adviser.calendar',
            'adviser.resources',
            'adviser.notifications',
            'adviser.settings',
        ];

        foreach ($routes as $route) {
            $this->actingAs($user)->get(route($route))->assertOk();
        }
    }

    public function test_moderator_module_views_render(): void
    {
        $user = \App\Models\User::firstOrCreate(
            ['email' => 'moderator@compass.edu.ph'],
            ['name' => 'Ivan Reyes', 'password' => bcrypt('password123'), 'role' => 'moderator', 'email_verified_at' => now()]
        );
        \App\Models\Moderator::firstOrCreate(
            ['user_account_id' => $user->id],
            ['first_name' => 'Ivan', 'last_name' => 'Reyes', 'email' => 'moderator@compass.edu.ph']
        );

        $routes = [
            'moderator.dashboard',
            'moderator.queue',
            'moderator.sessions',
            'moderator.manage',
            'moderator.emergency',
            'moderator.analytics',
            'moderator.reports',
            'moderator.notifications',
            'moderator.settings',
        ];

        foreach ($routes as $route) {
            $this->actingAs($user)->get(route($route))->assertOk();
        }
    }
}