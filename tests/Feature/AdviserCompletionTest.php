<?php
namespace Tests\Feature;
use App\Models\{Adviser,Helper,HelpSeeker,Session,SessionReport,User,Referral,EmergencyAlert,SelfHelpResource,HelperCompetencyHistory,TrainingRecommendation};
use App\Services\{AdviserEvaluationService,CompetencyRubric,TrainingRecommendationService,AdviserResourceService,AdviserEmergencyService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class AdviserCompletionTest extends TestCase {
    use RefreshDatabase;
    private function records(): array {
        $user=User::factory()->create(['role'=>'adviser']);
        $adviser=Adviser::create(['user_account_id'=>$user->id,'first_name'=>'Demo','last_name'=>'Adviser','email'=>$user->email]);
        $hu=User::factory()->create(['role'=>'helper']);
        $helper=Helper::create(['user_account_id'=>$hu->id,'adviser_id'=>$adviser->id,'first_name'=>'Demo','last_name'=>'Helper','email'=>$hu->email]);
        $su=User::factory()->create(['role'=>'seeker']);
        $seeker=HelpSeeker::create(['user_account_id'=>$su->id,'generated_alias'=>'DemoSeeker'.$su->id,'age'=>20,'gender'=>'prefer-not-to-say']);
        $session=Session::create(['seeker_id'=>$seeker->id,'helper_id'=>$helper->id,'session_type'=>'chat','session_status'=>'completed','risk_level'=>'low','created_date'=>now()->subHour(),'start_time'=>now()->subMinutes(50),'end_time'=>now()->subMinutes(10)]);
        $report=SessionReport::create(['session_id'=>$session->id,'session_summary'=>'Evidence of listening and clarification.','personal_reflection'=>'Improve the way questions are summarized.']);
        $this->actingAs($user);
        return [$user,$helper,$session,$report];
    }
    private function scores(): array { return ['active_listening'=>5,'empathy'=>4,'respect_professionalism'=>3,'ethical_practices'=>2,'referral_accuracy'=>1,'recommended_action'=>'Follow up','strengths'=>'Listening','improvement_areas'=>'Clarification']; }
    public function test_structured_evaluation_preserves_independent_scores_evidence_and_corrections(): void {
        [$user,$helper,$session,$report]=$this->records();
        $service=app(AdviserEvaluationService::class);
        $evaluation=$service->save($report,$this->scores());
        $this->assertSame(100,array_sum(array_column(CompetencyRubric::CRITERIA,1)));
        $this->assertEquals(3.35,$evaluation->overall_score);
        $this->assertEquals(5,$evaluation->active_listening_score);
        $this->assertEquals(1,$evaluation->referral_accuracy_score);
        $this->assertSame($report->id,$evaluation->evidence['session_report_id']);
        $service->save($report,$this->scores());
        $this->assertSame(1,HelperCompetencyHistory::count());
        $service->save($report,array_merge($this->scores(),['active_listening'=>4,'correction_reason'=>'Correcting the documented listening rating.']));
        $versions=DB::table('supervision_record_versions')->where('record_type','helper_competency_history')->orderBy('version')->get();
        $this->assertCount(2,$versions);
        $this->assertEquals(5,json_decode($versions[0]->snapshot,true)['active_listening_score']);
        $this->assertEquals(4,$evaluation->fresh()->active_listening_score);
    }
    public function test_helper_training_completion_and_adviser_review_are_separate(): void {
        [$user,$helper,$session,$report]=$this->records();
        $evaluation=app(AdviserEvaluationService::class)->save($report,$this->scores());
        $task=app(TrainingRecommendationService::class)->create(['evaluation_id'=>$evaluation->id,'criterion'=>'referral_accuracy','reason'=>'Review documented gaps in referral judgments.','activity'=>'Read the institutional referral protocol','priority'=>'normal']);
        $this->actingAs($helper->user)->patch(route('helper.training.update',$task),['status'=>'reviewed','review_notes'=>'Self approval'])->assertStatus(409);
        $this->patch(route('helper.training.update',$task),['status'=>'in_progress'])->assertRedirect();
        $this->patch(route('helper.training.update',$task),['status'=>'completed','completion_evidence'=>'Completed protocol review and discussion notes.'])->assertRedirect();
        $this->assertNull($task->fresh()->reviewed_at);
        $this->actingAs($user)->patch(route('adviser.training.update',$task),['status'=>'reviewed','review_notes'=>'Reviewed the completion evidence together.'])->assertRedirect();
        $this->assertNotNull($task->fresh()->reviewed_at);
        $this->assertDatabaseHas('audit_logs',['action'=>'training_reviewed','target_id'=>$task->id]);
    }
    public function test_internal_expired_and_archived_resources_are_not_public(): void {
        $this->records();
        $service=app(AdviserResourceService::class);
        $resource=new SelfHelpResource;
        $service->save($resource,['title'=>'Private guidance','category'=>'Training','is_published'=>true,'visibility'=>'internal'],'Initial internal guidance');
        $this->assertFalse(SelfHelpResource::published()->whereKey($resource->id)->exists());
        $service->save($resource,['visibility'=>'public','review_date'=>now()->subDay()->toDateString()],'Expired review date');
        $this->assertFalse(SelfHelpResource::published()->whereKey($resource->id)->exists());
        $service->save($resource,['review_date'=>now()->addMonth()->toDateString()],'Institutional review completed');
        $this->assertTrue(SelfHelpResource::published()->whereKey($resource->id)->exists());
        $service->save($resource,['archived_at'=>now()],'Archive old guidance');
        $this->assertFalse(SelfHelpResource::published()->whereKey($resource->id)->exists());
        $this->assertNotNull($resource->fresh());
        $this->assertDatabaseCount('supervision_record_versions',4);
    }
    public function test_emergency_actions_are_scoped_and_history_is_preserved(): void {
        [$user,$helper,$session]=$this->records();
        $alert=EmergencyAlert::create(['session_id'=>$session->id,'seeker_id'=>$session->seeker_id,'adviser_id'=>$user->adviser->id,'trigger_reason'=>'Documented safety concern','alert_type'=>'manual','risk_level'=>'emergency','triggered_at'=>now(),'status'=>'pending']);
        $url=route('adviser.emergencies.action',$alert->id);
        $this->actingAs($helper->user)->post($url,['action'=>'acknowledged','notes'=>'Helper cannot acknowledge for adviser.'])->assertForbidden();
        $this->actingAs($user)->post($url,['action'=>'acknowledged','notes'=>'Acknowledged the documented escalation.'])->assertRedirect();
        $this->assertNotNull($alert->fresh()->acknowledged_at);
        $this->post($url,['action'=>'coordination','notes'=>'Coordinated the approved institutional response.'])->assertRedirect();
        $this->post(route('adviser.emergencies.resolve',$alert->id),['resolution_notes'=>'Documented follow up and resolution.'])->assertRedirect();
        $this->assertDatabaseCount('emergency_review_actions',3);
        $this->post($url,['action'=>'instruction','notes'=>'Too late'])->assertStatus(409);
    }
    public function test_referral_clarification_must_be_answered_before_approval(): void {
        [$user,$helper,$session]=$this->records();
        $r=Referral::create(['session_id'=>$session->id,'helper_id'=>$helper->id,'adviser_id'=>$user->adviser->id,'referral_reason'=>'Further support requested','referral_date'=>now(),'status'=>'pending_adviser']);
        app(\App\Services\ReferralManagementService::class)->clarify($r,'Please clarify the supporting documentation.');
        $this->post(route('adviser.referral.approve',$r->id),['review_notes'=>'Approved after review'])->assertRedirect()->assertSessionHasErrors('review_notes');
        $this->actingAs($helper->user)->post(route('helper.referral.clarify',$r->id),['response'=>'Supporting documentation is in the submitted summary.'])->assertRedirect();
        $this->actingAs($user)->post(route('adviser.referral.approve',$r->id),['review_notes'=>'Reviewed the clarified supporting documentation.'])->assertRedirect();
        $this->assertSame('pending_consent',$r->fresh()->status);
        $this->assertFalse($r->fresh()->help_seeker_consent);
        $this->assertDatabaseCount('supervision_record_versions',3);
    }
}
