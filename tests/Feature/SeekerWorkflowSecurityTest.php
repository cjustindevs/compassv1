<?php
namespace Tests\Feature;
use App\Models\{Adviser, AuditLog, ConsentRecord, ConcernCategory, HelpSeeker, Helper, HelperSchedule, ReadinessCheck, QueueRequest, Session, User};
use App\Services\{ConsentService, EvaluationInstrument, HelperMatchingService, OperatingHoursService, QueueManagementService, RiskClassificationService, ScreeningInstrument, SeekerWorkflowService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Gate};
use Tests\TestCase;
class SeekerWorkflowSecurityTest extends TestCase {
    use RefreshDatabase;
    use \Tests\Concerns\SeekerWorkflowFixtures;
    protected function setUp(): void {
        parent::setUp(); $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-16 19:00:00','Asia/Manila')->utc());
        config(['screening.approved_version'=>ScreeningInstrument::VERSION,'screening.approval_reference'=>'TEST-ONLY-APPROVAL']);
    }
    private function seeker(bool $consent=true): User {
        $user=User::factory()->create(['role'=>'seeker','is_active'=>true]);
        HelpSeeker::create(['user_account_id'=>$user->id,'generated_alias'=>'CalmFox'.$user->id,'pseudo_id'=>'PS-TEST-'.$user->id,'age'=>20,'gender'=>'prefer-not-to-say']);
        $user->unsetRelation('helpSeeker');
        if ($consent) { $this->actingAs($user); foreach(['privacy_policy','informed_consent'] as $purpose) app(ConsentService::class)->decide($user,$purpose,'accepted'); }
        return $user;
    }
    private function answers(array $overrides=[]): array { return array_replace(array_fill_keys(array_keys(ScreeningInstrument::QUESTIONS),'no'),$overrides); }
    private function queued(User $user): Session {
        $this->actingAs($user)->post(route('request.screening.process'),$this->answers())->assertRedirect(route('request.concern'));
        $this->post(route('request.concern.process'),['concern_id'=>ConcernCategory::where('concern_name','Stress')->value('id')])->assertRedirect(route('request.preferences'));
        $this->post(route('request.preferences.process'),['support_mode'=>'chat','preferred_language'=>'English'])->assertRedirect(route('request.matching'));
        return Session::where('seeker_id',$user->helpSeeker->id)->latest('id')->firstOrFail();
    }
    private function helper(): Helper {
        $user=User::factory()->create(['role'=>'helper']);
        $helper=Helper::create(['user_account_id'=>$user->id,'first_name'=>'Test','last_name'=>'Helper','email'=>$user->email,'status'=>'available','availability'=>'available','competency_level'=>4,'competency_risk_level'=>4]);
        HelperSchedule::create(['helper_id'=>$helper->id,'date'=>now('Asia/Manila')->toDateString(),'shift_start'=>'18:00:00','shift_end'=>'23:00:00','created_by'=>$user->id,'is_active'=>true]);
        ReadinessCheck::create(['helper_id'=>$helper->id,'assessment_date'=>now(),'availability_status'=>'available','assessment_result'=>'ready','emotionally_ready'=>true,'willing_to_listen'=>true,'stress_level'=>'low','valid_until'=>now()->addHours(3),'is_active'=>true]);
        $this->verifiedHelperFixture($helper);
        return $helper;
    }
    public function test_every_seeker_route_rejects_other_roles_and_guests(): void {
        $routes=collect(app('router')->getRoutes()->getRoutes())->filter(fn($r)=>in_array('role:seeker',$r->gatherMiddleware()));
        foreach ($routes as $route) {
            $uri='/'.preg_replace('/\{[^}]+\}/','999',$route->uri());
            foreach(['helper','moderator','adviser','professional','admin'] as $role) {
                $user=User::factory()->create(['role'=>$role]);
                // Even a stray seeker profile on another role cannot authorize workflow entry.
                HelpSeeker::create(['user_account_id'=>$user->id,'generated_alias'=>'Alias'.$user->id,'age'=>20,'gender'=>'male']);
                $this->actingAs($user)->call($route->methods()[0],$uri)->assertForbidden();
            }
            auth()->logout(); $this->call($route->methods()[0],$uri)->assertRedirect('/login');
        }
    }
    public function test_consent_and_workflow_steps_cannot_be_skipped(): void {
        $user=$this->seeker(false); $this->actingAs($user);
        $this->get(route('request.screening'))->assertOk()->assertSee('seekerConsentDialog');
        $this->postJson(route('request.screening.process'),$this->answers())->assertRedirect(route('request.screening'))->assertSessionHas('open_consent',true);
        foreach(['privacy_policy','informed_consent'] as $purpose) app(ConsentService::class)->decide($user,$purpose,'accepted');
        $this->postJson(route('request.preferences.process'),['support_mode'=>'chat','preferred_language'=>'English'])->assertStatus(409);
        $this->post(route('request.screening.process'),$this->answers())->assertRedirect(route('request.concern'));
        $this->get(route('request.preferences'))->assertRedirect(route('request.concern'));
        $this->assertDatabaseCount('queue_requests',0);
    }
    public function test_screening_preserves_actual_answers_and_requires_all_inputs(): void {
        $user=$this->seeker(); $this->actingAs($user);
        $answers=$this->answers(); unset($answers['sleep_affected']);
        $this->postJson(route('request.screening.process'),$answers)->assertUnprocessable()->assertJsonValidationErrors('sleep_affected');
        $answers=$this->answers(['sleep_affected'=>'yes','recurring_distress'=>'yes']);
        $this->post(route('request.screening.process'),$answers)->assertRedirect(route('request.concern'));
        $this->assertSame($answers,\App\Models\ScreeningResponse::first()->responses);
        $this->assertDatabaseHas('screening_responses',['risk_level'=>'moderate','rule_code'=>'v4_moderate','instrument_version'=>ScreeningInstrument::VERSION]);
    }
    public function test_emergency_overrides_contradictions_and_bypasses_queue(): void {
        $user=$this->seeker(); $this->actingAs($user)->post(route('request.screening.process'),$this->answers(['immediate_intent'=>'yes','current_suicide_plan'=>'yes','suicidal_thoughts'=>'no']))->assertRedirect(route('request.matching'));
        $this->assertDatabaseHas('counseling_sessions',['workflow_state'=>'emergency_escalated']);
        $this->assertDatabaseCount('emergency_alerts',1); $this->assertDatabaseCount('queue_requests',0);
        $this->postJson(route('request.preferences.process'),['support_mode'=>'chat','preferred_language'=>'English'])->assertStatus(409);
        $this->assertDatabaseHas('audit_logs',['action'=>'emergency_branch_activated']);
    }
    public function test_prefer_not_to_say_and_unapproved_instrument_require_review(): void {
        $user=$this->seeker(); $this->actingAs($user)->post(route('request.screening.process'),$this->answers(['suicidal_thoughts'=>'prefer_not_to_say']))->assertRedirect();
        $this->assertDatabaseHas('counseling_sessions',['seeker_id'=>$user->helpSeeker->id,'workflow_state'=>'adviser_review_required','risk_level'=>null]);
        config(['screening.approval_reference'=>null]);$other=$this->seeker();
        $this->post(route('request.screening.process'),$this->answers())->assertRedirect();
        $this->assertDatabaseHas('counseling_sessions',['seeker_id'=>$other->helpSeeker->id,'workflow_state'=>'adviser_review_required']);
    }
    public function test_highest_risk_first_and_plan_requires_means_for_emergency(): void {
        $risk=app(RiskClassificationService::class);
        foreach ([['immediate_intent'=>'yes','severe_distress'=>'yes','difficulty_coping'=>'yes'],['severe_distress'=>'yes','difficulty_coping'=>'yes'],['difficulty_coping'=>'yes'],[]] as $i=>$input)
            $this->assertSame(['emergency','high','moderate','low'][$i],$risk->classifyRisk($this->answers($input))['risk_level']);
        $this->assertSame('high',$risk->classifyRisk($this->answers(['current_suicide_plan'=>'yes','suicidal_thoughts'=>'yes']))['risk_level']);
        $this->assertSame('emergency',$risk->classifyRisk($this->answers(['current_suicide_plan'=>'yes','access_to_means'=>'yes']))['risk_level']);
    }
    public function test_read_only_gets_preserve_waiting_state_and_scheduler_expires_with_history(): void {
        $user=$this->seeker();$session=$this->queued($user);
        $session->update(['created_date'=>now()->subHours(25)]);$before=$session->fresh()->getAttributes();
        foreach(['seeker.dashboard','request.matching','session.history','seeker.requests'] as $route) $this->get(route($route))->assertOk();
        $this->assertSame($before,$session->fresh()->getAttributes());
        $this->artisan('sessions:mark-abandoned')->assertSuccessful();
        $this->assertSame('cancelled',$session->fresh()->session_status);
        $this->assertDatabaseHas('queue_requests',['id'=>$session->queue_request_id,'request_status'=>'expired']);
        $this->assertDatabaseHas('audit_logs',['action'=>'request_expired']);
    }
    public function test_cross_account_records_and_manipulated_ids_are_denied(): void {
        $owner=$this->seeker();$session=$this->queued($owner);$other=$this->seeker();$this->actingAs($other);
        $this->post(route('request.cancel',$session))->assertForbidden();
        $this->getJson(route('chat.messages',$session->id))->assertForbidden();
        $this->postJson(route('risk.classify'),['seeker_id'=>$owner->helpSeeker->id,'screening_responses'=>$this->answers()])->assertForbidden();
        foreach([$session,$session->queue,$session->screeningResponses()->first(),ConsentRecord::where('seeker_id',$owner->helpSeeker->id)->first()] as $record) $this->assertFalse(Gate::allows('view',$record));
    }
    public function test_matching_requires_acceptance_and_blocks_parallel_sessions(): void {
        $user=$this->seeker();$session=$this->queued($user);$helper=$this->helper();
        $matched=app(HelperMatchingService::class)->processQueueRequest($session->queue);
        $this->assertNotNull($matched);$this->get(route('session.chat'))->assertRedirect();
        $this->postJson(route('chat.send'),['session_id'=>$session->id,'message'=>'Too early'])->assertStatus(409);
        $this->actingAs($helper->user);app(SeekerWorkflowService::class)->accept($helper->user,$session->fresh());
        $this->assertNotNull($session->fresh()->helper_accepted_at);$this->assertFalse($helper->fresh()->hasCapacity());
        app(SeekerWorkflowService::class)->start($helper->user,$session->fresh());
        $this->actingAs($user)->postJson(route('chat.send'),['session_id'=>$session->id,'message'=>'Hello'])->assertOk();
    }
    public function test_operating_hours_cutoff_and_queue_aging_preserve_risk(): void {
        $user=$this->seeker();$session=$this->queued($user);$this->helper();
        $session->queue->update(['request_date'=>now()->subHours(2)]);app(QueueManagementService::class)->checkQueueAging();
        $this->assertSame('low',$session->fresh()->risk_level);$this->assertSame('low',$session->queue->fresh()->priority_level);
        foreach(['2026-09-16 17:59','2026-09-16 22:30','2026-09-20 19:00'] as $time) { $this->travelTo(\Illuminate\Support\Carbon::parse($time,'Asia/Manila'));$this->assertFalse(app(OperatingHoursService::class)->acceptsAssignments()); }
    }
    public function test_withdrawal_is_a_new_event_and_blocks_further_processing(): void {
        $user=$this->seeker();$session=$this->queued($user);$original=ConsentRecord::first()->getAttributes();
        $this->post(route('seeker.privacy.decision'),['purpose'=>'privacy_policy','decision'=>'withdrawn'])->assertRedirect();
        $this->assertSame($original,ConsentRecord::first()->getAttributes());
        $this->assertDatabaseCount('consent_records',3);$this->assertSame('cancelled',$session->fresh()->session_status);
        $this->postJson(route('request.screening.process'),$this->answers())->assertRedirect(route('request.screening'))->assertSessionHas('open_consent',true);
    }
    public function test_categorical_evaluation_requires_completed_owner_and_rejects_duplicates(): void {
        $user=$this->seeker();$session=$this->queued($user);$answers=array_map(fn($options)=>$options[0],EvaluationInstrument::OPTIONS);
        $this->postJson(route('session.evaluation.process'),$answers+['session_id'=>$session->id])->assertStatus(409);
        $session->update(['session_status'=>'completed','completion_status'=>'completed','end_time'=>now()]);
        $this->post(route('session.evaluation.process'),$answers+['session_id'=>$session->id])->assertRedirect(route('session.thank-you'));
        $this->assertDatabaseHas('help_seeker_evaluations',['session_id'=>$session->id,'overall_score'=>10]);
        $this->postJson(route('session.evaluation.process'),$answers+['session_id'=>$session->id])->assertStatus(409);
        $this->assertSame(['helpfulness_score'=>1,'comfort_score'=>1,'feeling_after_score'=>1,'understood_score'=>1,'reuse_score'=>1],EvaluationInstrument::scores(array_map(fn($x)=>end($x),EvaluationInstrument::OPTIONS)));
    }
    public function test_voice_and_seeker_transcript_downloads_are_unavailable(): void {
        $user=$this->seeker();$session=$this->queued($user);
        $this->post(route('request.voice-consent.process'))->assertStatus(503);
        $this->get(route('session.voice'))->assertStatus(503);
        $this->get(route('api.transcript.download',$session->id))->assertForbidden();
        $this->assertDatabaseCount('call_logs',0);
    }

