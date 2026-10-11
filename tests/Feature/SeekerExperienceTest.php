<?php

namespace Tests\Feature;

use App\Models\{Adviser, ConcernCategory, HelpSeeker, HelpSeekerEvaluation, Helper, QueueRequest, ScreeningResponse, SeekerRequestDraft, Session, User};
use App\Services\{CompactScreening, ConsentService, EvaluationInstrument, SeekerWorkflowService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeekerExperienceTest extends TestCase
{
    use RefreshDatabase, \Tests\Concerns\SeekerWorkflowFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        // Optional syntax QA captures isolated fixture HTML, never live records.
        if ($directory = getenv('COMPASS_SEEKER_CAPTURE_DIR')) {
            \Illuminate\Support\Facades\Event::listen(\Illuminate\Foundation\Http\Events\RequestHandled::class, function ($event) use ($directory) {
                $name = $event->request->route()?->getName();
                if (!in_array($name, ['request.screening','request.preferences','request.matching','seeker.requests','seeker.requests.show','session.evaluation','session.thank-you'],true)) return;
                if ($event->response->getStatusCode() !== 200 || !str_contains($event->response->getContent(), '<html')) return;
                $view = $event->response->getOriginalContent();
                $state = $view instanceof \Illuminate\View\View ? ($view->getData()['session']->workflow_state ?? 'default') : 'default';
                file_put_contents($directory.'/'.$name.'-'.$state.'.html', $event->response->getContent());
            });
        }
    }

    private function seeker(bool $consent = true): User
    {
        $user = User::factory()->create(['role'=>'seeker','is_active'=>true]);
        HelpSeeker::create(['user_account_id'=>$user->id,'generated_alias'=>'CalmFox'.$user->id,'pseudo_id'=>'UX-'.$user->id,'age'=>20,'gender'=>'prefer-not-to-say']);
        if ($consent) $this->consentFixture($user);
        return $user;
    }

    private function answers(array $overrides = []): array
    {
        $concern = ConcernCategory::firstOrCreate(['concern_name'=>'Stress'], ['is_active'=>true]);
        return array_replace(array_fill_keys(CompactScreening::FIELDS, 'no'), [
            'screening_form_version'=>CompactScreening::FORM_VERSION,'concern_id'=>$concern->id,
        ], $overrides);
    }

    private function draft(array $overrides = []): array
    {
        return array_replace(['stage'=>'screening','instrument_version'=>CompactScreening::FORM_VERSION,'description'=>'Private unfinished concern'], $overrides);
    }

    private function singleAnswer(string $answer): array
    {
        $concern = ConcernCategory::firstOrCreate(['concern_name'=>'Stress'], ['is_active'=>true]);
        return ['screening_form_version'=>CompactScreening::SINGLE_VERSION,'concern_id'=>$concern->id,
            'description'=>'I would like peer support for academic stress.','safety_check'=>$answer];
    }

    public function test_single_no_answer_returns_to_preferences_and_enters_the_existing_queue(): void
    {
        $owner=$this->seeker(); $this->actingAs($owner);
        $this->post(route('request.screening.process'),$this->singleAnswer('no'))->assertRedirect(route('request.preferences'));
        $case=Session::firstOrFail(); $screen=ScreeningResponse::firstOrFail();
        $this->assertSame('low',$case->risk_level);
        $this->assertSame('single_safety_no',$screen->rule_code);
        $this->assertSame(CompactScreening::SINGLE_VERSION,$screen->instrument_version);
        $this->assertSame('no',$screen->responses['suicidal_thoughts']);
        foreach(array_diff(CompactScreening::FIELDS,['suicidal_thoughts']) as $field) $this->assertArrayNotHasKey($field,$screen->responses);
        $this->assertDatabaseCount('queue_requests',0);
        $this->get(route('request.preferences'))->assertOk()->assertSee('Find a Helper')->assertDontSee('Review your request');
        $this->post(route('request.preferences.process'),['support_mode'=>'chat','preferred_language'=>'Tagalog'])->assertRedirect(route('request.matching'));
        $this->assertDatabaseCount('queue_requests',1);
        $this->assertSame('queued',$case->fresh()->workflow_state);
        $this->assertSame('Tagalog',$case->fresh()->preferred_language);
        $this->post(route('request.preferences.process'),['support_mode'=>'chat','preferred_language'=>'Tagalog'])->assertRedirect();
        $this->assertDatabaseCount('queue_requests',1);
    }

    public function test_single_yes_does_not_invent_a_plan_or_automatically_escalate_an_emergency(): void
    {
        $this->actingAs($this->seeker())->post(route('request.screening.process'),$this->singleAnswer('yes'))->assertRedirect(route('request.matching'));
        $case=Session::firstOrFail(); $screen=ScreeningResponse::firstOrFail();
        $this->assertSame('high',$case->risk_level);
        $this->assertSame('adviser_review_required',$case->workflow_state);
        $this->assertTrue($case->requires_adviser_review);
        $this->assertFalse($case->permitsEmergencySupport());
        $this->assertSame('yes',$screen->responses['suicidal_thoughts']);
        $this->assertArrayNotHasKey('current_suicide_plan',$screen->responses);
        $this->assertDatabaseCount('queue_requests',0);
        $this->assertDatabaseCount('emergency_alerts',0);
    }

    public function test_single_unknown_answer_can_be_clarified_by_its_assigned_adviser(): void
    {
        $adviserUser=User::factory()->create(['role'=>'adviser','is_active'=>true]);
        $adviser=Adviser::create(['user_account_id'=>$adviserUser->id,'first_name'=>'Test','last_name'=>'Reviewer','email'=>$adviserUser->email]);
        $this->actingAs($this->seeker())->post(route('request.screening.process'),$this->singleAnswer('prefer_not_to_say'))->assertRedirect();
        $case=Session::firstOrFail(); $original=ScreeningResponse::firstOrFail();
        $this->assertNull($case->risk_level);
        $this->assertSame($adviser->id,$case->review_adviser_id);
        $this->assertTrue($case->requires_adviser_review);
        $this->actingAs($adviserUser)->get('/adviser/screenings')->assertOk()->assertSee('This intake asked one safety question.')->assertSee('use_clarified_answers');
        $this->post(route('adviser.screenings.review',$case),[
            'risk_level'=>'low','reason'=>'Clarified all support answers directly with the Seeker.','evidence_source'=>'Direct conversation',
            'allow_peer_support'=>1,'use_clarified_answers'=>1,'answers'=>array_fill_keys(CompactScreening::FIELDS,false),
        ])->assertRedirect();
        $this->assertSame('prefer_not_to_say',$original->fresh()->responses['suicidal_thoughts']);
        $this->assertSame(CompactScreening::SINGLE_VERSION,$original->fresh()->instrument_version);
        $this->assertSame('session_preferences_required',$case->fresh()->workflow_state);
        $this->assertDatabaseCount('screening_responses',2);
        $this->assertDatabaseCount('queue_requests',0);
    }

    public function test_single_screening_validates_description_answer_and_rejects_hidden_answers(): void
    {
        $this->actingAs($this->seeker()); $data=$this->singleAnswer('no');
        foreach([''=>'required',str_repeat('x',201)=>'too long'] as $description=>$unused) {
            $this->postJson(route('request.screening.process'),array_replace($data,['description'=>$description]))->assertUnprocessable()->assertJsonValidationErrors('description');
        }
        $this->postJson(route('request.screening.process'),array_replace($data,['safety_check'=>'invalid']))->assertUnprocessable()->assertJsonValidationErrors('safety_check');
        $this->postJson(route('request.screening.process'),$data+['current_suicide_plan'=>false])->assertUnprocessable()->assertJsonValidationErrors('current_suicide_plan');
        $this->assertDatabaseCount('counseling_sessions',0);
    }

    public function test_visible_screening_records_thoughts_without_inventing_a_current_plan(): void
    {
        $user=$this->seeker();
        $this->actingAs($user)->get(route('request.screening'))->assertOk()->assertSee('name="safety_check"',false)
            ->assertDontSee('name="current_suicide_plan"',false)->assertDontSee('name="suicidal_thoughts"',false)->assertDontSee('Answer all five questions')->assertDontSee('hidden_current_suicide_plan');
        $this->post(route('request.screening.process'),$this->answers(['suicidal_thoughts'=>'yes']))->assertRedirect(route('request.matching'));
        $case=Session::firstOrFail();
        $this->assertSame('high',$case->risk_level);
        $this->assertSame('adviser_review_required',$case->workflow_state);
        $screen=ScreeningResponse::firstOrFail();
        $this->assertSame('yes',$screen->responses['suicidal_thoughts']);
        $this->assertSame('no',$screen->responses['current_suicide_plan']);
        $this->assertSame(CompactScreening::FORM_VERSION,$screen->instrument_version);
        $this->assertArrayNotHasKey('access_to_means',$screen->responses);
        $this->assertDatabaseCount('queue_requests',0);
    }

    public function test_missing_answers_and_ambiguous_old_form_are_rejected(): void
    {
        $this->actingAs($this->seeker());
        $answers=$this->answers(); unset($answers['difficulty_coping']);
        $this->postJson(route('request.screening.process'),$answers)->assertUnprocessable()->assertJsonValidationErrors('difficulty_coping');
        $this->postJson(route('request.screening.process'),array_fill_keys(CompactScreening::FIELDS,false)+['safety_check'=>'yes','concern_id'=>$answers['concern_id']])
            ->assertUnprocessable()->assertJsonValidationErrors('safety_check');
        $this->postJson(route('request.screening.process'),$this->answers(['severe_distress'=>'0']))->assertUnprocessable();
        $this->assertDatabaseCount('counseling_sessions',0);
    }

    public function test_prefer_not_to_say_requires_review_and_adviser_can_clarify_without_overwriting(): void
    {
        $adviserUser=User::factory()->create(['role'=>'adviser','is_active'=>true]);
        $adviser=Adviser::create(['user_account_id'=>$adviserUser->id,'first_name'=>'Test','last_name'=>'Reviewer','email'=>$adviserUser->email]);
        $this->actingAs($this->seeker())->post(route('request.screening.process'),$this->answers(['difficulty_coping'=>'prefer_not_to_say']))->assertRedirect();
        $case=Session::firstOrFail(); $original=ScreeningResponse::firstOrFail();
        $this->assertNull($case->risk_level); $this->assertTrue($case->requires_adviser_review);
        $this->assertSame($adviser->id,$case->review_adviser_id);
        $this->actingAs($adviserUser)->get('/adviser/screenings')->assertOk()->assertSee('use_clarified_answers');
        $this->post(route('adviser.screenings.review',$case),[
            'risk_level'=>'low','reason'=>'Clarified all five responses with the Seeker.','evidence_source'=>'Direct clarification',
            'allow_peer_support'=>1,'use_clarified_answers'=>1,'answers'=>array_fill_keys(CompactScreening::FIELDS,false),
        ])->assertRedirect();
        $this->assertSame('prefer_not_to_say',$original->fresh()->responses['difficulty_coping']);
        $this->assertSame('low',$case->fresh()->risk_level);
        $this->assertDatabaseCount('screening_responses',2);
    }

    public function test_explicit_plan_keeps_emergency_precedence_over_unknown_answers(): void
    {
        $this->actingAs($this->seeker())->post(route('request.screening.process'),$this->answers(['current_suicide_plan'=>'yes','difficulty_coping'=>'prefer_not_to_say']))->assertRedirect();
        $this->assertSame('emergency',Session::firstOrFail()->risk_level);
        $this->assertTrue(Session::firstOrFail()->permitsEmergencySupport());
        $this->assertTrue(Session::firstOrFail()->requires_adviser_review);
    }

    public function test_partial_draft_is_encrypted_owned_and_does_not_create_operational_records(): void
    {
        $owner=$this->seeker(); $other=$this->seeker();
        $this->actingAs($owner)->postJson(route('request.draft.save'),$this->draft(['suicidal_thoughts'=>'prefer_not_to_say']))->assertOk();
        $draft=SeekerRequestDraft::firstOrFail();
        $this->assertSame('Private unfinished concern',$draft->payload['description']);
        $this->assertStringNotContainsString('Private unfinished concern',DB::table('seeker_request_drafts')->value('payload'));
        $this->assertDatabaseCount('counseling_sessions',0); $this->assertDatabaseCount('screening_responses',0); $this->assertDatabaseCount('queue_requests',0);
        $this->get(route('request.screening'))->assertOk()->assertSee('Private unfinished concern')->assertSee('Your previously saved screening entries were restored.')
            ->assertDontSee('data-save-draft',false)->assertDontSee('data-discard-draft',false);
        $this->actingAs($other)->get(route('request.screening'))->assertOk()->assertDontSee('Private unfinished concern');
        $this->deleteJson(route('request.draft.discard'))->assertOk();
        $this->assertDatabaseCount('seeker_request_drafts',1);
        $this->actingAs($owner)->postJson(route('request.draft.save'),$this->draft(['description'=>'Changed draft']))->assertOk();
        $this->assertDatabaseCount('seeker_request_drafts',1);
    }

    public function test_draft_requires_current_consent_and_expired_or_old_versions_are_not_restored(): void
    {
        $owner=$this->seeker(false);
        $this->actingAs($owner)->postJson(route('request.draft.save'),$this->draft())->assertConflict();
        $this->consentFixture($owner);
        $this->postJson(route('request.draft.save'),$this->draft())->assertOk();
        $this->travel(8)->days();
        $this->get(route('request.screening'))->assertOk()->assertDontSee('Private unfinished concern');
        $this->assertDatabaseCount('seeker_request_drafts',0);
        $this->postJson(route('request.draft.save'),$this->draft(['instrument_version'=>'unsupported']))->assertUnprocessable();
    }

    public function test_review_preferences_draft_and_final_submission_preserve_screening_and_language(): void
    {
        $user=$this->seeker(); $this->actingAs($user);
        $this->postJson(route('request.draft.save'),$this->draft())->assertOk();
        $this->post(route('request.screening.process'),$this->answers(['description'=>'Recorded original']))->assertRedirect(route('request.preferences'));
        $case=Session::firstOrFail(); $original=ScreeningResponse::firstOrFail()->responses;
        $this->assertDatabaseCount('seeker_request_drafts',0);
        $this->postJson(route('request.draft.save'),$this->draft())->assertConflict();
        $this->postJson(route('request.draft.save'),$this->draft(['stage'=>'preferences','session_id'=>$case->id,'preferred_language'=>'English/Tagalog','description'=>'Cannot overwrite']))->assertOk();
        $this->assertArrayNotHasKey('description',SeekerRequestDraft::firstOrFail()->payload);
        $this->get(route('request.preferences'))->assertOk()->assertSee('Find a Helper')->assertSee('Both (English and Tagalog)')
            ->assertDontSee('Review your request')->assertDontSee('Recorded original')->assertDontSee('data-save-draft',false)->assertDontSee('data-discard-draft',false);
        $this->post(route('request.preferences.process'),['support_mode'=>'voice','preferred_language'=>'English'])->assertSessionHasErrors('support_mode');
        $data=['support_mode'=>'chat','preferred_language'=>'English/Tagalog'];
        $this->post(route('request.preferences.process'),$data)->assertRedirect(route('request.matching'));
        $this->post(route('request.preferences.process'),$data)->assertRedirect(route('request.matching'));
        $this->assertSame('English/Tagalog',$case->fresh()->preferred_language);
        $this->assertSame($original,ScreeningResponse::firstOrFail()->responses);
        $this->assertDatabaseCount('queue_requests',1); $this->assertDatabaseCount('seeker_request_drafts',0);
        $this->postJson(route('request.draft.save'),$this->draft(['stage'=>'preferences','session_id'=>$case->id]))->assertConflict();
        $this->postJson(route('request.cancel',$case))->assertForbidden();
    }

    public function test_history_paginates_and_filters_only_owned_records_with_philippine_dates(): void
    {
        $owner=$this->seeker(); $other=$this->seeker(); $this->actingAs($owner);
        for($i=0;$i<17;$i++) Session::create(['seeker_id'=>$owner->helpSeeker->id,'session_type'=>'chat','session_status'=>'completed','created_date'=>'2026-10-10 16:30:00']);
        $foreign=Session::create(['seeker_id'=>$other->helpSeeker->id,'session_type'=>'chat','session_status'=>'completed','created_date'=>now()]);
        $page=$this->get(route('seeker.requests'))->assertOk()->assertViewHas('requests',fn($rows)=>$rows->total()===17 && $rows->count()===15);
        $this->get(route('seeker.requests',['page'=>2,'status'=>'completed']))->assertOk()->assertViewHas('requests',fn($rows)=>$rows->count()===2);
        $this->get(route('seeker.requests',['q'=>'R-0001']))->assertOk()->assertViewHas('requests',fn($rows)=>$rows->total()===1);
        $this->get(route('seeker.requests',['q'=>$foreign->reference_number]))->assertOk()->assertViewHas('requests',fn($rows)=>$rows->total()===0);
        $this->get(route('seeker.requests',['from'=>'2026-10-11','to'=>'2026-10-11']))->assertOk()->assertViewHas('requests',fn($rows)=>$rows->total()===17);
        $this->get(route('seeker.requests',['from'=>'2026-10-10','to'=>'2026-10-10']))->assertOk()->assertViewHas('requests',fn($rows)=>$rows->total()===0);
        $this->getJson(route('seeker.requests',['from'=>'2026-10-11','to'=>'2026-10-10']))->assertUnprocessable();
        $this->getJson(route('seeker.requests',['page'=>0]))->assertUnprocessable();
    }

    public function test_request_details_reject_other_seekers_and_hide_staff_data(): void
    {
        $owner=$this->seeker(); $other=$this->seeker();
        $case=Session::create(['seeker_id'=>$owner->helpSeeker->id,'session_type'=>'chat','session_status'=>'completed','created_date'=>now(),'matching_details'=>['private'=>'Staff internal detail']]);
        DB::table('request_status_events')->insert(['session_id'=>$case->id,'to_state'=>'private_staff_state','occurred_at'=>now()]);
        $this->actingAs($owner)->get(route('seeker.requests.show',$case))->assertOk()->assertSee($case->reference_number)->assertDontSee('Staff internal detail')->assertDontSee('private_staff_state');
        $this->actingAs($other)->get(route('seeker.requests.show',$case))->assertForbidden();
        $this->actingAs(User::factory()->create(['role'=>'helper']))->get(route('seeker.requests.show',$case))->assertForbidden();
    }

    public function test_feedback_can_be_viewed_but_not_overwritten_by_repeated_submission(): void
    {
        $owner=$this->seeker();
        $case=Session::create(['seeker_id'=>$owner->helpSeeker->id,'session_type'=>'chat','session_status'=>'completed','created_date'=>now()]);
        $data=$this->evaluationAnswers()+['session_id'=>$case->id,'comments'=>'Useful support'];
        $this->actingAs($owner)->get(route('session.evaluation',['session_id'=>$case->id]))->assertOk()->assertSee('comments-count')->assertSee('Maybe');
        $this->get(route('seeker.requests',['status'=>'feedback_pending']))->assertOk()->assertViewHas('requests',fn($rows)=>$rows->total()===1);
        $this->postJson(route('session.evaluation.process'),array_replace($data,['comments'=>str_repeat('x',501)]))->assertUnprocessable()->assertJsonValidationErrors('comments');
        $this->post(route('session.evaluation.process'),$data)->assertRedirect(route('session.thank-you'));
        $this->post(route('session.evaluation.process'),array_replace($data,['comments'=>'Changed']))->assertRedirect(route('seeker.requests.show',$case));
        $this->get(route('session.evaluation',['session_id'=>$case->id]))->assertRedirect(route('seeker.requests.show',$case));
        $this->get(route('seeker.requests.show',$case))->assertOk()->assertSee('Your submitted feedback')->assertSee('Useful support');
        $this->postJson(route('session.evaluation.process'),$data)->assertConflict();
        $this->assertSame('Useful support',HelpSeekerEvaluation::firstOrFail()->comments);
        $this->assertDatabaseCount('help_seeker_evaluations',1);
        $this->get(route('seeker.requests',['status'=>'feedback_pending']))->assertOk()->assertViewHas('requests',fn($rows)=>$rows->total()===0);
    }

    public function test_accepted_helper_shows_preparing_until_chat_is_active(): void
    {
        $owner=$this->seeker();
        $case=Session::create(['seeker_id'=>$owner->helpSeeker->id,'session_type'=>'chat','session_status'=>'helper_assigned','workflow_state'=>'session_ready','helper_accepted_at'=>now(),'created_date'=>now()]);
        $this->actingAs($owner)->get(route('request.matching'))->assertOk()->assertSee('Your helper is preparing')->assertDontSee('Waiting for helper acceptance')->assertDontSee('Open chat');
        $this->get(route('session.chat'))->assertRedirect();
    }

    public function test_real_queue_position_handles_assignment_and_durations_are_not_guessed(): void
    {
        $owner=$this->seeker(); $other=$this->seeker(); $this->actingAs($owner);
        $earlier=QueueRequest::create(['seeker_id'=>$other->helpSeeker->id,'request_date'=>now()->subMinutes(8),'request_status'=>'waiting','priority_level'=>'moderate','preferred_session_type'=>'chat']);
        $queue=QueueRequest::create(['seeker_id'=>$owner->helpSeeker->id,'request_date'=>now()->subMinutes(5),'request_status'=>'waiting','priority_level'=>'low','preferred_session_type'=>'chat']);
        $case=Session::create(['seeker_id'=>$owner->helpSeeker->id,'queue_request_id'=>$queue->id,'session_type'=>'chat','session_status'=>'waiting','created_date'=>now()]);
        $this->get(route('seeker.requests.show',$case))->assertOk()->assertViewHas('queuePosition',2);
        $earlier->update(['request_status'=>'assigned']);
        $this->get(route('seeker.requests.show',$case))->assertOk()->assertViewHas('queuePosition',1);
        $queue->update(['request_status'=>'assigned']);
        $this->assertNull(app(\App\Services\QueueManagementService::class)->position($queue));
        $this->get(route('seeker.requests.show',$case))->assertOk()->assertViewHas('queuePosition',null);
        $this->assertSame('5 min',\App\Services\SeekerRequestPresentation::duration('5 min'));
        $this->assertSame('5 min',\App\Services\SeekerRequestPresentation::duration('5 min min'));
        $this->assertSame('Duration not specified',\App\Services\SeekerRequestPresentation::duration('N/A'));
        $this->assertSame('Duration not specified',\App\Services\SeekerRequestPresentation::duration('-5'));
    }

    public function test_waiting_page_shows_three_public_resources_and_preserves_queue_on_refresh(): void
    {
        config(['app.enforce_duty_hours'=>false]);
        $owner=$this->seeker(); $this->actingAs($owner)->post(route('request.screening.process'),$this->answers())->assertRedirect();
        $this->post(route('request.preferences.process'),['support_mode'=>'chat','preferred_language'=>'English'])->assertRedirect();
        $case=Session::firstOrFail(); $queue=QueueRequest::firstOrFail();
        for ($i=0;$i<5;$i++) \App\Models\SelfHelpResource::create(['title'=>'Published resource '.$i,'description'=>'Guided practice','category'=>'exercise','duration'=>'5 min','is_published'=>true,'visibility'=>'public']);
        $private=\App\Models\SelfHelpResource::create(['title'=>'Private resource','category'=>'article','is_published'=>false]);
        $this->get(route('request.matching'))->assertOk()->assertViewHas('resources',fn($resources)=>$resources->count()===3)->assertDontSee('Private resource')->assertDontSee('5 min min')->assertSee('View more resources');
        $this->get(route('request.matching'))->assertOk()->assertViewHas('resources',fn($resources)=>$resources->count()===3);
        $this->assertSame($case->id,Session::firstOrFail()->id); $this->assertSame($queue->id,QueueRequest::firstOrFail()->id);
        $this->assertDatabaseCount('queue_requests',1);
    }

    public function test_matching_uses_submitted_language_even_after_profile_preferences_change(): void
    {
        config(['app.enforce_duty_hours'=>false]);
        $owner=$this->seeker(); $this->actingAs($owner)->post(route('request.screening.process'),$this->answers())->assertRedirect();
        $this->post(route('request.preferences.process'),['support_mode'=>'chat','preferred_language'=>'Tagalog'])->assertRedirect();
        $case=Session::firstOrFail();
        $owner->update(['preferred_language'=>'English']);
        $matcher=\Mockery::mock(\App\Services\HelperMatchingService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $matcher->shouldReceive('rankHelpers')->once()->withArgs(fn($helpers,$risk,$category,$language)=>$language === 'Tagalog')->andReturn(collect());
        $matcher->processQueueRequest($case->queue);
        $this->assertSame('Tagalog',$case->fresh()->preferred_language);
    }

    public function test_legacy_active_request_without_acceptance_does_not_offer_chat(): void
    {
        $owner=$this->seeker();
        Session::create(['seeker_id'=>$owner->helpSeeker->id,'session_type'=>'chat','session_status'=>'active','created_date'=>now()]);
        $this->actingAs($owner)->get(route('request.matching'))->assertOk()->assertSee('no recorded helper acceptance')->assertDontSee('Open chat');
        Session::firstOrFail()->update(['helper_accepted_at'=>now()]);
        $this->get(route('request.matching'))->assertOk()->assertSee('helper connection for this request cannot be confirmed')->assertDontSee('Open chat');
    }
}
