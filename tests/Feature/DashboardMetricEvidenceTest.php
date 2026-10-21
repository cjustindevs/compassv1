<?php

namespace Tests\Feature;

use App\Models\{Adviser, Helper, HelpSeeker, HelpSeekerEvaluation, Message, ScreeningResponse, SelfHelpResource, Session, User, UserResourceProgress, VoiceCall};
use App\Services\{AnalyticsPeriod, CompactScreening, DashboardIndicators, SessionPerformance};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardMetricEvidenceTest extends TestCase
{
    use RefreshDatabase, \Tests\Concerns\SeekerWorkflowFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-10-22 20:00', 'Asia/Manila')->utc());
    }

    private function supportCase(array $attributes = []): Session
    {
        $user = User::factory()->create(['role'=>'seeker']);
        $seeker = HelpSeeker::create(['user_account_id'=>$user->id,'generated_alias'=>'Private'.$user->id]);
        $helperUser = User::factory()->create(['role'=>'helper']);
        $helper = Helper::create(['user_account_id'=>$helperUser->id,'first_name'=>'Evidence','last_name'=>'Helper','email'=>$helperUser->email]);
        return Session::create($attributes + ['seeker_id'=>$seeker->id,'helper_id'=>$helper->id,'session_status'=>'completed',
            'session_type'=>'chat','start_time'=>now()->subMinutes(40),'end_time'=>now()->subMinutes(10),'submitted_at'=>now()->subHour()]);
    }

    private function summarize(): array
    {
        return app(SessionPerformance::class)->summarize(Session::all(), Helper::pluck('id'),
            AnalyticsPeriod::fromRequest(Request::create('/', 'GET', ['period'=>'today'])));
    }

    public function test_first_response_uses_actual_interaction_after_start_including_connected_audio(): void
    {
        $case = $this->supportCase();
        foreach ([['Earlier', 45], ['   ', 39], ['Real response', 35], ['After end', 5]] as [$text,$minutes]) {
            Message::create(['session_id'=>$case->id,'sender_id'=>$case->helper->user_account_id,'message_text'=>$text,'sent_datetime'=>now()->subMinutes($minutes)]);
        }
        Message::create(['session_id'=>$case->id,'sender_id'=>$case->seeker->user_account_id,'message_text'=>'Seeker message','sent_datetime'=>now()->subMinutes(39)]);
        $call = VoiceCall::create(['id'=>(string) Str::uuid(),'session_id'=>$case->id,'caller_user_id'=>$case->helper->user_account_id,
            'callee_user_id'=>$case->seeker->user_account_id,'status'=>'connecting','accepted_at'=>now()->subMinutes(39)]);
        $this->assertSame(5.0, $this->summarize()['metrics']['response_minutes']);
        $call->update(['status'=>'ended','connected_at'=>now()->subMinutes(38)]);
        $this->assertSame(2.0, $this->summarize()['metrics']['response_minutes']);
    }

    public function test_helpfulness_uses_original_answers_and_excludes_legacy_composites_test_duplicates_and_cancelled(): void
    {
        foreach (['Helpful','Not Helpful',null,'Very Helpful','Very Helpful','Very Helpful'] as $i=>$answer) {
            $case = $this->supportCase($i === 5 ? ['session_status'=>'cancelled'] : []);
            if ($i === 3) $case->forceFill(['is_test_record'=>true])->save();
            if ($i === 4) $case->forceFill(['duplicate_of_id'=>1])->save();
            HelpSeekerEvaluation::create(['session_id'=>$case->id,'overall_score'=>10,'answers'=>$answer ? ['helpfulness_score'=>$answer] : [],'submitted_at'=>now()]);
        }
        $result = $this->summarize();
        $this->assertSame(50.0, $result['metrics']['helpfulness_rate']);
        $this->assertSame(2, $result['samples']['helpfulness_rate']);
        $this->assertSame(3, $result['metrics']['completed']);
    }

    public function test_event_dates_measure_starts_and_completions_independently(): void
    {
        $case = $this->supportCase(['start_time'=>now()->subDay(),'end_time'=>now()->subMinute()]);
        Message::create(['session_id'=>$case->id,'sender_id'=>$case->helper->user_account_id,'message_text'=>'Yesterday response','sent_datetime'=>$case->start_time->copy()->addMinute()]);
        $result = $this->summarize();
        $this->assertSame(1, $result['metrics']['completed']);
        $this->assertNull($result['metrics']['response_minutes']);
        $this->assertNull($result['metrics']['completion_rate']);
        $this->assertSame('—', SessionPerformance::number(null));
        $this->assertSame('0%', SessionPerformance::number(0, '%'));
    }

    public function test_numeric_seeker_cards_are_owned_and_live_tasks_ignore_historical_filter(): void
    {
        $case = $this->supportCase();
        $this->supportCase();
        $old = AnalyticsPeriod::fromRequest(Request::create('/', 'GET', ['from'=>'2026-10-01','to'=>'2026-10-02']));
        $cards = collect(app(DashboardIndicators::class)->seeker($case->seeker->user, $old))->keyBy('label');
        $this->assertSame(0.0, $cards['Completed sessions']['raw']);
        $this->assertSame(1.0, $cards['Feedback due']['raw']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(DashboardIndicators::class)->seeker($case->helper->user, $old);
    }

    public function test_parallel_intake_can_be_reviewed_without_replacing_answers_risk_or_queue_progress(): void
    {
        $case = $this->supportCase(['session_status'=>'waiting','start_time'=>null,'end_time'=>null,'workflow_state'=>'awaiting_helper_assignment',
            'risk_level'=>null,'requires_adviser_review'=>true]);
        $user = User::factory()->create(['role'=>'adviser']);
        $adviser = Adviser::create(['user_account_id'=>$user->id,'first_name'=>'Intake','last_name'=>'Adviser','email'=>$user->email]);
        $case->update(['review_adviser_id'=>$adviser->id]);
        $original = ScreeningResponse::create(['session_id'=>$case->id,'seeker_id'=>$case->seeker_id,
            'responses'=>['suicidal_thoughts'=>'prefer_not_to_say'],'instrument_version'=>CompactScreening::SINGLE_VERSION,
            'risk_level'=>null,'priority'=>2,'action'=>'adviser_review_required','review_status'=>'adviser_review_required']);
        $data = ['review_action'=>'complete_intake','reason'=>'Reviewed original intake under supervision.'];
        $this->actingAs($user)->post(route('adviser.screenings.review',$case), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('adviser.screenings.review',$case), $data)->assertRedirect();
        $this->assertFalse($case->fresh()->requires_adviser_review);
        $this->assertNull($case->fresh()->risk_level);
        $this->assertNull($case->fresh()->peer_support_approved_at);
        $this->assertSame('waiting',$case->fresh()->session_status);
        $this->assertSame('awaiting_helper_assignment',$case->fresh()->workflow_state);
        $this->assertSame('prefer_not_to_say',$original->fresh()->responses['suicidal_thoughts']);
        $this->assertDatabaseCount('screening_responses',2);
        $this->assertDatabaseCount('referrals',0);
        $this->actingAs(User::factory()->create(['role'=>'adviser']))->post(route('adviser.screenings.review',$case),$data)->assertForbidden();
    }

    public function test_opening_resource_is_not_completion_and_repeated_completion_keeps_first_timestamp(): void
    {
        $case = $this->supportCase();
        $user = $case->seeker->user;
        $this->consentFixture($user);
        $resource = SelfHelpResource::create(['title'=>'Read calmly','description'=>'A published guide','category'=>'article','content'=>'Read at your own pace.','is_published'=>true]);
        $resource->forceFill(['visibility'=>'public'])->save();
        $this->actingAs($user)->get(route('selfhelp.show',$resource->id))->assertOk()->assertSee('Mark as read')->assertDontSee('<input type="range"',false);
        $progress = UserResourceProgress::where('user_id',$user->id)->where('resource_id',$resource->id)->firstOrFail();
        $this->assertNull($progress->completed_at);
        $this->postJson(route('selfhelp.complete',$resource->id))->assertOk()->assertJsonPath('completed',true);
        $first = $progress->fresh()->completed_at->toIso8601String();
        $this->travel(2)->minutes();
        $this->postJson(route('selfhelp.complete',$resource->id))->assertOk();
        $this->assertSame($first,$progress->fresh()->completed_at->toIso8601String());
        $this->assertSame(100,$progress->fresh()->progress_percentage);
        $this->assertDatabaseCount('user_resource_progress',1);
    }
}
