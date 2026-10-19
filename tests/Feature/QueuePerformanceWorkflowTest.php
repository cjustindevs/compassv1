<?php

namespace Tests\Feature;

use App\Models\{EmergencyAlert, Helper, HelperSchedule, HelpSeeker, QueueRequest, ReadinessCheck, Session, User};
use App\Services\{HelperDutyEvidence, HelperEligibilityService, HelperMatchingService, QueueAssignmentHistory, QueuePerformance, SeekerWorkflowService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeekerWorkflowFixtures;
use Tests\TestCase;

class QueuePerformanceWorkflowTest extends TestCase
{
    use RefreshDatabase, SeekerWorkflowFixtures;

    protected function setUp(): void
    {
        parent::setUp(); $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-10-19 18:00','Asia/Manila')->utc());
    }

    private function readyHelper(): Helper
    {
        $user=User::factory()->create(['role'=>'helper']);
        $helper=Helper::create(['user_account_id'=>$user->id,'email'=>$user->email,'first_name'=>'Ready','last_name'=>'Helper',
            'availability'=>'available','status'=>'available','competency_level'=>4,'competency_risk_level'=>4]);
        $this->verifiedHelperFixture($helper);
        HelperSchedule::create(['helper_id'=>$helper->id,'date'=>'2026-10-19','shift_start'=>'18:00','shift_end'=>'23:00','is_active'=>true,'created_by'=>$user->id]);
        ReadinessCheck::create(['helper_id'=>$helper->id,'assessment_date'=>now(),'valid_until'=>now()->addHours(4),'assessment_result'=>'ready','is_active'=>true]);
        return $helper->fresh();
    }

    private function waiting(array $data=[]): Session
    {
        $user=User::factory()->create(['role'=>'seeker']);
        $seeker=HelpSeeker::create(['user_account_id'=>$user->id,'generated_alias'=>'QueueDemo'.$user->id]);
        $this->consentFixture($user);
        $queue=QueueRequest::create(['seeker_id'=>$seeker->id,'request_date'=>now(),'queued_at'=>now(),'request_status'=>'waiting','priority_level'=>$data['risk_level']??'low']);
        return Session::create($data+['seeker_id'=>$seeker->id,'queue_request_id'=>$queue->id,'risk_level'=>'low','session_type'=>'chat','session_status'=>'waiting','submitted_at'=>now(),'created_date'=>now()]);
    }

    public function test_approved_high_risk_request_appears_and_matches_without_clearing_original_review_flag(): void
    {
        $helper=$this->readyHelper();
        $session=$this->waiting(['risk_level'=>'high','requires_adviser_review'=>true,'peer_support_approved_at'=>now()]);
        $mod=User::factory()->create(['role'=>'moderator']);
        $this->actingAs($mod)->getJson(route('moderator.queue.helper-options',['queue_ids'=>[$session->queue_request_id]]))
            ->assertOk()->assertJsonPath('requests.0.helpers.0.id',$helper->id);
        $this->assertTrue(app(HelperEligibilityService::class)->allows($helper,$session));
        app(HelperMatchingService::class)->processQueueRequest($session->queue);
        $this->assertSame($helper->id,$session->fresh()->helper_id);
        $this->assertTrue($session->fresh()->requires_adviser_review);
        $blocked=$this->waiting(['risk_level'=>'high','requires_adviser_review'=>true]);
        $this->assertFalse($blocked->permitsPeerMatching());
    }

    public function test_emergency_candidate_endpoint_updates_readiness_and_never_exposes_screening(): void
    {
        $helper=$this->readyHelper();
        $session=$this->waiting(['risk_level'=>'emergency','requires_immediate_action'=>true]);
        $alert=EmergencyAlert::create(['session_id'=>$session->id,'seeker_id'=>$session->seeker_id,'adviser_id'=>$helper->adviser_id,'trigger_reason'=>'Restricted private narrative']);
        $mod=User::factory()->create(['role'=>'moderator']);
        $args=['emergency_alerts',$alert->id];
        $url=route('moderator.emergency.helpers',$args);
        $this->actingAs($mod)->getJson($url)->assertOk()->assertJsonPath('helpers.0.id',$helper->id)->assertDontSee('Restricted private narrative');
        $helper->readinessChecks()->latest('id')->first()->update(['assessment_result'=>'not_ready']);
        $this->getJson($url)->assertOk()->assertJsonCount(0,'helpers')->assertJsonPath('unavailable.0.status','Readiness required');
        $this->post(route('moderator.emergency.assign',$args),['helper_id'=>$helper->id])->assertSessionHasErrors('helper_id');
        $alert->update(['status'=>'resolved','resolved_at'=>now()]);
        $this->getJson($url)->assertOk()->assertJsonCount(0,'helpers');
        $this->actingAs($helper->user)->getJson($url)->assertForbidden();
    }

    public function test_metrics_use_started_waits_final_acceptance_and_verified_duty_including_busy_time(): void
    {
        $helper=$this->readyHelper();
        $this->travel(5)->minutes(); $session=$this->waiting();
        app(HelperMatchingService::class)->manualAssign($session->queue,$helper->id);
        $this->travel(5)->minutes();
        $this->actingAs($helper->user);
        app(SeekerWorkflowService::class)->accept($helper->user,$session->fresh());
        $this->travel(5)->minutes(); app(SeekerWorkflowService::class)->start($helper->user,$session->fresh());
        $this->travel(30)->minutes(); $session->fresh()->update(['session_status'=>'completed','end_time'=>now()]);
        $failed=$this->waiting(); app(QueueAssignmentHistory::class)->processed($failed->queue);
        $this->travelTo(Carbon::parse('2026-10-19 20:00','Asia/Manila')->utc());
        $mod=User::factory()->create(['role'=>'moderator']);
        $this->actingAs($mod)->get(route('queue.performance',['period'=>'today']))->assertOk()
            ->assertViewHas('cards',function($cards){
                $cards=collect($cards)->keyBy('label');
                return $cards['Average Waiting Time']['value']==='10 min'
                    && $cards['Maximum Waiting Time']['value']==='10 min'
                    && $cards['Successful Assignment Rate']['value']==='50%'
                    && $cards['Helper Utilization Rate']['value']==='25%';
            });
        $this->get(route('queue.performance.export',['period'=>'today']))->assertOk()->assertDownload('compass-queue-performance.csv');
        $intervals=DB::table('helper_service_intervals')->where('helper_id',$helper->id)->whereNull('ended_at')->get();
        $this->assertCount(1,$intervals);
        $helper->update(['availability'=>'unavailable']);
        $this->assertSame(0,DB::table('helper_service_intervals')->where('helper_id',$helper->id)->whereNull('ended_at')->count());
    }

    public function test_emergency_and_test_requests_do_not_inflate_regular_assignments_and_after_hours_are_separate(): void
    {
        $helper=$this->readyHelper();
        $regular=$this->waiting(); app(QueueAssignmentHistory::class)->processed($regular->queue);
        $test=$this->waiting(); $test->queue->update(['is_test_record'=>true]); app(QueueAssignmentHistory::class)->processed($test->queue);
        $duplicate=$this->waiting(); $duplicate->queue->update(['duplicate_of_id'=>$regular->queue_request_id]); app(QueueAssignmentHistory::class)->processed($duplicate->queue);
        $emergency=$this->waiting(['risk_level'=>'high','requires_immediate_action'=>true]); app(QueueAssignmentHistory::class)->processed($emergency->queue);
        EmergencyAlert::create(['session_id'=>$emergency->id,'seeker_id'=>$emergency->seeker_id,'adviser_id'=>$helper->adviser_id,
            'trigger_reason'=>'Synthetic test report', 'triggered_at'=>now()])
            ->forceFill(['acknowledged_at'=>now()->addMinutes(3),'acknowledged_by'=>$helper->adviser->user_account_id])->save();
        $outside=$this->waiting(); $outside->queue->update(['queued_at'=>now()->subHours(2)]);
        $outside->update(['helper_id'=>$helper->id,'session_status'=>'active','start_time'=>now()->addMinutes(10)]);
        $this->travel(30)->minutes();
        $mod=User::factory()->create(['role'=>'moderator']);
        $this->actingAs($mod)->get(route('queue.performance',['period'=>'today']))->assertOk()
            ->assertViewHas('cards',fn($cards)=>collect($cards)->firstWhere('label','Successful Assignment Rate')['sample']==='0 accepted / 1 processed regular requests; 1 pending')
            ->assertViewHas('cards',fn($cards)=>collect($cards)->firstWhere('label','Emergency Response Time')['value']==='3 min')
            ->assertViewHas('quality',fn($q)=>$q['after_hours_started']===1 && $q['after_hours_average']===130.0);
    }

    public function test_adviser_report_scope_and_missing_evidence_are_explicit(): void
    {
        $helper=$this->readyHelper(); $other=$this->readyHelper();
        $session=$this->waiting(['helper_id'=>$other->id]);
        $session->update(['session_status'=>'active','start_time'=>now()]);
        $this->actingAs($helper->adviser->user)->get(route('queue.performance',['period'=>'today']))->assertOk()
            ->assertViewHas('cards',fn($cards)=>collect($cards)->firstWhere('label','Average Waiting Time')['value']==='No data');
        $this->actingAs($helper->user)->get(route('queue.performance'))->assertForbidden();
        $mod=User::factory()->create(['role'=>'moderator']);
        $this->actingAs($mod)->get(route('queue.performance',['period'=>'semester']))->assertSessionHasErrors('period');
    }

    public function test_final_referral_rejection_is_separate_from_revision_and_does_not_resolve_emergency(): void
    {
        $helper=$this->readyHelper(); $session=$this->waiting(['helper_id'=>$helper->id]);
        $referral=\App\Models\Referral::create(['session_id'=>$session->id,'helper_id'=>$helper->id,'adviser_id'=>$helper->adviser_id,
            'referral_reason'=>'Synthetic recommendation','referral_date'=>now(),'status'=>'pending_adviser','priority_level'=>'high','help_seeker_consent'=>false]);
        $alert=EmergencyAlert::create(['session_id'=>$session->id,'seeker_id'=>$session->seeker_id,'adviser_id'=>$helper->adviser_id,'referral_id'=>$referral->id,'trigger_reason'=>'Synthetic emergency']);
        $this->actingAs($helper->adviser->user)->get(route('adviser.referral.show',$referral->case_reference))->assertOk()->assertSee('Reject Referral')->assertSee('Request Revision');
        $this->post(route('adviser.referral.decline',$referral->case_reference),['rejection_reason'=>'This recommendation does not meet the documented criteria.'])->assertSessionHasNoErrors();
        $this->assertSame('declined',$referral->fresh()->status);
        $this->assertNull($alert->fresh()->resolved_at);
        $this->assertDatabaseHas('notifications',['user_account_id'=>$helper->user_account_id,'title'=>'Referral declined']);
        $this->post(route('adviser.referral.approve',$referral->case_reference),['review_notes'=>'Do not overwrite the recorded rejection.'])->assertSessionHasErrors('review_notes');
    }

    public function test_single_safety_yes_keeps_original_high_classification_but_receives_emergency_queue_priority(): void
    {
        $this->readyHelper();
        $user=User::factory()->create(['role'=>'seeker']);
        HelpSeeker::create(['user_account_id'=>$user->id,'generated_alias'=>'SafetyDemo']); $this->consentFixture($user);
        $concern=\App\Models\ConcernCategory::firstOrCreate(['concern_name'=>'General support'],['is_active'=>true]);
        $this->actingAs($user);
        $workflow=app(SeekerWorkflowService::class);
        $session=$workflow->screen($user,['suicidal_thoughts'=>'yes'],['screening_form_version'=>\App\Services\CompactScreening::SINGLE_VERSION,'concern_id'=>$concern->id,'description'=>'Synthetic screening concern']);
        $workflow->submit($user,['preferred_language'=>'English','support_mode'=>'chat']);
        $session->refresh();
        $this->assertSame('high',$session->risk_level);
        $this->assertSame('emergency',$session->queue->priority_level);
        $this->assertSame('yes',$session->screeningResponses()->first()->responses['suicidal_thoughts']);
        $alert=$session->emergencyAlerts()->first();$alert->forceFill(['archived_at'=>now()])->save();
        $this->assertFalse($session->fresh()->permitsEmergencySupport());
        $before=\App\Models\Notification::count();app(\App\Services\EmergencyStaffNotifications::class)->deliver($alert);
        $this->assertSame($before,\App\Models\Notification::count());
    }

    public function test_new_emergency_queues_private_staff_email_once_and_reconciliation_does_not_repeat_it(): void
    {
        $helper=$this->readyHelper(); $session=$this->waiting(['risk_level'=>'emergency','requires_immediate_action'=>true]);
        User::factory()->create(['role'=>'moderator']);
        $alert=EmergencyAlert::create(['session_id'=>$session->id,'seeker_id'=>$session->seeker_id,'adviser_id'=>$helper->adviser_id,'trigger_reason'=>'Private narrative must not enter email']);
        app(\App\Services\EmergencyStaffNotifications::class)->deliver($alert);
        $mail=\App\Models\StaffMail::all();
        $this->assertCount(2,$mail);
        $this->assertStringNotContainsString('Private narrative',$mail[0]->payload['message']);
        app(\App\Services\EmergencyStaffNotifications::class)->deliver($alert);
        $this->assertSame(2,\App\Models\StaffMail::count());
        $alert->update(['status'=>'resolved','resolved_at'=>now()]);
        app(\App\Services\EmergencyStaffNotifications::class)->deliver($alert,true);
        $this->assertSame(2,\App\Models\StaffMail::count());
    }
}
