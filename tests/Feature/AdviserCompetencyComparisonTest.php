<?php

namespace Tests\Feature;

use App\Models\{Adviser, Helper, HelperCompetencyHistory, HelpSeeker, Notification, Session, SessionReport, TrainingRecommendation, User};
use App\Services\{AdviserCompetencyComparison, AdviserEvaluationService, AnalyticsPeriod, CompetencyRubric, TrainingRecommendationService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdviserCompetencyComparisonTest extends TestCase
{
    use RefreshDatabase;

    private User $adviserUser;
    private Adviser $adviser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-19 12:00:00', 'Asia/Manila'));
        $this->adviserUser = User::factory()->create(['role' => 'adviser', 'is_active' => true]);
        $this->adviser = Adviser::create(['user_account_id' => $this->adviserUser->id, 'first_name' => 'Assigned', 'last_name' => 'Adviser', 'email' => $this->adviserUser->email]);
        $this->actingAs($this->adviserUser);
    }

    private function helper(string $name = 'Sample'): Helper
    {
        $user = User::factory()->create(['role' => 'helper']);
        return Helper::create(['user_account_id' => $user->id, 'adviser_id' => $this->adviser->id,
            'first_name' => $name, 'last_name' => 'Helper', 'email' => $user->email]);
    }

    private function evaluate(Helper $helper, array $scores = []): HelperCompetencyHistory
    {
        $seekerUser = User::factory()->create(['role' => 'seeker']);
        $seeker = HelpSeeker::create(['user_account_id' => $seekerUser->id, 'generated_alias' => 'PrivateSeeker'.$seekerUser->id, 'age' => 20, 'gender' => 'prefer-not-to-say']);
        $session = Session::create(['helper_id' => $helper->id, 'seeker_id' => $seeker->id, 'session_type' => 'chat',
            'session_status' => 'completed', 'risk_level' => 'low', 'start_time' => now()->subHour(), 'end_time' => now()->subMinutes(10)]);
        $report = SessionReport::create(['session_id' => $session->id, 'session_summary' => 'Documented evaluation evidence.', 'personal_reflection' => 'Reflection on skills used.']);
        return app(AdviserEvaluationService::class)->save($report,
            $scores + array_fill_keys(array_keys(CompetencyRubric::CRITERIA), 4) + ['recommended_action' => 'Follow up']);
    }

    private function comparison(array $filters = []): array
    {
        $request = Request::create('/adviser/dashboard', 'GET', $filters + ['period' => 'today']);
        return app(AdviserCompetencyComparison::class)->forAdviser($this->adviser->id, AnalyticsPeriod::fromRequest($request), $request);
    }

    public function test_weighted_comparison_uses_finalized_results_and_corrections_once(): void
    {
        $helper = $this->helper();
        $scores = ['active_listening' => 5, 'empathy' => 4, 'respect_professionalism' => 3, 'ethical_practices' => 2, 'referral_accuracy' => 1];
        $first = $this->evaluate($helper, $scores);
        $this->evaluate($helper, array_fill_keys(array_keys(CompetencyRubric::CRITERIA), 5));
        $row = $this->comparison()['rows']->items()[0];
        $this->assertSame(4.18, $row['overall']);
        $this->assertSame(2, $row['count']);
        $this->assertSame('referral_accuracy', $row['weakest']);
        $this->assertSame(3.0, $row['scores']['referral_accuracy']);

        app(AdviserEvaluationService::class)->save(SessionReport::findOrFail($first->report_id),
            ['active_listening' => 4, 'correction_reason' => 'Correcting the documented listening rating.'] + $scores + ['recommended_action' => 'Follow up']);
        $row = $this->comparison()['rows']->items()[0];
        $this->assertSame(2, $row['count']);
        $this->assertSame(4.05, $row['overall']);
        $this->assertSame($first->id, $row['training_evaluation']->id);
    }

    public function test_strong_overall_does_not_hide_a_weak_skill_and_no_evaluation_is_not_zero(): void
    {
        $helper = $this->helper('Strong overall');
        $this->evaluate($helper, ['referral_accuracy' => 1] + array_fill_keys(array_keys(CompetencyRubric::CRITERIA), 5));
        $empty = $this->helper('No evaluation');
        $data = $this->comparison();
        $this->assertSame(1, $data['needs_attention']);
        $this->assertSame(1, $data['not_evaluated']);
        $this->assertSame(4.6, $data['rows']->items()[0]['overall']);
        $this->assertTrue($data['rows']->items()[0]['needs_attention']);
        $this->assertNull(collect($data['rows']->items())->firstWhere('helper.id', $empty->id)['score']);
        $this->get(route('adviser.dashboard', ['period' => 'today']))->assertOk()
            ->assertSee('Training review suggested')->assertSee('Based on 1 evaluation')
            ->assertSee('4.60/5')->assertSee('Not evaluated')->assertSee('View skill breakdown and evaluations')
            ->assertSee('width:92%', false)->assertDontSee('PrivateSeeker');
    }

    public function test_legacy_incomplete_future_and_out_of_period_records_are_separate_from_comparable_scores(): void
    {
        $helper = $this->helper();
        $this->evaluate($helper);
        HelperCompetencyHistory::create(['helper_id' => $helper->id, 'adviser_id' => $this->adviser->id, 'overall_score' => 82, 'evaluation_date' => now()]);
        $incomplete = $this->evaluate($helper);
        $incomplete->forceFill(['empathy_score' => null])->save();
        $unreviewed = $this->evaluate($helper);
        SessionReport::findOrFail($unreviewed->report_id)->update(['adviser_reviewed' => false]);
        $this->evaluate($helper)->forceFill(['evaluation_date' => now()->addMinute()])->save();
        $this->evaluate($helper)->forceFill(['evaluation_date' => now()->subDay()])->save();
        $wrongScore = $this->evaluate($helper);
        $wrongScore->forceFill(['overall_score' => 4.9])->save();
        $data = $this->comparison();
        $this->assertSame(1, $data['legacy_count']);
        $this->assertSame(1, $data['rows']->items()[0]['count']);
        $this->assertSame(4.0, $data['rows']->items()[0]['score']);
    }

    public function test_criterion_filters_sorting_pagination_and_duplicate_names_preserve_each_helper(): void
    {
        $high = $this->helper('Same name');
        $low = $this->helper('Same name');
        $this->evaluate($high, ['empathy' => 5]);
        $this->evaluate($low, ['empathy' => 2]);
        for ($i = 0; $i < 7; $i++) $this->helper('Unevaluated '.$i);
        $data = $this->comparison(['competency_skill' => 'empathy']);
        $this->assertSame($low->id, $data['rows']->items()[0]['helper']->id);
        $this->assertSame(2.0, $data['rows']->items()[0]['score']);
        $this->assertSame(9, $data['rows']->total());
        $this->assertCount(8, $data['rows']->items());
        $this->assertStringContainsString('competency_skill=empathy', $data['rows']->nextPageUrl());
        $this->assertStringContainsString('#competency-comparison', $data['rows']->nextPageUrl());
        $this->assertSame($high->id, $this->comparison(['competency_skill' => 'empathy', 'competency_sort' => 'highest'])['rows']->items()[0]['helper']->id);
        $this->assertCount(1, $this->comparison(['competency_page' => 2])['rows']->items());
        $this->getJson(route('adviser.dashboard', ['competency_skill' => 'unknown']))->assertUnprocessable();
        $this->getJson(route('adviser.dashboard', ['competency_page' => -1]))->assertUnprocessable();
    }

    public function test_manila_date_boundaries_and_unrelated_helper_scope_are_enforced(): void
    {
        $helper = $this->helper();
        $this->evaluate($helper)->forceFill(['evaluation_date' => Carbon::parse('2026-10-19 00:00:00', 'Asia/Manila')->utc()])->save();
        $this->evaluate($helper)->forceFill(['evaluation_date' => Carbon::parse('2026-10-18 23:59:59', 'Asia/Manila')->utc()])->save();
        $foreign = $this->helper('Foreign private');
        $foreignEval = $this->evaluate($foreign);
        $other = User::factory()->create(['role' => 'adviser']);
        $otherAdviser = Adviser::create(['user_account_id' => $other->id, 'first_name' => 'Other', 'last_name' => 'Adviser', 'email' => $other->email]);
        $foreign->update(['adviser_id' => $otherAdviser->id]);
        $data = $this->comparison();
        $this->assertSame(1, $data['rows']->total());
        $this->assertSame(1, $data['rows']->items()[0]['count']);
        $this->get(route('adviser.dashboard'))->assertOk()->assertDontSee('Foreign private');
        $this->get(route('adviser.training', ['evaluation_id' => $foreignEval->id]))->assertNotFound();
        $this->get(route('adviser.training', ['helper_id' => $foreign->id]))->assertNotFound();
    }

    public function test_training_link_preselects_the_helper_evidence_and_skill_and_preserves_input_on_error(): void
    {
        $helper = $this->helper();
        $evaluation = $this->evaluate($helper, ['referral_accuracy' => 1]);
        $url = route('adviser.training', ['helper_id' => $helper->id, 'evaluation_id' => $evaluation->id, 'criterion' => 'referral_accuracy']);
        $this->get($url)->assertOk()->assertViewHas('selectedEvaluation', fn ($e) => $e->id === $evaluation->id)
            ->assertViewHas('selectedCriterion', 'referral_accuracy')->assertViewHas('evaluations', fn ($rows) => $rows->count() === 1);
        $this->from($url)->post(route('adviser.training.store'), ['evaluation_id' => $evaluation->id, 'criterion' => 'referral_accuracy',
            'reason' => 'Training evidence entered by the Adviser.', 'activity' => '', 'priority' => 'high'])
            ->assertRedirect($url)->assertSessionHasErrors('activity');
        $this->get($url)->assertOk()->assertSee('Training evidence entered by the Adviser.');
    }

    public function test_training_duplicate_guard_notification_and_completion_review_flow(): void
    {
        $helper = $this->helper();
        $first = $this->evaluate($helper, ['referral_accuracy' => 1]);
        $second = $this->evaluate($helper, ['referral_accuracy' => 2]);
        $data = ['evaluation_id' => $first->id, 'criterion' => 'referral_accuracy', 'reason' => 'Review the documented referral judgment gap.',
            'activity' => 'Review referral protocol with the Adviser.', 'priority' => 'normal'];
        $this->post(route('adviser.training.store'), $data)->assertRedirect()->assertSessionHasNoErrors();
        $task = TrainingRecommendation::firstOrFail();
        $this->assertTrue($this->comparison()['rows']->items()[0]['has_training']);
        $this->get(route('adviser.dashboard'))->assertOk()->assertSee('Review existing training for Referral Judgment');
        $this->post(route('adviser.training.store'), ['evaluation_id' => $second->id] + $data)->assertSessionHasErrors('criterion');
        $this->assertDatabaseCount('training_recommendations', 1);
        $this->assertSame(1, Notification::where('title', 'Training recommendation assigned')->where('user_account_id', $helper->user_account_id)->count());
        $this->actingAs($helper->user)->get(route('helper.training'))->assertOk()->assertSee('Acknowledge and start')->assertDontSee('Assign a learning activity');
        $this->patch(route('helper.training.update', $task), ['status' => 'in_progress'])->assertRedirect();
        $this->patch(route('helper.training.update', $task), ['status' => 'completed', 'completion_evidence' => 'Reviewed protocol and discussed referral examples.'])->assertRedirect();
        $this->assertNull($task->fresh()->reviewed_at);
        $this->assertTrue($this->comparison()['rows']->items()[0]['has_training']);
        $this->actingAs($this->adviserUser)->patch(route('adviser.training.update', $task), ['status' => 'reviewed', 'review_notes' => 'Completion evidence reviewed with the Helper.'])->assertRedirect();
        $this->assertFalse($this->comparison()['rows']->items()[0]['has_training']);
        $this->assertSame(2, Notification::where('title', 'Training recommendation updated')->where('user_account_id', $this->adviserUser->id)->count());
    }
}
