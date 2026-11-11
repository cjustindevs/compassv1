<?php

namespace Tests\Feature;

use App\Models\Helper;
use App\Models\HelpSeeker;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsAdviserWorld;
use Tests\TestCase;

class AdviserNavigationTest extends TestCase
{
    use BuildsAdviserWorld, RefreshDatabase;

    public function test_sidebar_groups_titles_and_hooks(): void
    {
        $adviser = $this->adviser();
        $response = $this->actingAs($adviser->user)->get(route('adviser.reports'))->assertOk()
            ->assertSee('Evaluation')->assertSee('Records')->assertSee('Account')
            ->assertSee('id="emergencyBadge"', false)->assertSee('data-adviser-badge="pendingReviewsBadge"', false)
            ->assertSee('title="Referral Queue"', false)->assertSee('title="Live Sessions"', false)
            ->assertSee('title="Areas of Concern"', false);
        foreach (['dashboard', 'screenings', 'evaluations', 'helpers', 'live.index', 'referrals', 'emergencies', 'reports', 'calendar', 'schedule', 'transcripts', 'resources', 'notifications', 'settings'] as $name) {
            $response->assertSee(route('adviser.'.$name), false);
        }
        $response->assertSee(route('concerns.manage'), false);
    }

    public function test_bottom_nav_lists_adviser_destinations(): void
    {
        $adviser = $this->adviser();
        $this->actingAs($adviser->user)->get(route('adviser.reports'))->assertOk()
            ->assertSee('ui-bottom-nav', false)
            ->assertSee('href="'.route('adviser.dashboard').'" class="ui-bottom-nav-item', false)
            ->assertSee('href="'.route('adviser.evaluations').'" class="ui-bottom-nav-item', false)
            ->assertSee('href="'.route('adviser.referrals').'" class="ui-bottom-nav-item', false)
            ->assertSee('href="'.route('adviser.emergencies').'" class="ui-bottom-nav-item', false)
            ->assertSee('data-open-sidebar', false)
            ->assertDontSee('moderator/queue', false);
    }

    public function test_emergency_strip_for_adviser_matches_the_active_tab_count(): void
    {
        $adviser = $this->adviser();
        $this->actingAs($adviser->user);
        $this->get(route('adviser.reports'))->assertOk()->assertDontSee('ui-emergency-strip', false);

        $alert = $this->activeEmergency($adviser);
        $this->activeEmergency($adviser, 'resolved');

        $this->get(route('adviser.reports'))->assertOk()->assertSee('ui-emergency-strip', false)->assertSee('1 active emergency');
        $this->get(route('adviser.emergencies'))->assertOk()->assertDontSee('ui-emergency-strip', false)
            ->assertSeeInOrder(['Active', '<span>1</span>'], false);
        $this->get(route('adviser.emergencies.show', $alert->id))->assertOk()->assertDontSee('ui-emergency-strip', false);
    }

    public function test_other_roles_get_no_strip_or_bottom_nav(): void
    {
        $this->activeEmergency();
        $seeker = User::factory()->create(['role' => 'seeker', 'is_active' => true]);
        HelpSeeker::create(['user_account_id' => $seeker->id, 'generated_alias' => 'Alias'.$seeker->id]);
        $this->actingAs($seeker)->get(route('request.screening'))->assertOk()
            ->assertDontSee('ui-emergency-strip', false)->assertDontSee('ui-bottom-nav', false);

        $helper = User::factory()->create(['role' => 'helper', 'is_active' => true]);
        Helper::create(['user_account_id' => $helper->id, 'email' => $helper->email, 'first_name' => 'Peer', 'last_name' => 'Helper']);
        $this->actingAs($helper)->get(route('helper.reconnections'))->assertOk()
            ->assertDontSee('ui-emergency-strip', false)->assertDontSee('ui-bottom-nav', false);
    }

    public function test_adviser_emergency_count_is_computed_once_per_request(): void
    {
        $adviser = $this->adviser();
        $this->actingAs($adviser->user);
        $this->activeEmergency($adviser);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('adviser.reports'))->assertOk()->assertSee('ui-emergency-strip', false)->assertSee('ui-bottom-nav', false);
        $count = collect(DB::getQueryLog())->filter(fn ($q) => str_starts_with($q['query'], 'select count(*) as aggregate from "emergency_alerts"') && ! str_contains($q['query'], 'between'))->count();
        // The reports page adds its own windowed count; the composer's unwindowed active count must run once.
        $this->assertSame(1, $count);
    }
}