    public function test_high_risk_requires_review_and_retains_original_screening(): void {
        $adviserUser=User::factory()->create(['role'=>'adviser','is_active'=>true]);
        $adviser=\App\Models\Adviser::create(['user_account_id'=>$adviserUser->id,'first_name'=>'Review','last_name'=>'Adviser','email'=>$adviserUser->email]);
        $user=$this->seeker();
        $high=array_fill_keys(\App\Services\CompactScreening::FIELDS,'0');$high['suicidal_thoughts']='1';
        $this->post(route('request.screening.process'),$high+['concern_id'=>ConcernCategory::where('concern_name','Stress')->value('id')])->assertRedirect(route('request.matching'));
        $session=Session::where('seeker_id',$user->helpSeeker->id)->firstOrFail();
        $original=$session->screeningResponses()->first()->getAttributes();
        $this->assertSame('adviser_review_required',$session->workflow_state);
        $this->assertDatabaseCount('queue_requests',0);
        $this->assertDatabaseHas('referrals',['session_id'=>$session->id,'adviser_id'=>$adviser->id,'help_seeker_consent'=>false]);
        $this->actingAs($adviserUser)->post(route('adviser.screenings.review',$session),['use_clarified_answers'=>true,'answers'=>array_merge(array_fill_keys(\App\Services\CompactScreening::FIELDS,0),['recurring_distress'=>1]),'risk_level'=>'moderate','reason'=>'Reviewed responses and clarified current support needs.','evidence_source'=>'Adviser clarification with seeker','allow_peer_support'=>true])->assertRedirect();
        $this->assertSame($original,$session->screeningResponses()->oldest('id')->first()->getAttributes());
        $this->assertSame('concern_required',$session->fresh()->workflow_state);
        $this->assertDatabaseHas('audit_logs',['action'=>'risk_reassessed']);
    }
    public function test_declared_conflict_and_missing_submission_prevent_matching(): void {
        $user=$this->seeker();$session=$this->queued($user);$helper=$this->helper();
        DB::table('helper_conflicts')->insert(['helper_id'=>$helper->id,'seeker_id'=>$user->helpSeeker->id,'reported_by'=>$helper->user_account_id,'created_at'=>now()]);
        $this->assertSame('A declared conflict prevents this assignment.',app(HelperMatchingService::class)->manualAssign($session->queue,$helper->id));
        $other=$this->helper();$session->update(['submitted_at'=>null]);
        $this->assertIsString(app(HelperMatchingService::class)->manualAssign($session->queue,$other->id));
        $this->assertSame('waiting',$session->fresh()->session_status);
    }
    public function test_seeker_can_cancel_a_waiting_request_idempotently(): void {
        $user=$this->seeker();$session=$this->queued($user);
        $this->actingAs($user)->post(route('request.cancel',$session))->assertRedirect(route('seeker.requests'));
        $this->assertSame('cancelled',$session->fresh()->session_status);
        $this->assertSame('cancelled',$session->queue->fresh()->request_status);
        $this->actingAs($user)->post(route('request.cancel',$session))->assertRedirect(route('seeker.requests'));
        $this->assertSame('cancelled',$session->fresh()->session_status);
        $this->assertDatabaseHas('audit_logs',['action'=>'request_cancelled']);
    }
    public function test_manual_assignment_reports_explicit_shift_capacity_reason(): void {
        $user=$this->seeker();$session=$this->queued($user);$helper=$this->helper();
        foreach([18,19] as $hour) Session::create(['seeker_id'=>$user->helpSeeker->id,'helper_id'=>$helper->id,'session_status'=>'completed','start_time'=>\Illuminate\Support\Carbon::parse(sprintf('2026-09-16 %02d:00',$hour),'Asia/Manila')->utc(),'end_time'=>\Illuminate\Support\Carbon::parse(sprintf('2026-09-16 %02d:30',$hour),'Asia/Manila')->utc(),'completion_status'=>'completed']);
        $this->assertSame('The two-session duty-shift limit has been reached.',app(HelperMatchingService::class)->manualAssign($session->queue,$helper->id));
        $this->assertSame('waiting',$session->fresh()->session_status);
    }
    public function test_new_screening_review_goes_to_the_least_loaded_active_adviser(): void {
        $makeAdviser=function(string $email){ $u=User::factory()->create(['role'=>'adviser','is_active'=>true]); return Adviser::create(['user_account_id'=>$u->id,'first_name'=>'Review','last_name'=>'Adviser','email'=>$email]); };
        $busy=$makeAdviser('busy-reviewer@example.com');
        $free=$makeAdviser('free-reviewer@example.com');
        $preSeeker=User::factory()->create(['role'=>'seeker']);$preSeekerProfile=HelpSeeker::create(['user_account_id'=>$preSeeker->id,'generated_alias'=>'Pre'.$preSeeker->id,'age'=>20,'gender'=>'prefer-not-to-say']);
        Session::create(['seeker_id'=>$preSeekerProfile->id,'session_type'=>'chat','session_status'=>'pending_review','workflow_state'=>'adviser_review_required','review_adviser_id'=>$busy->id,'requires_adviser_review'=>true,'completion_status'=>'pending','created_date'=>now()]);
        $user=$this->seeker();$this->actingAs($user)->post(route('request.screening.process'),$this->answers(['suicidal_thoughts'=>'prefer_not_to_say']))->assertRedirect();
        $this->assertSame($free->id,Session::where('seeker_id',$user->helpSeeker->id)->latest('id')->firstOrFail()->review_adviser_id);
    }
    public function test_consent_and_audit_cannot_be_rewritten_by_models(): void {
        $this->seeker();
        foreach([ConsentRecord::first(),AuditLog::first()] as $record) {
            try { $record->delete(); $this->fail('Evidence deletion must be denied.'); }
            catch (\LogicException $e) { $this->assertTrue($record->fresh()->exists); }
        }
    }

