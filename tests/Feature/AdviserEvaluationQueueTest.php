<?php

namespace Tests\Feature;

use App\Models\Adviser;
use App\Models\Helper;
use App\Models\HelperCompetencyHistory;
use App\Models\HelpSeeker;
use App\Models\Session;
use App\Models\SessionReport;
use App\Models\User;
use App\Services\AdviserEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The adviser evaluation queue previously listed session reports for sessions
 * that were still running, even though both the store and skip actions only
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
}
