<?php

namespace Tests\Feature;

use App\Models\Adviser;
use App\Models\Helper;
use App\Models\HelperCompetencyHistory;
use App\Models\HelpSeeker;
use App\Models\PsychologyProfessional;
use App\Models\Referral;
use App\Models\Session;
use App\Models\SessionReport;
use App\Models\User;
use App\Services\AdviserEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The adviser evaluation queue previously listed session reports for sessions
 * that were still running, even though the evaluation action only
 * accept concluded sessions. Advisers therefore clicked through to a dead end,
 * and a fully handled queue looked identical to a broken one.
 */
class AdviserEvaluationQueueTest extends TestCase
{
    use RefreshDatabase;

    private function makeReport(string $sessionStatus, bool $documented = true, ?Helper $helper = null, ?User $adviserUser = null): array
    {
        if (! $adviserUser) {
            $adviserUser = User::factory()->create(['role' => 'adviser', 'is_active' => true]);
            Adviser::create([
                'user_account_id' => $adviserUser->id,
                'first_name' => 'Demo',
                'last_name' => 'Adviser',
                'email' => $adviserUser->email,
            ]);
        }

        if (! $helper) {
            $helperUser = User::factory()->create(['role' => 'helper', 'is_active' => true]);
            $helper = Helper::create([
                'user_account_id' => $helperUser->id,
                'adviser_id' => Adviser::where('user_account_id', $adviserUser->id)->value('id'),
                'first_name' => 'Demo',
                'last_name' => 'Helper',
                'email' => $helperUser->email,
            ]);
        }

        $seekerUser = User::factory()->create(['role' => 'seeker', 'is_active' => true]);
        $seeker = HelpSeeker::create([
            'user_account_id' => $seekerUser->id,
            'generated_alias' => 'EvalSeeker'.$seekerUser->id,
            'age' => 20,
            'gender' => 'prefer-not-to-say',
        ]);
        $session = Session::create([
            'seeker_id' => $seeker->id,
            'helper_id' => $helper->id,
            'session_type' => 'chat',
            'session_status' => $sessionStatus,
            'risk_level' => 'low',
            'created_date' => now()->subHour(),
            'start_time' => now()->subMinutes(50),
            'end_time' => $sessionStatus === Session::STATUS_ACTIVE ? null : now()->subMinutes(10),
        ]);
        $report = SessionReport::create([
            'session_id' => $session->id,
            'session_summary' => $documented ? 'Evidence of listening and clarification.' : null,
            'personal_reflection' => $documented ? 'Improve how questions are summarized.' : null,
        ]);

        return [$adviserUser, $helper, $session, $report];
    }

    private function scores(): array
    {
        return [
            'active_listening' => 5,
            'empathy' => 4,
            'respect_professionalism' => 3,
            'ethical_practices' => 2,
            'referral_accuracy' => 1,
            'recommended_action' => 'Follow up',
            'strengths' => 'Listening',
            'improvement_areas' => 'Clarification',
        ];
    }

    private function assertSidebarCount($response, string $id, ?int $count): void
    {
        $pattern = '/id="'.preg_quote($id, '/').'"[^>]*>\s*(\d+)\s*</';
        $matched = preg_match($pattern, $response->getContent(), $matches);
        if ($count === null) {
            $this->assertSame(0, $matched, "Unexpected sidebar badge: {$id}");
        } else {
            $this->assertSame(1, $matched, "Missing sidebar count: {$id}");
            $this->assertSame($count, (int) $matches[1]);
        }
    }

    public function test_sidebar_separates_screening_work_from_evaluable_reports_and_updates_after_evaluation(): void
    {
        [$user, $helper, $session, $report] = $this->makeReport(Session::STATUS_COMPLETED);
        [, , , $reflectionOnly] = $this->makeReport(Session::STATUS_EVALUATED, helper: $helper, adviserUser: $user);
        $reflectionOnly->update(['session_summary' => '   ']);
        $this->makeReport(Session::STATUS_ACTIVE, helper: $helper, adviserUser: $user);
        $this->makeReport(Session::STATUS_COMPLETED, false, $helper, $user);
        $this->makeReport(Session::STATUS_CANCELLED, helper: $helper, adviserUser: $user);
        [, , , $reviewed] = $this->makeReport(Session::STATUS_COMPLETED, helper: $helper, adviserUser: $user);
        $reviewed->update(['adviser_reviewed' => true]);
        $this->makeReport(Session::STATUS_COMPLETED); // Another Adviser's pending report.
        Session::create(['seeker_id' => $session->seeker_id, 'session_type' => 'chat', 'session_status' => 'pending_review',
            'workflow_state' => 'adviser_review_required', 'requires_adviser_review' => true, 'review_adviser_id' => $user->adviser->id]);

        $response = $this->actingAs($user)->get(route('adviser.evaluations'))->assertOk()
            ->assertViewHas('totalPending', 2)->assertViewHas('pendingReports', fn ($rows) => $rows->total() === 2);
        $this->assertSidebarCount($response, 'evalBadge', 2);
        $this->assertSidebarCount($response, 'pendingReviewsBadge', 1);
        $this->assertSidebarCount($response, 'pendingReviews', 2);

        $this->post(route('adviser.evaluate.store', $report->id), $this->scores())->assertRedirect(route('adviser.evaluations'));
        $updated = $this->get(route('adviser.evaluations'))->assertOk()->assertViewHas('totalPending', 1);
        $this->assertSidebarCount($updated, 'evalBadge', 1);
        $this->assertSidebarCount($updated, 'pendingReviewsBadge', 1);
        $this->assertSidebarCount($updated, 'pendingReviews', 1);
    }

