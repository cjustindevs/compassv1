<?php

namespace Tests\Feature;

use App\Models\Adviser;
use App\Models\ConcernCategory;
use App\Models\Helper;
use App\Models\HelperCompetencyHistory;
use App\Models\HelperSchedule;
use App\Models\HelpSeeker;
use App\Models\Notification;
use App\Models\ReadinessCheck;
use App\Models\Session;
use App\Models\StaffMail;
use App\Models\User;
use App\Services\CompactScreening;
use App\Services\ConsentService;
use App\Services\HelperDutyCandidates;
use App\Services\HelperMatchingService;
use App\Services\StaffMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OctoberQaRevisionTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): array
    {
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-10-14 10:00', 'Asia/Manila'));
        config(['app.enforce_duty_hours' => false, 'app.relax_duty_hours' => false, 'broadcasting.default' => 'log']);
        $admin = User::factory()->create(['role' => 'admin']);
        $mod = User::factory()->create(['role' => 'moderator']);
        $au = User::factory()->create(['role' => 'adviser']);
        $adviser = Adviser::create(['user_account_id' => $au->id, 'first_name' => 'Review', 'last_name' => 'Staff', 'email' => $au->email]);
        $hu = User::factory()->create(['role' => 'helper']);
        $helper = Helper::create(['user_account_id' => $hu->id, 'first_name' => 'Test', 'last_name' => 'Helper', 'email' => $hu->email, 'adviser_id' => $adviser->id, 'verification_status' => 'verified', 'verified_at' => now(), 'verified_by' => $au->id, 'training_verified' => true, 'status' => 'offline', 'competency_score' => 4, 'competency_level' => 3, 'competency_risk_level' => 3]);

        return [$admin, $mod, $au, $hu, $helper];
    }

    private function seeker(): User
    {
        $user = User::factory()->create(['role' => 'seeker']);
        HelpSeeker::create(['user_account_id' => $user->id, 'generated_alias' => 'PrivateOtter42']);
        $this->actingAs($user);
        foreach (['privacy_policy', 'informed_consent'] as $purpose) {
            app(ConsentService::class)->decide($user, $purpose, 'accepted');
        }

        return $user;
    }

    public function test_offline_verified_helper_can_be_scheduled_before_readiness_and_receives_notice(): void
    {
        [$admin,$mod,$au,$hu,$helper] = $this->staff();
        $this->assertTrue(app(HelperDutyCandidates::class)->allows($helper));
        $this->actingAs($mod)->post(route('moderator.schedules.store'), ['helper_id' => $helper->id, 'event_date' => '2026-10-14'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('helper_schedules', 1);
        $this->assertDatabaseHas('notifications', ['user_account_id' => $hu->id, 'title' => 'Duty scheduled']);
        $this->assertDatabaseCount('staff_mail_outbox', 1);
        $this->actingAs($hu)->get(route('helper.dashboard'))->assertOk()->assertSee('Today’s duty')->assertSee('Wed, Oct 14');
        $this->get(route('helper.readiness'))->assertOk();
    }

    public function test_cancel_duty_with_linked_readiness_retains_history_and_disables_readiness(): void
    {
        [$admin,$mod,$au,$hu,$helper] = $this->staff();
        $shift = HelperSchedule::create(['helper_id' => $helper->id, 'date' => '2026-10-14', 'is_active' => true, 'created_by' => $mod->id]);
        $check = ReadinessCheck::create(['helper_id' => $helper->id, 'helper_schedule_id' => $shift->id, 'assessment_result' => 'ready', 'assessment_date' => now(), 'valid_until' => now()->addHours(4), 'is_active' => true]);
        $this->actingAs($mod)->post(route('moderator.schedules.destroy'), ['helper_id' => $helper->id, 'date' => '2026-10-14', 'shift_id' => $shift->id])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('helper_schedules', ['id' => $shift->id, 'is_active' => false]);
        $this->assertFalse($check->fresh()->is_active);
        $this->assertSame('offline', $helper->fresh()->status);
        $this->assertSame('unavailable', $helper->fresh()->availability);
        $this->actingAs($hu)->get(route('helper.readiness'))->assertRedirect(route('helper.dashboard'))->assertSessionHas('warning');
        $this->get(route('helper.dashboard'))->assertOk()->assertSee('readiness is not open yet');
        $this->actingAs($mod)->post(route('moderator.schedules.store'), ['helper_id' => $helper->id, 'event_date' => '2026-10-14'])->assertSessionHasNoErrors();
        $this->assertTrue($shift->fresh()->is_active);
        $this->assertDatabaseCount('helper_schedules', 1);
        $this->assertFalse($check->fresh()->is_active);
    }

    public function test_recurring_schedule_creates_only_selected_dates_and_rolls_back_clashes(): void
    {
        [$admin,$mod,$au,$hu,$helper] = $this->staff();
        $data = ['helper_id' => $helper->id, 'event_date' => '2026-10-14', 'end_date' => '2026-10-19', 'schedule_type' => 'recurring', 'weekdays' => [1, 3, 5], 'shift_start' => '18:00', 'shift_end' => '23:00'];
        $this->actingAs($mod)->post(route('moderator.schedules.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('helper_schedules', 3);
        $this->post(route('moderator.schedules.store'), $data)->assertSessionHasErrors('event_date');
        $this->assertDatabaseCount('helper_schedules', 3);
        $this->get(route('moderator.schedules'))->assertOk()->assertSee('Duty Scheduling')->assertSee('Support Sessions');
    }

    public function test_single_yes_continues_to_queue_with_urgent_alert_and_no_premature_referral(): void
    {
        [$admin,$mod,$au,$hu,$helper] = $this->staff();
        $user = $this->seeker();
        $concern = ConcernCategory::firstOrCreate(['concern_name' => 'QA concern'], ['is_active' => true]);
        $this->post(route('request.screening.process'), ['screening_form_version' => CompactScreening::SINGLE_VERSION, 'safety_check' => 'yes', 'concern_id' => $concern->id, 'description' => 'Need support today.'])->assertRedirect(route('request.preferences'));
        $session = Session::firstOrFail();
        $this->assertTrue($session->requires_adviser_review);
        $this->assertSame('high', $session->risk_level);
        $this->assertTrue($session->permitsPrioritySafetySupport());
        $this->assertDatabaseCount('referrals', 0);
        $this->assertDatabaseHas('notifications', ['user_account_id' => $au->id, 'notification_type' => 'emergency']);
        $this->assertDatabaseHas('notifications', ['user_account_id' => $mod->id, 'notification_type' => 'emergency']);
        $this->post(route('request.preferences.process'), ['support_mode' => 'chat', 'preferred_language' => 'English'])->assertSessionHasNoErrors();
        $this->assertNotNull($session->fresh()->queue_request_id);
        $shift = HelperSchedule::create(['helper_id' => $helper->id, 'date' => '2026-10-14', 'is_active' => true, 'created_by' => $mod->id]);
        ReadinessCheck::create(['helper_id' => $helper->id, 'helper_schedule_id' => $shift->id, 'assessment_result' => 'ready', 'assessment_date' => now(), 'valid_until' => now()->addHours(4), 'is_active' => true]);
        $helper->update(['status' => 'available', 'availability' => 'available']);
        app(HelperMatchingService::class)->matchWaitingRequests();
        $session->refresh();
        $this->assertSame($helper->id, $session->helper_id);
        $this->assertSame('helper_assigned', $session->session_status);
        $this->assertDatabaseHas('notifications', ['user_account_id' => $hu->id, 'notification_type' => 'emergency', 'title' => 'Urgent safety case assigned']);
        $this->actingAs($au)->post(route('adviser.screenings.review', $session), ['risk_level' => 'high', 'allow_peer_support' => '1', 'reason' => 'Original screening reviewed alongside support.', 'evidence_source' => 'Original seeker screening'])->assertSessionHasNoErrors();
        $this->assertSame('helper_assigned', $session->fresh()->session_status);
        $this->assertDatabaseCount('referrals', 0);
    }

    public function test_prefer_not_to_say_retains_review_hold_and_cannot_submit_preferences(): void
    {
        $this->staff();
        $this->seeker();
        $concern = ConcernCategory::firstOrCreate(['concern_name' => 'QA concern'], ['is_active' => true]);
        $this->post(route('request.screening.process'), ['screening_form_version' => CompactScreening::SINGLE_VERSION, 'safety_check' => 'prefer_not_to_say', 'concern_id' => $concern->id, 'description' => 'Need support today.'])->assertSessionHasNoErrors();
        $session = Session::firstOrFail();
        $this->assertSame('adviser_review_required', $session->workflow_state);
        $this->assertNull($session->risk_level);
        $this->assertFalse($session->permitsPrioritySafetySupport());
        $this->post(route('request.preferences.process'), ['support_mode' => 'chat', 'preferred_language' => 'English'])->assertStatus(409);
        $this->assertDatabaseCount('queue_requests', 0);
    }

    public function test_editing_duty_invalidates_readiness_and_cancelled_date_collision_is_readable(): void
    {
        [$admin,$mod,$au,$hu,$helper] = $this->staff();
        $shift = HelperSchedule::create(['helper_id' => $helper->id, 'date' => '2026-10-14', 'is_active' => true, 'created_by' => $mod->id]);
        $check = ReadinessCheck::create(['helper_id' => $helper->id, 'helper_schedule_id' => $shift->id, 'assessment_result' => 'ready', 'assessment_date' => now(), 'valid_until' => now()->addHours(4), 'is_active' => true]);
        $data = ['helper_id' => $helper->id, 'shift_id' => $shift->id, 'event_date' => '2026-10-14', 'shift_start' => '19:00', 'shift_end' => '22:00'];
        $this->actingAs($mod)->post(route('moderator.schedules.store'), $data)->assertSessionHasNoErrors();
        $this->assertFalse($check->fresh()->is_active);
        HelperSchedule::create(['helper_id' => $helper->id, 'date' => '2026-10-15', 'is_active' => false, 'created_by' => $mod->id]);
        $data['event_date'] = '2026-10-15';
        $this->post(route('moderator.schedules.store'), $data)->assertSessionHasErrors('event_date');
        $this->assertSame('2026-10-14', $shift->fresh()->date->format('Y-m-d'));
    }

    public function test_notification_feed_cursor_does_not_skip_large_batches(): void
    {
        [$admin,$mod] = $this->staff();
        for ($i = 0; $i < 35; $i++) {
            Notification::create(['user_account_id' => $mod->id, 'title' => 'Assignment '.$i, 'message' => 'Open the queue', 'notification_type' => 'assignment']);
        }
        $first = $this->actingAs($mod)->getJson(route('notifications.feed', ['after' => 0]))->assertOk()->assertJsonCount(30, 'fresh');
        $this->getJson(route('notifications.feed', ['after' => $first->json('cursor')]))->assertOk()->assertJsonCount(5, 'fresh');
    }

    public function test_admin_reactivation_keeps_operational_status_and_profile_edit_does_not_change_role(): void
    {
        [$admin,$mod,$au,$hu,$helper] = $this->staff();
        $hu->update(['is_active' => false]);
        $this->actingAs($admin)->post(route('admin.users.reactivate', $hu), ['confirm_reactivation' => '1'])->assertSessionHasNoErrors();
        $this->assertTrue($hu->fresh()->is_active);
        $this->assertSame('offline', $helper->fresh()->status);
        $this->patch(route('admin.users.update', $hu), ['first_name' => 'Updated', 'last_name' => 'Helper', 'email' => $hu->email])->assertSessionHasNoErrors();
        $this->assertSame('Updated', $helper->fresh()->first_name);
        $this->patch(route('admin.users.update', $hu), ['first_name' => 'Updated', 'last_name' => 'Helper', 'email' => strtoupper($admin->email)])->assertSessionHasErrors('email');
        $this->patch(route('admin.users.update', $hu), ['first_name' => 'Updated', 'last_name' => 'Helper', 'email' => $hu->email, 'role' => 'admin'])->assertSessionHasErrors('role');
        $this->get(route('admin.users.edit', $hu))->assertOk()->assertSee('Edit Profile');
    }

    public function test_notification_feed_is_private_and_fetches_missed_alerts_by_cursor(): void
    {
        [$admin,$mod] = $this->staff();
        $a = Notification::create(['user_account_id' => $mod->id, 'title' => 'New assignment', 'message' => 'Open the queue', 'notification_type' => 'assignment']);
        Notification::create(['user_account_id' => $admin->id, 'title' => 'Private admin alert', 'message' => 'Hidden', 'notification_type' => 'system']);
        $this->actingAs($mod)->getJson(route('notifications.feed', ['after' => 0]))->assertOk()->assertJsonPath('count', 1)->assertJsonPath('fresh.0.id', $a->id)->assertDontSee('Private admin alert')->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson(route('notifications.feed', ['after' => $a->id]))->assertOk()->assertJsonCount(0, 'fresh');
    }

    public function test_staff_mail_retries_without_rolling_back_accounts_and_is_encrypted(): void
    {
        [$admin,$mod] = $this->staff();
        config(['otp.delivery_driver' => 'resend', 'services.resend.key' => 'private-test-key', 'mail.from.address' => 'no-reply@example.test', 'mail.from.name' => 'COMPASS']);
        app(StaffMailService::class)->enqueue($mod, 'test-welcome', 'Account created', 'Your account is ready.');
        $row = StaffMail::firstOrFail();
        $raw = DB::table('staff_mail_outbox')->value('payload');
        $this->assertStringNotContainsString($mod->email, $raw);
        Http::preventStrayRequests();
        Http::fake(['api.resend.com/*' => Http::sequence()->push([], 503)->push(['id' => 'delivered-test'], 200)]);
        app(StaffMailService::class)->deliver();
        $this->assertNull($row->fresh()->sent_at);
        $this->assertSame(1, $row->fresh()->attempts);
        $this->assertTrue($mod->fresh()->is_active);
        $this->travel(5)->minutes();
        app(StaffMailService::class)->deliver();
        $this->assertNotNull($row->fresh()->sent_at);
        app(StaffMailService::class)->deliver();
        Http::assertSentCount(2);
    }

    public function test_competency_scale_growth_and_philippine_period_boundaries_are_consistent(): void
    {
        [$admin,$mod,$au,$hu,$helper] = $this->staff();
        $a = HelperCompetencyHistory::create(['helper_id' => $helper->id, 'adviser_id' => $au->adviser->id, 'evaluation_date' => '2026-09-30 16:30:00', 'overall_score' => 82]);
        HelperCompetencyHistory::create(['helper_id' => $helper->id, 'adviser_id' => $au->adviser->id, 'evaluation_date' => '2026-10-14 01:00:00', 'overall_score' => 4.6]);
        HelperCompetencyHistory::create(['helper_id' => $helper->id, 'adviser_id' => $au->adviser->id, 'evaluation_date' => '2026-11-01 01:00:00', 'overall_score' => 1]);
        $this->assertSame(4.1, $a->normalized_score);
        $this->actingAs($mod)->get(route('moderator.analytics', ['from' => '2026-10-01', 'to' => '2026-10-14']))->assertOk()
            ->assertViewHas('metrics', fn ($m) => $m['competency_growth'] === 0.5)
            ->assertViewHas('competencyTrajectory', fn ($t) => $t['values'] === [4.4]);
        $this->actingAs($au)->get(route('adviser.helpers'))->assertOk()->assertDontSee('82 / 5');
    }
}
