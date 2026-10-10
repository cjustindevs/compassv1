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
    public function test_legacy_referral_can_be_returned_revised_and_approved_without_resolving_emergency(): void {
        [$user, $helper, $session] = $this->records();
        $referral = Referral::create(['session_id' => $session->id, 'helper_id' => $helper->id,
            'adviser_id' => $user->adviser->id, 'referral_reason' => 'Original emergency recommendation',
            'priority_level' => 'emergency', 'status' => Referral::STATUS_CONSENT_REQUESTED,
            'help_seeker_consent' => true, 'consent_obtained_at' => now()->subDay()]);
        $alert = EmergencyAlert::create(['session_id' => $session->id, 'seeker_id' => $session->seeker_id,
            'adviser_id' => $user->adviser->id, 'status' => 'open', 'risk_level' => 'emergency',
            'trigger_reason' => 'Emergency remains under review', 'triggered_at' => now()]);

        $this->post(route('adviser.referral.reject', $referral->case_reference),
            ['rejection_reason' => 'Please clarify the supporting recommendation.'])
            ->assertRedirect(route('adviser.referrals'))->assertSessionHasNoErrors()->assertSessionHas('info');
        $referral->refresh();
        $this->assertSame(Referral::STATUS_PENDING_ADVISER, $referral->status);
        $this->assertSame('Original emergency recommendation', $referral->referral_reason);
        $this->assertSame('Please clarify the supporting recommendation.', $referral->clarification_question);
        $this->assertNull($referral->reviewed_at);
        $this->assertNull($referral->approved_at);
        $this->assertTrue($referral->help_seeker_consent);
        $this->assertFalse($referral->canProvideIdentity());
        $this->assertDatabaseHas('notifications', ['user_account_id' => $helper->user_account_id, 'title' => 'Referral clarification']);
        $this->assertSame('open', $alert->fresh()->status);
        $this->assertNull($alert->fresh()->resolved_at);

        $this->post(route('adviser.referral.approve', $referral->id), ['review_notes' => 'Cannot approve before revision'])
            ->assertSessionHasErrors('review_notes');
        $this->actingAs($helper->user)->post(route('helper.referral.clarify', $referral->id),
            ['response' => 'The supporting recommendation has been clarified.'])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('adviser.referral.approve', $referral->id),
            ['review_notes' => 'Reviewed the clarified emergency recommendation.'])->assertSessionHasNoErrors();
        $this->assertSame(Referral::STATUS_PENDING_CONSENT, $referral->fresh()->status);
        $this->assertFalse($referral->fresh()->help_seeker_consent);
        $this->assertNull($referral->fresh()->professional_id);
        $this->assertSame('open', $alert->fresh()->status);
    }

    public function test_queue_explains_pending_revision_and_blocks_duplicate_return_without_replacing_comments(): void {
        [$user, $helper, $session] = $this->records();
        $referral = Referral::create(['session_id' => $session->id, 'helper_id' => $helper->id,
            'adviser_id' => $user->adviser->id, 'referral_reason' => 'Recommendation to clarify', 'status' => 'pending_adviser']);
        $url = route('adviser.referral.reject', $referral->id);
        $this->post($url, ['rejection_reason' => 'Please clarify the supporting documentation.'])->assertSessionHasNoErrors();
        $versions = DB::table('supervision_record_versions')->where('record_type', 'referrals')->where('record_id', $referral->id)->count();
        $notifications = \App\Models\Notification::count();
        $this->get(route('adviser.referrals'))->assertOk()->assertSee('Awaiting Helper revision')
            ->assertDontSee('onclick="openRejectModal('.$referral->id.')"', false)
            ->assertDontSee('onclick="openApproveModal('.$referral->id.')"', false);
        $this->post($url, ['rejection_reason' => 'Do not replace the outstanding comments.'])
            ->assertSessionHasErrors(['rejection_reason' => 'This referral is already awaiting the Helper\'s revision. Open the referral to view your comments.']);
        $this->assertSame('Please clarify the supporting documentation.', $referral->fresh()->clarification_question);
        $this->assertSame($versions, DB::table('supervision_record_versions')->where('record_type', 'referrals')->where('record_id', $referral->id)->count());
        $this->assertSame($notifications, \App\Models\Notification::count());
    }

    public function test_revision_does_not_reopen_progressed_or_reviewed_referrals_and_legacy_ownership_is_enforced(): void {
        [$user, $helper, $session] = $this->records();
        $referral = Referral::create(['session_id' => $session->id, 'helper_id' => $helper->id,
            'adviser_id' => $user->adviser->id, 'referral_reason' => 'Protected recommendation', 'status' => 'pending_adviser']);
        foreach (['pending_consent', 'pending_professional', 'accepted', 'in_progress', 'completed', 'closed', 'declined'] as $status) {
            $referral->update(['status' => $status]);
            $this->post(route('adviser.referral.reject', $referral->id), ['rejection_reason' => 'Do not reopen progressed work.'])
                ->assertSessionHasErrors('rejection_reason');
            $this->assertSame($status, $referral->fresh()->status);
            $this->assertNull($referral->fresh()->clarification_question);
        }
        $referral->update(['status' => Referral::STATUS_CONSENT_REQUESTED, 'reviewed_at' => now()]);
        $this->post(route('adviser.referral.reject', $referral->id), ['rejection_reason' => 'Do not overwrite recorded review.'])->assertSessionHasErrors('rejection_reason');
        $this->assertSame(Referral::STATUS_CONSENT_REQUESTED, $referral->fresh()->status);
        $this->assertNull($referral->fresh()->clarification_question);
        $referral->update(['reviewed_at' => null, 'approved_at' => now()]);
        $this->post(route('adviser.referral.reject', $referral->id), ['rejection_reason' => 'Do not overwrite existing approval.'])->assertSessionHasErrors('rejection_reason');
        $this->assertNotNull($referral->fresh()->approved_at);
        $this->assertNull($referral->fresh()->clarification_question);
        $referral->update(['approved_at' => null]);
        $other = User::factory()->create(['role' => 'adviser']);
        Adviser::create(['user_account_id' => $other->id, 'first_name' => 'Other', 'last_name' => 'Adviser', 'email' => $other->email]);
        $this->actingAs($other)->post(route('adviser.referral.reject', $referral->id), ['rejection_reason' => 'Not authorized for this referral.'])->assertForbidden();
        $this->assertDatabaseCount('supervision_record_versions', 0);
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
         $this->assertSame([
            'Adviser requested clarification',
            'Recommendation before clarification response',
            'Helper clarification submitted',
            'Adviser approved referral review',
        ], \Illuminate\Support\Facades\DB::table('supervision_record_versions')->where('record_type', 'referrals')->where('record_id', $r->id)->orderBy('version')->pluck('reason')->all());
    }
}