    public function test_evaluation_badge_counts_all_pages_and_disappears_when_no_evaluable_reports_remain(): void
    {
        [$user, $helper] = $this->makeReport(Session::STATUS_COMPLETED);
        for ($i = 0; $i < 16; $i++) {
            $this->makeReport(Session::STATUS_COMPLETED, helper: $helper, adviserUser: $user);
        }
        $response = $this->actingAs($user)->get(route('adviser.evaluations'))->assertOk()
            ->assertViewHas('pendingReports', fn ($rows) => $rows->count() === 15 && $rows->total() === 17);
        $this->assertSidebarCount($response, 'evalBadge', 17);
        $this->assertSidebarCount($response, 'pendingReviewsBadge', null);
        $second = $this->get(route('adviser.evaluations', ['pending_page' => 2]))->assertOk();
        $this->assertSidebarCount($second, 'evalBadge', 17);

        SessionReport::whereHas('session', fn ($q) => $q->where('helper_id', $helper->id))->update(['adviser_reviewed' => true]);
        $empty = $this->get(route('adviser.evaluations'))->assertOk()->assertViewHas('totalPending', 0);
        $this->assertSidebarCount($empty, 'evalBadge', null);
        $this->assertSidebarCount($empty, 'pendingReviews', 0);
    }

    public function test_screening_badge_matches_distinct_pending_sessions_including_reassessment(): void
    {
        [$user, , $session, $report] = $this->makeReport(Session::STATUS_COMPLETED);
        $attributes = ['seeker_id' => $session->seeker_id, 'session_type' => 'chat', 'session_status' => 'pending_review',
            'workflow_state' => 'adviser_review_required', 'requires_adviser_review' => true, 'review_adviser_id' => $user->adviser->id];
        $pending = Session::create($attributes);
        foreach (range(1, 2) as $i) {
            \App\Models\ScreeningResponse::create(['session_id' => $pending->id, 'seeker_id' => $pending->seeker_id,
                'responses' => [], 'risk_level' => 'low', 'priority' => 4, 'action' => 'peer_support', 'review_status' => 'pending']);
        }
        Session::create(array_replace($attributes, ['session_status' => 'cancelled']));
        Session::create(array_replace($attributes, ['requires_adviser_review' => false]));
        $alreadyReviewed = Session::create($attributes);
        \App\Models\ScreeningResponse::create(['session_id' => $alreadyReviewed->id, 'seeker_id' => $session->seeker_id,
            'responses' => [], 'risk_level' => 'low', 'priority' => 4, 'action' => 'peer_support', 'review_status' => 'reviewed']);
        $session->update(['requires_adviser_review' => true, 'review_adviser_id' => $user->adviser->id]);
        $report->update(['reassessment_requested_at' => now()]);

        $response = $this->actingAs($user)->get(route('adviser.screenings'))->assertOk()
            ->assertViewHas('sessions', fn ($rows) => $rows->count() === 2 && $rows->contains('id', $pending->id) && $rows->contains('id', $session->id));
        $this->assertSidebarCount($response, 'pendingReviewsBadge', 2);
        $report->update(['reassessment_reviewed_at' => now()]);
        $updated = $this->get(route('adviser.screenings'))->assertOk()->assertViewHas('sessions', fn ($rows) => $rows->count() === 1);
        $this->assertSidebarCount($updated, 'pendingReviewsBadge', 1);
    }

