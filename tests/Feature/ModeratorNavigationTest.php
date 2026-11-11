<?php

namespace Tests\Feature;

use App\Models\EmergencyAlert;
use App\Models\HelpSeeker;
use App\Models\Session;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModeratorNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function moderator(): User
    {
        return User::factory()->create(['role' => 'moderator', 'is_active' => true]);
    }

    private function activeEmergency(): EmergencyAlert
    {
        $user = User::factory()->create(['role' => 'seeker', 'is_active' => true]);
        $seeker = HelpSeeker::create(['user_account_id' => $user->id, 'generated_alias' => 'SafeAlias'.$user->id]);
        $session = Session::create(['seeker_id' => $seeker->id, 'session_status' => 'waiting', 'risk_level' => 'emergency', 'session_type' => 'chat', 'submitted_at' => now(), 'created_date' => now()]);

        return EmergencyAlert::create(['session_id' => $session->id, 'seeker_id' => $seeker->id, 'status' => 'pending', 'risk_level' => 'emergency', 'trigger_reason' => 'x', 'triggered_at' => now()->subMinutes(20)]);
    }

    public function test_sidebar_groups_and_renamed_label(): void
    {
        $this->actingAs($this->moderator())->get(route('moderator.reports'))->assertOk()
            ->assertSee('Work now')->assertSee('People')->assertSee('Reconnection requests')
            ->assertDontSee('Connection review')->assertSee('id="emergencyBadge"', false);
    }

    public function test_bottom_nav_lists_moderator_destinations(): void
    {
        $this->actingAs($this->moderator())->get(route('moderator.reports'))->assertOk()
            ->assertSee('ui-bottom-nav', false)->assertSee(route('moderator.dashboard'), false)
            ->assertSee(route('moderator.queue'), false)->assertSee(route('moderator.emergency'), false)
            ->assertSee('data-open-sidebar', false);
    }

    public function test_emergency_strip_only_when_emergencies_are_active(): void
    {
        $this->actingAs($this->moderator());
        $this->get(route('moderator.reports'))->assertOk()->assertDontSee('ui-emergency-strip', false);
        $this->activeEmergency();
        $this->get(route('moderator.reports'))->assertOk()->assertSee('ui-emergency-strip', false)->assertSee('1 active emergency');
        $this->get(route('moderator.emergency'))->assertOk()->assertDontSee('ui-emergency-strip', false);
    }

    public function test_seeker_pages_have_no_emergency_strip_or_moderator_bottom_nav(): void
    {
        $this->activeEmergency();
        $seeker = User::factory()->create(['role' => 'seeker', 'is_active' => true]);
        HelpSeeker::create(['user_account_id' => $seeker->id, 'generated_alias' => 'Alias'.$seeker->id]);
        $r = $this->actingAs($seeker)->get('/');
        $r->assertDontSee('ui-emergency-strip', false)->assertDontSee('ui-bottom-nav', false);
    }
}
