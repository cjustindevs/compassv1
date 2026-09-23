<?php

namespace Tests\Feature;

use App\Models\AdviserFeedback;
use App\Models\Helper;
use App\Models\HelperCompetencyHistory;
use App\Models\HelperSchedule;
use App\Models\HelpSeeker;
use App\Models\QueueRequest;
use App\Models\Session;
use App\Models\SessionReport;
use App\Models\User;
use App\Services\HelperEligibilityService;
use App\Services\HelperReadinessService;
use App\Services\HelperWorkflowMaintenance;
use App\Services\SeekerWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class HelperWorkflowSecurityTest extends TestCase
{
    use RefreshDatabase, \Tests\Concerns\SeekerWorkflowFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-16 20:00', 'Asia/Manila')->utc());
    }

    private function helper(bool $verified = true): Helper
    {
        $user = User::factory()->create(['role' => 'helper', 'is_active' => true]);
        $helper = Helper::create(['user_account_id' => $user->id, 'first_name' => 'Test', 'last_name' => 'Helper', 'email' => $user->email, 'status' => 'available', 'availability' => 'available', 'competency_level' => 3, 'competency_risk_level' => 3]);
        if ($verified) {
            $this->verifiedHelperFixture($helper);
        }
        HelperSchedule::create(['helper_id' => $helper->id, 'date' => now('Asia/Manila')->toDateString(), 'shift_start' => '18:00', 'shift_end' => '23:00', 'created_by' => $user->id, 'is_active' => true]);
        $this->actingAs($user);
        app(HelperReadinessService::class)->submit($user, $this->readiness());

        return $helper->fresh();
    }

    private function readiness(array $overrides = []): array
    {
        return array_replace(['emotionally_ready' => true, 'willing_to_listen' => true, 'stress_level' => 'low', 'availability_status' => 'available', 'exercise_completed' => 'skipped', 'skills_confirmed' => HelperReadinessService::SKILLS], $overrides);
    }

    private function supportSession(Helper $helper, string $status = 'active'): Session
    {
        $user = User::factory()->create(['role' => 'seeker']);
        $seeker = HelpSeeker::create(['user_account_id' => $user->id, 'generated_alias' => 'Seeker'.$user->id, 'age' => 20, 'gender' => 'male']);
        $this->consentFixture($user);

        return Session::create(['seeker_id' => $seeker->id, 'helper_id' => $helper->id, 'risk_level' => 'low', 'session_status' => $status, 'start_time' => $status === 'active' ? now() : null, 'helper_accepted_at' => $status === 'active' ? now() : null, 'submitted_at' => now()]);
    }

    private function summary(array $overrides = []): array
    {
        return array_replace(['session_summary' => 'Peer support discussion.', 'observations' => 'Seeker described academic stress.', 'actions_taken' => 'Listened and discussed resources.', 'session_result' => 'stable'], $overrides);
    }

    public function test_unverified_helpers_can_use_portal_but_cannot_receive_assignments(): void
    {
        $helper = $this->helper(false);
        foreach (['helper.dashboard', 'helper.profile', 'helper.calendar', 'helper.readiness', 'helper.competency', 'helper.self-help'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->assertFalse(app(HelperEligibilityService::class)->allows($helper));
        $this->assertContains('Institutional verification and training approval are required.', app(HelperEligibilityService::class)->reasons($helper));
    }

    public function test_readiness_expires_at_shift_end_and_latest_failure_wins(): void
    {
        $helper = $this->helper();
        $check = $helper->getCurrentReadiness();
        $this->assertSame('23:00', $check->valid_until->timezone('Asia/Manila')->format('H:i'));
        $this->assertSame(HelperReadinessService::VERSION, $check->form_version);
        $this->assertCount(5, $check->skills_confirmed);
        app(HelperReadinessService::class)->submit($helper->user, $this->readiness(['stress_level' => 'high']));
        $this->assertFalse($helper->fresh()->isReady());
        $this->assertFalse($check->fresh()->is_active);
        $this->get(route('helper.dashboard'))->assertOk();
    }

    public function test_return_from_unavailable_requires_fresh_readiness(): void
    {
        $helper = $this->helper();
        $helper->setAvailability('unavailable', 'Rest');
        $this->post(route('helper.availability.update'), ['status' => 'available'])->assertRedirect(route('helper.readiness'));
        $this->assertFalse($helper->fresh()->isReady());
    }

    public function test_summary_and_reflection_are_separate_and_corrections_preserve_history(): void
    {
        $helper = $this->helper();
        $session = $this->supportSession($helper);
        $this->post(route('helper.session.notes.store', $session->id), $this->summary(['risk_level_assessed' => 'high']))->assertSessionHasNoErrors();
        $this->assertSame('active', $session->fresh()->session_status);
        $this->assertSame('low', $session->fresh()->risk_level);
        $this->assertNull($session->fresh()->end_time);
        $report = $session->report()->firstOrFail();
        $this->assertNull($report->reflection_submitted_at);
        $this->assertNotNull($report->reassessment_requested_at);
        $this->post(route('helper.reflection.submit', $session->id), ['personal_reflection' => 'I can improve my listening.', 'skills_applied' => ['active_listening']])->assertSessionHasNoErrors();
        $this->assertSame('submitted', $session->fresh()->documentation_status);
        $this->post(route('helper.session.notes.store', $session->id), $this->summary(['session_summary' => 'Corrected summary.']))->assertSessionHasErrors('correction_reason');
        $this->post(route('helper.session.notes.store', $session->id), $this->summary(['session_summary' => 'Corrected summary.', 'correction_reason' => 'Corrected inaccurate wording.']))->assertSessionHasNoErrors();
        $snapshot = json_decode(DB::table('session_report_revisions')->where('report_id', $report->id)->value('snapshot'), true);
        $this->assertSame('Peer support discussion.', $snapshot['session_summary']);
        $this->assertSame('I can improve my listening.', $report->fresh()->personal_reflection);
    }

    public function test_reflection_can_be_submitted_before_the_summary(): void
    {
        $helper = $this->helper();
        $session = $this->supportSession($helper);

        $this->post(route('helper.reflection.submit', $session->id), [
            'personal_reflection' => 'I listened actively and stayed present.',
            'skills_applied' => ['active_listening', 'empathy'],
        ])->assertSessionHasNoErrors();

        $report = $session->report()->firstOrFail();
        $this->assertNull($report->session_summary);
        $this->assertNotNull($report->reflection_submitted_at);
        $this->assertSame('incomplete', $session->fresh()->documentation_status);

        $this->post(route('helper.session.notes.store', $session->id), $this->summary())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame('Peer support discussion.', $session->report()->firstOrFail()->session_summary);
        $this->assertSame('submitted', $session->fresh()->documentation_status);
    }

    public function test_approved_results_and_follow_up_plan_are_validated(): void
    {
        $helper = $this->helper();
        $session = $this->supportSession($helper);
        $this->post(route('helper.session.notes.store', $session->id), $this->summary(['session_result' => 'diagnosed']))->assertSessionHasErrors('session_result');
        $this->post(route('helper.session.notes.store', $session->id), $this->summary(['session_result' => 'needs_follow_up']))->assertSessionHasErrors('follow_up_plan');
        $this->post(route('helper.reflection.submit', $session->id), ['personal_reflection' => 'Reflection'])->assertSessionHasErrors('skills_applied');
    }

    public function test_foreign_helper_cannot_access_or_modify_session_records(): void
    {
        $owner = $this->helper();
        $session = $this->supportSession($owner);
        $other = $this->helper();
        foreach (['helper.session.notes', 'helper.cases.show', 'helper.session.pre-assessment'] as $route) {
            $this->get(route($route, $session->id))->assertNotFound();
        }
        $this->post(route('helper.session.notes.store', $session->id), $this->summary())->assertNotFound();
        $this->post(route('helper.session.end', $session->id))->assertNotFound();
        $this->assertSame('active', $session->fresh()->session_status);
        $this->assertFalse(Gate::forUser($other->user)->allows('view', new SessionReport(['session_id' => $session->id])));
    }

    public function test_every_helper_route_rejects_other_roles(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())->filter(fn ($r) => in_array('role:helper', $r->gatherMiddleware()));
        foreach (['seeker', 'moderator', 'adviser', 'professional', 'admin'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            Helper::create(['user_account_id' => $user->id, 'first_name' => 'Stray', 'last_name' => 'Profile', 'email' => $user->email]);
            foreach ($routes as $route) {
                $uri = '/'.preg_replace('/\{[^}]+\}/', '999', $route->uri());
                $this->actingAs($user)->call($route->methods()[0], $uri)->assertForbidden();
            }
        }
    }

    public function test_pending_chat_and_start_are_blocked_until_acceptance(): void
    {
        $helper = $this->helper();
        $session = $this->supportSession($helper, 'helper_assigned');
        $this->get(route('helper.session.chat', $session->id))->assertRedirect(route('helper.cases'));
        $this->post(route('helper.session.start', $session->id))
            ->assertRedirect(route('helper.session.pre-assessment', $session->id))
            ->assertSessionHas('error');
        $this->post(route('helper.cases.accept', $session->id))->assertRedirect();
        $this->assertNull($session->fresh()->start_time);
        $this->post(route('helper.session.start', $session->id))->assertRedirect();
        $started = $session->fresh()->start_time;
        $this->actingAs($helper->user);
        app(SeekerWorkflowService::class)->start($helper->user, $session->fresh());
        $this->assertTrue($started->eq($session->fresh()->start_time));
        $this->assertSame(1, $helper->fresh()->current_shift_sessions);
    }

    public function test_start_marks_session_active_and_chat_opens(): void
    {
        $helper = $this->helper();
        $session = $this->supportSession($helper, 'helper_assigned');

        $this->post(route('helper.cases.accept', $session->id))->assertRedirect(route('helper.session.pre-assessment', $session->id));
        $this->assertSame('helper_assigned', $session->fresh()->session_status);

        $this->post(route('helper.session.start', $session->id))->assertRedirect(route('helper.session.chat', $session->id));
        $session->refresh();
        $this->assertSame('active', $session->session_status);
        $this->assertNotNull($session->start_time);

        $this->get(route('helper.session.chat', $session->id))->assertOk();
    }

    public function test_recommendation_expiry_is_scheduled_and_get_is_read_only(): void
    {
        $helper = $this->helper();
        $session = $this->supportSession($helper, 'helper_assigned');
        $session->update(['pre_session_brief_expires_at' => now()->subMinute()]);
        $queue = QueueRequest::create(['seeker_id' => $session->seeker_id, 'request_status' => 'assigned', 'assigned_helper_id' => $helper->id, 'priority_level' => 'low']);
        $session->update(['queue_request_id' => $queue->id]);
        $this->get(route('helper.cases.show', $session->id))->assertOk();
        $this->assertSame($helper->id, $session->fresh()->helper_id);
        app(HelperWorkflowMaintenance::class)->run();
        $this->assertNull($session->fresh()->helper_id);
        $this->assertSame('expired', $session->fresh()->match_status);
        $this->assertSame('waiting', $queue->fresh()->request_status);
        $count = DB::table('notifications')->count();
        app(HelperWorkflowMaintenance::class)->run();
        $this->assertSame($count, DB::table('notifications')->count());
    }

    public function test_expired_recommendation_accept_and_decline_redirect_gracefully(): void
    {
        $helper = $this->helper();
        $session = $this->supportSession($helper, 'helper_assigned');
        $session->update(['pre_session_brief_expires_at' => now()->subMinute()]);

        $this->post(route('helper.cases.accept', $session->id))
            ->assertRedirect(route('helper.cases'))
            ->assertSessionHas('error');

        $session->refresh();
        $this->assertNull($session->helper_id);
        $this->assertSame('expired', $session->match_status);

        $session2 = $this->supportSession($helper, 'helper_assigned');
        $session2->update(['pre_session_brief_expires_at' => now()->subMinute()]);

        $this->post(route('helper.cases.decline', ['id' => $session2->id]), ['reason' => 'unavailable'])
            ->assertRedirect(route('helper.cases'))
            ->assertSessionHas('error');

        $this->assertNull($session2->fresh()->helper_id);
    }

    public function test_helper_cannot_grant_own_qualification_or_edit_competency(): void
    {
        $helper = $this->helper(false);
        $this->put(route('helper.profile.update'), ['first_name' => 'Test', 'last_name' => 'Helper', 'specializations' => 'Declared interest', 'verification_status' => 'verified', 'training_verified' => true, 'competency_level' => 5])->assertSessionHasNoErrors();
        $this->assertSame('pending', $helper->fresh()->verification_status);
        $this->assertFalse($helper->fresh()->training_verified);
        $this->assertSame('Declared interest', $helper->fresh()->declared_specializations);
        $this->assertFalse(Gate::forUser($helper->user)->allows('update', new HelperCompetencyHistory(['helper_id' => $helper->id])));
    }

    public function test_only_assigned_adviser_can_record_institutional_verification(): void
    {
        $helper = $this->helper();
        $adviser = $helper->adviser;
        $helper->update(['verification_status' => 'pending', 'training_verified' => false]);
        $data = ['currently_enrolled' => 1, 'recognized_member' => 1, 'training_completed' => 1, 'qualification_evidence' => 'Institutional roster and completed orientation reviewed.'];
        $this->post(route('adviser.helper.verify', $helper->id), $data)->assertForbidden();
        $other = $this->helper()->adviser;
        $this->actingAs($other->user)->post(route('adviser.helper.verify', $helper->id), $data)->assertForbidden();
        $this->actingAs($adviser->user)->post(route('adviser.helper.verify', $helper->id), $data)->assertSessionHasNoErrors();
        $this->assertSame('verified', $helper->fresh()->verification_status);
        $this->assertSame($adviser->user_account_id, $helper->fresh()->verified_by);
        $this->assertTrue($helper->fresh()->training_verified);
        $this->assertDatabaseHas('audit_logs', ['action' => 'helper_institutionally_verified', 'target_id' => $helper->id]);
    }

    public function test_weighted_rubric_is_persisted_once_and_helper_feedback_is_private(): void
    {
        $helper = $this->helper();
        $session = $this->supportSession($helper);
        $session->update(['session_status' => 'completed', 'end_time' => now()]);
        $report = SessionReport::create(['session_id' => $session->id, 'session_summary' => 'Summary', 'personal_reflection' => 'Reflection']);
        $data = ['active_listening' => 5, 'empathy' => 4, 'respect_professionalism' => 3, 'ethical_practices' => 2, 'referral_accuracy' => 1, 'recommended_action' => 'mentoring', 'recommendations' => 'Review active-listening exercises.'];
        $this->actingAs($helper->adviser->user)->post(route('adviser.evaluate.store', $report->id), $data)->assertSessionHasNoErrors();
        $this->post(route('adviser.evaluate.store', $report->id), $data)->assertRedirect();
        $this->assertSame(1, $helper->competencyHistory()->count());
        $this->assertEqualsWithDelta(3.35, $helper->fresh()->competency_score, 0.001);
        $evaluation = $helper->competencyHistory()->firstOrFail();
        $this->actingAs($helper->user)->get(route('helper.competency.view', $evaluation->id))->assertOk()->assertSee('67%');
        $feedback = $report->fresh()->id;
        $record = AdviserFeedback::where('report_id', $feedback)->firstOrFail();
        $this->assertEqualsWithDelta(3.35, $record->fresh()->competency_rating, 0.001);
        $this->post(route('helper.feedback.acknowledge', $record->id))->assertRedirect();
        $this->assertNotNull($record->fresh()->acknowledged_at);
        $other = $this->helper();
        $this->get(route('helper.competency.view', $evaluation->id))->assertNotFound();
        $this->get(route('helper.feedback.view', $record->id))->assertNotFound();
        $this->post(route('helper.feedback.acknowledge', $record->id))->assertNotFound();
    }

    public function test_evaluation_defaults_to_documentation_without_conversation_consent(): void
    {
        $helper = $this->helper();
        $session = $this->supportSession($helper);
        $session->update(['session_status' => 'completed', 'end_time' => now()]);
        $report = SessionReport::create(['session_id' => $session->id, 'session_summary' => 'Summary', 'personal_reflection' => 'Reflection']);
        \App\Models\Message::create(['session_id' => $session->id, 'sender_id' => $helper->user_account_id, 'sender' => 'helper', 'message_text' => 'Used grounding for anxiety.', 'transcript' => 'Used grounding for anxiety.', 'is_transcript' => true, 'sent_datetime' => now()->subMinutes(3)]);
        \App\Models\Message::create(['session_id' => $session->id, 'sender_id' => $session->seeker->user_account_id, 'sender' => 'seeker', 'message_text' => 'That helped me feel calmer.', 'transcript' => 'That helped me feel calmer.', 'is_transcript' => true, 'sent_datetime' => now()->subMinute()]);

        $this->actingAs($helper->adviser->user)->get(route('adviser.evaluate', $report->id))->assertOk()
            ->assertDontSee('Used grounding for anxiety.')
            ->assertDontSee('That helped me feel calmer.')
            ->assertSee($session->seeker->generated_alias);
        $this->assertDatabaseHas('audit_logs', ['action' => 'session_documentation_viewed', 'target_id' => $report->id]);
    }

    public function test_cutoff_and_declared_conflicts_prevent_matching(): void
    {
        $helper = $this->helper();
        $session = $this->supportSession($helper, 'waiting');
        $session->update(['helper_id' => null]);
        $this->assertTrue(app(HelperEligibilityService::class)->allows($helper, $session));
        DB::table('helper_conflicts')->insert(['helper_id' => $helper->id, 'seeker_id' => $session->seeker_id, 'reported_by' => $helper->user_account_id, 'created_at' => now()]);
        $this->assertFalse(app(HelperEligibilityService::class)->allows($helper, $session));
        $this->travelTo(Carbon::parse('2026-09-16 22:30', 'Asia/Manila')->utc());
        $this->assertContains('Service is closed for new assignments.', app(HelperEligibilityService::class)->reasons($helper));
    }

    public function test_helper_observation_can_be_reviewed_without_reopening_closed_session(): void
    {
        $helper = $this->helper();
        $session = $this->supportSession($helper);
        $session->update(['session_status' => 'completed', 'end_time' => now()]);
        $this->post(route('helper.session.notes.store', $session->id), $this->summary(['risk_level_assessed' => 'moderate']))->assertSessionHasNoErrors();
        $this->assertSame('low', $session->fresh()->risk_level);
        $this->actingAs($helper->adviser->user)->post(route('adviser.screenings.review', $session), ['use_clarified_answers'=>true,'answers'=>array_merge(array_fill_keys(\App\Services\CompactScreening::FIELDS,0),['difficulty_coping'=>1]),'risk_level' => 'moderate', 'reason' => 'Reviewed helper observations.', 'evidence_source' => 'Session summary and adviser clarification.', 'allow_peer_support' => 1])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('moderate', $session->fresh()->risk_level);
        $this->assertSame('completed', $session->fresh()->session_status);
        $this->assertNotNull($session->report()->first()->reassessment_reviewed_at);
    }

    public function test_helper_cannot_export_transcripts_or_bypass_verification_via_inactive_account(): void
    {
        $helper = $this->helper();
        $session = $this->supportSession($helper);
        $this->get(route('chat.transcript', $session->id))->assertForbidden();
        $this->get(route('api.transcript.download', $session->id))->assertForbidden();
        $helper->user->update(['is_active' => false]);
        $this->actingAs($helper->user->fresh())->get(route('helper.dashboard'))->assertForbidden();
        $this->assertFalse(app(HelperEligibilityService::class)->allows($helper->fresh()));
    }

    public function test_shift_capacity_ignores_sessions_started_outside_the_shift_window(): void
    {
        $helper = $this->helper();
        $seekerUser = User::factory()->create(['role' => 'seeker']);
        $seeker = HelpSeeker::create(['user_account_id' => $seekerUser->id, 'generated_alias' => 'Capacity' . $seekerUser->id, 'age' => 20, 'gender' => 'male']);
        $make = fn (int $hour) => Session::create(['seeker_id' => $seeker->id, 'helper_id' => $helper->id, 'session_status' => 'completed', 'start_time' => Carbon::parse(sprintf('2026-09-16 %02d:00', $hour), 'Asia/Manila')->utc(), 'end_time' => Carbon::parse(sprintf('2026-09-16 %02d:30', $hour), 'Asia/Manila')->utc(), 'completion_status' => 'completed']);

        $make(10);
        $make(11);
        $this->assertTrue($helper->fresh()->hasCapacity(), 'Sessions before the 18:00-23:00 shift must not count against it.');

        $make(18);
        $make(19);
        $this->assertFalse($helper->fresh()->hasCapacity(), 'Two sessions inside the shift must exhaust capacity.');
    }

    public function test_readiness_before_shift_is_activated_and_reconciled_at_shift_start(): void
    {
        $this->travelTo(Carbon::parse('2026-09-16 17:00', 'Asia/Manila')->utc());
        $user = User::factory()->create(['role' => 'helper', 'is_active' => true]);
        $helper = Helper::create(['user_account_id' => $user->id, 'first_name' => 'Ahead', 'last_name' => 'Helper', 'email' => $user->email, 'status' => 'offline', 'availability' => 'unavailable', 'competency_level' => 3, 'competency_risk_level' => 3]);
        $this->verifiedHelperFixture($helper);
        HelperSchedule::create(['helper_id' => $helper->id, 'date' => now('Asia/Manila')->toDateString(), 'shift_start' => '18:00', 'shift_end' => '23:00', 'created_by' => $user->id, 'is_active' => true]);

        app(HelperReadinessService::class)->submit($user, $this->readiness());
        $helper = $helper->fresh();
        $this->assertSame('available', $helper->availability, 'Willing helpers declare availability before the shift.');
        $this->assertNotSame('available', $helper->status, 'They must not be assignable until on duty.');

        $this->travelTo(Carbon::parse('2026-09-16 18:30', 'Asia/Manila')->utc());
        $this->assertTrue(app(HelperWorkflowMaintenance::class)->reconcileHelperAvailability($helper->fresh()));
        $this->assertSame('available', $helper->fresh()->status, 'Shift-start reconcile flips a ready helper available.');
    }
}