    public function test_queue_only_offers_sessions_that_can_actually_be_evaluated(): void
    {
        [$adviserUser, $helper, , $concludedReport] = $this->makeReport(Session::STATUS_COMPLETED);
        [, , , $secondConcluded] = $this->makeReport(Session::STATUS_COMPLETED, helper: $helper, adviserUser: $adviserUser);
        // Same adviser and helper, but this session has not concluded yet.
        [, , , $runningReport] = $this->makeReport(Session::STATUS_ACTIVE, helper: $helper, adviserUser: $adviserUser);

        $concludedAlias = $concludedReport->session->seeker->generated_alias;
        $secondAlias = $secondConcluded->session->seeker->generated_alias;
        $runningAlias = $runningReport->session->seeker->generated_alias;

        $this->actingAs($adviserUser)
            ->get(route('adviser.evaluations'))
            ->assertOk()
            ->assertSee($concludedAlias)
            ->assertSee($secondAlias)
            // A live session is not evaluable, so it must not be offered.
            ->assertDontSee($runningAlias);

        $this->assertFalse((bool) $concludedReport->fresh()->adviser_reviewed);
    }

    public function test_concluded_sessions_appear_in_the_queue(): void
    {
        [$adviserUser, , , $report] = $this->makeReport(Session::STATUS_COMPLETED);

        $this->actingAs($adviserUser)
            ->get(route('adviser.evaluations'))
            ->assertOk()
            ->assertSee('Pending Evaluations');

        $this->assertFalse((bool) $report->fresh()->adviser_reviewed);
    }

    public function test_empty_queue_explains_that_reports_are_withheld(): void
    {
        [$adviserUser] = $this->makeReport(Session::STATUS_ACTIVE);

        $this->actingAs($adviserUser)
            ->get(route('adviser.evaluations'))
            ->assertOk()
            ->assertSee('withheld')
            ->assertSee('until the session is completed or evaluated');
    }

    public function test_undocumented_reports_are_counted_not_listed(): void
    {
        [$adviserUser, , , $report] = $this->makeReport(Session::STATUS_COMPLETED, false);

        // The queue must not offer a report that can only fail the evidence check.
        $this->actingAs($adviserUser)
            ->get(route('adviser.evaluations'))
            ->assertOk()
            ->assertDontSee(route('adviser.evaluate', $report->id))
            ->assertSee('awaiting')
            ->assertSee('session documentation from the Helper');

        $this->actingAs($adviserUser)
            ->post(route('adviser.evaluate.store', $report->id), $this->scores())
            ->assertStatus(422);
    }

    public function test_repeat_submission_reports_that_nothing_changed(): void
    {
        [$adviserUser, , , $report] = $this->makeReport(Session::STATUS_COMPLETED);

        $this->actingAs($adviserUser)
            ->post(route('adviser.evaluate.store', $report->id), $this->scores())
            ->assertRedirect(route('adviser.evaluations'))
            ->assertSessionHas('success');

        // A duplicate submission must not claim a fresh evaluation was saved.
        $this->actingAs($adviserUser)
            ->post(route('adviser.evaluate.store', $report->id), $this->scores())
            ->assertRedirect(route('adviser.evaluations'))
            ->assertSessionHas('info')
            ->assertSessionMissing('success');

        $this->assertSame(1, HelperCompetencyHistory::where('report_id', $report->id)->count());
        $this->assertTrue((bool) $report->fresh()->adviser_reviewed);
    }

    public function test_correction_reason_still_versions_the_evaluation(): void
    {
        [$adviserUser, , , $report] = $this->makeReport(Session::STATUS_COMPLETED);
        $service = app(AdviserEvaluationService::class);

        $this->actingAs($adviserUser)->post(route('adviser.evaluate.store', $report->id), $this->scores());

        $this->actingAs($adviserUser)
            ->post(route('adviser.evaluate.store', $report->id), array_merge($this->scores(), [
                'active_listening' => 4,
                'correction_reason' => 'Correcting the documented listening rating.',
            ]))
            ->assertRedirect(route('adviser.evaluations'))
            ->assertSessionHas('success');

        $this->assertSame(1, HelperCompetencyHistory::where('report_id', $report->id)->count());
        $this->assertFalse($service->lastSaveWasUnchanged);
        $this->assertSame(4, (int) HelperCompetencyHistory::where('report_id', $report->id)->value('active_listening_score'));
    }

    public function test_adviser_account_without_a_profile_is_rejected(): void
    {
        $orphan = User::factory()->create(['role' => 'adviser', 'is_active' => true]);

        $this->actingAs($orphan)->get(route('adviser.evaluations'))->assertForbidden();
    }

    public function test_still_running_sessions_cannot_be_evaluated(): void
    {
        [$adviserUser, , , $report] = $this->makeReport(Session::STATUS_ACTIVE);

        $this->actingAs($adviserUser)
            ->post(route('adviser.evaluate.store', $report->id), $this->scores())
            ->assertStatus(409);

        $this->assertFalse((bool) $report->fresh()->adviser_reviewed);
    }