    public function test_restored_short_form_routes_to_preferences_without_approval_gate(): void {
        config(['screening.approval_reference'=>null]);
        $user=$this->seeker();
        $this->get(route('request.screening'))->assertOk()->assertSee('Area of Concern')->assertDontSee('name="immediate_intent"',false);
        $answers=array_fill_keys(\App\Services\CompactScreening::FIELDS,'0');
        $payload=$answers+['concern_id'=>ConcernCategory::where('concern_name','Stress')->value('id'),'description'=>'Deadlines are stressful.'];
        $this->post(route('request.screening.process'),$payload)->assertRedirect(route('request.preferences'));
        $session=Session::where('seeker_id',$user->helpSeeker->id)->firstOrFail();
        $this->assertSame('session_preferences_required',$session->workflow_state);
        $stored=$session->screeningResponses()->first();
        $this->assertSame(\App\Services\CompactScreening::VERSION,$stored->instrument_version);
        $this->assertArrayNotHasKey('sleep_affected',$stored->responses);
        $this->post(route('request.preferences.process'),['support_mode'=>'chat','preferred_language'=>'Tagalog'])->assertRedirect(route('request.matching'));
        $this->assertDatabaseCount('queue_requests',1);
    }
    public function test_short_form_requires_five_answers_and_uses_previous_routing(): void {
        $this->seeker();$payload=array_fill_keys(\App\Services\CompactScreening::FIELDS,'0');
        $payload['concern_id']=ConcernCategory::first()->id;unset($payload['difficulty_coping']);
        $this->postJson(route('request.screening.process'),$payload)->assertUnprocessable()->assertJsonValidationErrors('difficulty_coping');
        $classifier=app(\App\Services\CompactScreening::class);
        foreach(['current_suicide_plan'=>'emergency','suicidal_thoughts'=>'high','severe_distress'=>'high','recurring_distress'=>'moderate','difficulty_coping'=>'moderate'] as $key=>$risk) {
            $this->assertSame($risk,$classifier->classify(array_replace(array_fill_keys(\App\Services\CompactScreening::FIELDS,'0'),[$key=>'1']))['risk_level']);
        }
    }
    public function test_consent_saves_in_modal_and_pages_highlight_sidebar_links(): void {
        $user=$this->seeker(false);
        $this->actingAs($user)->get(route('request.screening'))->assertOk()->assertSee('id="seekerConsentDialog"',false);
        $this->postJson(route('seeker.consent.accept'),['agree_privacy'=>1,'agree_terms'=>1,'agree_emergency'=>1,'agree_consent'=>1])->assertOk()->assertJson(['success'=>true]);
        foreach(['seeker.requests'=>'Request history','seeker.privacy'=>'Privacy and consent','seeker.referrals'=>'Referral decisions'] as $route=>$label) {
            $this->get(route($route))->assertOk()->assertSee('title="'.$label.'" class="nav-item active"',false)->assertSee('<span class="nav-text">'.$label.'</span>',false);
        }
    }
}