    public function test_every_referral_status_is_accepted_by_the_reports_filter(): void
    {
        [$adviserUser] = $this->makeReport(Session::STATUS_COMPLETED);

        // The dropdown and the validator each kept their own hardcoded list,
        // so a newly added status was unfilterable and failed validation.
        foreach (Referral::STATUSES as $status) {
            $this->actingAs($adviserUser)
                ->get(route('adviser.reports', ['referral_status' => $status]))
                ->assertOk();
        }

        $this->actingAs($adviserUser)
            ->get(route('adviser.reports', ['referral_status' => 'not_a_status']))
            ->assertSessionHasErrors('referral_status');
    }

    public function test_concluded_referrals_stay_within_the_professional_read_scope(): void
    {
        [$adviserUser, $helper, $session] = $this->makeReport(Session::STATUS_COMPLETED);
        $adviser = Adviser::where('user_account_id', $adviserUser->id)->firstOrFail();
        $professional = PsychologyProfessional::create([
            'user_account_id' => User::factory()->create(['role' => 'professional', 'is_active' => true])->id,
            'first_name' => 'Pro',
            'last_name' => 'One',
            'email' => 'pro.scope@example.com',
            'is_available' => true,
        ]);

        $base = [
            'session_id' => $session->id,
            'helper_id' => $helper->id,
            'adviser_id' => $adviser->id,
            'professional_id' => $professional->id,
            'approved_at' => now(),
            'help_seeker_consent' => true,
            'priority_level' => Referral::PRIORITY_LOW,
            'referral_reason' => 'Concluded professional support.',
            'referral_date' => now(),
        ];

        $closed = Referral::create($base + ['status' => Referral::STATUS_CLOSED]);
        $declined = Referral::create($base + ['status' => Referral::STATUS_DECLINED]);

        // A professional could still write to a case after it closed, so the
        // read scope must not drop completed and closed work either.
        $visible = Referral::professionalAuthorized()->pluck('id')->all();
        $this->assertContains($closed->id, $visible);
        $this->assertNotContains($declined->id, $visible);
    }

    public function test_pending_queue_requires_evaluation_and_has_no_mark_reviewed_shortcuts(): void
    {
        [$user, , , $report] = $this->makeReport(Session::STATUS_COMPLETED);
        $this->actingAs($user)->get(route('adviser.evaluations'))
            ->assertOk()->assertSee('Evaluate')->assertSee('View Session')
            ->assertDontSee('Mark as reviewed')->assertDontSee('Select all on this page')
            ->assertDontSee('bulkCompleteForm')->assertDontSee('pending-check')
            ->assertDontSee('btn-skip');
        $this->assertFalse((bool) $report->fresh()->adviser_reviewed);
        $this->assertNull($report->fresh()->reviewed_date);
    }

    public function test_removed_review_shortcut_endpoints_cannot_change_report_state(): void
    {
        [$user, , , $report] = $this->makeReport(Session::STATUS_COMPLETED);
        $this->actingAs($user)
            ->post('/adviser/evaluations/bulk-complete', ['report_ids'=>[$report->id]])->assertNotFound();
        $this->post('/adviser/evaluations/'.$report->id.'/skip', ['review_note'=>'Old shortcut request'])
            ->assertNotFound();
        $this->assertFalse((bool) $report->fresh()->adviser_reviewed);
        $this->assertNull($report->fresh()->reviewed_date);
        $this->assertDatabaseMissing('audit_logs', ['action'=>'documentation_reviewed_in_bulk']);
        $this->assertDatabaseMissing('audit_logs', ['action'=>'documentation_reviewed_without_new_score']);
        $this->assertDatabaseCount('helper_competency_history', 0);
    }

    public function test_review_is_completed_only_after_a_valid_evaluation_is_submitted(): void
    {
        [$user, $helper, $session, $report] = $this->makeReport(Session::STATUS_COMPLETED);
        $this->actingAs($user)->post(route('adviser.evaluate.store', $report->id), [])
            ->assertSessionHasErrors('active_listening');
        $this->assertFalse((bool) $report->fresh()->adviser_reviewed);
        $this->post(route('adviser.evaluate.store', $report->id), $this->scores())
            ->assertRedirect(route('adviser.evaluations'))->assertSessionHas('success');
        $this->assertTrue((bool) $report->fresh()->adviser_reviewed);
        $this->assertNotNull($report->fresh()->reviewed_date);
        $this->assertSame(Session::STATUS_EVALUATED, $session->fresh()->session_status);
        $this->assertDatabaseHas('helper_competency_history', ['report_id'=>$report->id,'helper_id'=>$helper->id]);
        $this->assertDatabaseHas('adviser_feedback', ['report_id'=>$report->id,'adviser_id'=>$user->adviser->id]);
    }
}
