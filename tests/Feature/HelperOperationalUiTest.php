<?php

namespace Tests\Feature;

use App\Models\{Helper, HelperSchedule, HelpSeeker, Notification, QueueRequest, ReadinessCheck, ScreeningResponse, Session, User};
use App\Services\{HelperEligibilityService, HelperReadinessService, HelperWorkflowMaintenance};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HelperOperationalUiTest extends TestCase
{
    use RefreshDatabase, \Tests\Concerns\SeekerWorkflowFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-10-15 20:00', 'Asia/Manila')->utc());
    }

    private function helper(string $name = 'Ready'): Helper
    {
        $user = User::factory()->create(['role'=>'helper', 'is_active'=>true]);
        $helper = Helper::create(['user_account_id'=>$user->id, 'first_name'=>$name, 'last_name'=>'Helper', 'email'=>$user->email,
            'status'=>'available', 'availability'=>'available', 'competency_level'=>3, 'competency_risk_level'=>3]);
        $this->verifiedHelperFixture($helper);
        HelperSchedule::create(['helper_id'=>$helper->id, 'date'=>now('Asia/Manila')->toDateString(), 'is_active'=>true, 'created_by'=>$user->id]);
        return $helper->fresh();
    }

    private function supportSession(array $attributes = [], ?HelpSeeker $seeker = null): Session
    {
        $seeker ??= HelpSeeker::create(['user_account_id'=>User::factory()->create(['role'=>'seeker'])->id, 'generated_alias'=>'TestSeeker'.User::count()]);
        return Session::create($attributes + ['seeker_id'=>$seeker->id, 'session_status'=>'waiting', 'session_type'=>'chat', 'risk_level'=>'low', 'created_date'=>now()]);
    }

    private function ready(Helper $helper): void
    {
        ReadinessCheck::create(['helper_id'=>$helper->id, 'assessment_date'=>now(), 'valid_until'=>now()->addHours(2), 'assessment_result'=>'ready', 'is_active'=>true]);
    }

    public function test_workload_is_paginated_searchable_and_scoped_without_truncating_scheduling_choices(): void
    {
        $first = $this->helper('Helper00');
        for ($i=1; $i<12; $i++) $this->helper('Helper'.str_pad($i,2,'0',STR_PAD_LEFT));
        $mod = User::factory()->create(['role'=>'moderator', 'is_active'=>true]);
        $this->actingAs($mod)->get(route('moderator.schedules'))->assertOk()
            ->assertViewHas('workload', fn ($page) => $page->total()===12 && $page->count()===10)
            ->assertViewHas('dutyHelpers', fn ($helpers) => $helpers->count()===12);
        $this->getJson(route('moderator.schedules.workload', ['workload_page'=>2]))->assertOk()->assertJsonCount(2,'helpers')->assertJsonPath('total',12);
        $this->getJson(route('moderator.schedules.workload', ['workload_search'=>'Helper11']))->assertOk()->assertJsonCount(1,'helpers')->assertJsonPath('helpers.0.name','Helper11 Helper');
        $this->actingAs($first->adviser->user)->getJson(route('adviser.schedule.workload'))->assertOk()->assertJsonCount(1,'helpers')->assertJsonPath('helpers.0.id',$first->id);
        $this->actingAs($first->user)->getJson(route('moderator.schedules.workload'))->assertForbidden();
    }

    public function test_future_appointment_preserves_current_readiness_and_queue_choices_but_near_reservation_blocks_overlap(): void
    {
        $helper=$this->helper(); $this->ready($helper);
        $future=$this->supportSession(['helper_id'=>$helper->id, 'session_status'=>'scheduled', 'scheduled_start'=>now()->addDay()]);
        $helper->update(['status'=>'busy']);
        app(HelperWorkflowMaintenance::class)->reconcileHelperAvailability($helper->fresh());
        $this->assertTrue(app(HelperEligibilityService::class)->allows($helper->fresh()));
        $this->assertTrue($helper->fresh()->hasCapacity());
        $this->assertFalse($helper->readinessBlockers()->exists());
        $queue=QueueRequest::create(['seeker_id'=>$future->seeker_id, 'request_status'=>'waiting', 'priority_level'=>'low', 'request_date'=>now()]);
        $this->supportSession(['queue_request_id'=>$queue->id],$future->seeker);
        $mod=User::factory()->create(['role'=>'moderator', 'is_active'=>true]);
        $url=route('moderator.queue.helper-options', ['queue_ids'=>[$queue->id]]);
        $this->actingAs($mod)->getJson($url)->assertOk()->assertJsonPath('requests.0.helpers.0.id',$helper->id);
        $future->update(['scheduled_start'=>now()->addMinutes(45)]);
        $this->getJson($url)->assertOk()->assertJsonCount(0,'requests.0.helpers')->assertJsonPath('requests.0.reason',fn ($reason)=>str_contains($reason,'No eligible Helper'));
        $this->assertFalse($helper->fresh()->hasCapacity());
        $this->assertFalse($helper->readinessBlockers()->exists());
        $future->update(['scheduled_start'=>now()->addMinute()]);
        $this->assertTrue($helper->readinessBlockers()->exists());
        $this->actingAs($helper->user)->getJson($url)->assertForbidden();
    }

    public function test_two_started_sessions_use_the_duty_limit_even_when_completed(): void
    {
        $helper=$this->helper(); $this->ready($helper);
        for ($i=0;$i<2;$i++) $this->supportSession(['helper_id'=>$helper->id,'session_status'=>'completed','start_time'=>now()->subMinutes(60-$i*20)]);
        $this->assertSame(0,$helper->getRemainingCapacity());
        $this->assertFalse($helper->hasCapacity());
        $this->assertContains('The two-session duty-shift limit has been reached.',app(HelperEligibilityService::class)->reasons($helper));
        $this->actingAs($helper->user)->get(route('helper.readiness'))->assertOk()->assertSee('2 / 2');
    }

    public function test_skipped_breathing_is_rejected_and_future_appointment_is_not_released_by_negative_readiness(): void
    {
        $helper=$this->helper();
        $future=$this->supportSession(['helper_id'=>$helper->id,'session_status'=>'helper_assigned','scheduled_start'=>now()->addDay(),'pre_session_brief_expires_at'=>now()->addDay()]);
        $data=['emotionally_ready'=>false,'willing_to_listen'=>true,'stress_level'=>'high','availability_status'=>'not_ready','exercise_completed'=>'skipped','skills_confirmed'=>HelperReadinessService::SKILLS];
        $this->actingAs($helper->user)->get(route('helper.readiness'))->assertOk()->assertDontSee('skipExerciseBtn');
        $this->post(route('helper.readiness.store'),$data)->assertSessionHasErrors('exercise_completed');
        $this->assertDatabaseCount('readiness_checks',0);
        $data['exercise_completed']='completed';
        $this->post(route('helper.readiness.store'),$data)->assertSessionHasNoErrors();
        app(HelperWorkflowMaintenance::class)->releaseUnavailableRecommendations();
        $this->assertSame('not_ready',$helper->fresh()->getCurrentReadiness()->assessment_result);
        $this->assertSame('helper_assigned',$future->fresh()->session_status);
        $this->assertSame($helper->id,$future->fresh()->helper_id);
    }

    public function test_brief_shows_only_assigned_request_concern_and_escapes_description(): void
    {
        $helper=$this->helper();
        $assigned=$this->supportSession(['helper_id'=>$helper->id,'session_status'=>'helper_assigned','concern_category'=>'Academic stress']);
        ScreeningResponse::create(['session_id'=>$assigned->id,'seeker_id'=>$assigned->seeker_id,'responses'=>['description'=>'Concern <script>bad()</script>','custom_concern'=>'Exam stress','severe_distress'=>'yes'],'risk_level'=>'moderate','priority'=>2,'action'=>'queue','reason'=>'Test screening']);
        $other=$this->supportSession([],$assigned->seeker);
        ScreeningResponse::create(['session_id'=>$other->id,'seeker_id'=>$other->seeker_id,'responses'=>['description'=>'PRIVATE OTHER REQUEST'],'risk_level'=>'high','priority'=>1,'action'=>'review','reason'=>'Other request']);
        $this->actingAs($helper->user)->get(route('helper.session.pre-assessment',$assigned->id))->assertOk()
            ->assertSee('Academic stress')->assertSee('Exam stress')->assertSee('Concern &lt;script&gt;bad()&lt;/script&gt;',false)
            ->assertSee('reports severe emotional distress')->assertDontSee('PRIVATE OTHER REQUEST')->assertDontSee('<script>bad()</script>',false);
        $this->actingAs($this->helper('Other')->user)->get(route('helper.session.pre-assessment',$assigned->id))->assertNotFound();
    }

    public function test_unread_referral_is_recovered_after_browser_cursor_advanced_and_is_scoped_to_recipient(): void
    {
        $owner=User::factory()->create(['role'=>'adviser']);
        $other=User::factory()->create(['role'=>'adviser']);
        $notice=Notification::create(['user_account_id'=>$owner->id,'title'=>'Referral review required','message'=>'Review this referral','notification_type'=>'referral','status'=>'unread','link'=>'/adviser/referral/1']);
        Notification::create(['user_account_id'=>$other->id,'title'=>'Other case','message'=>'Private','notification_type'=>'referral','status'=>'unread']);
        $url=route('notifications.feed',['after'=>99999]);
        $this->actingAs($owner)->getJson($url)->assertOk()->assertJsonCount(0,'fresh')->assertJsonCount(1,'attention')->assertJsonPath('attention.0.id',$notice->id)->assertJsonPath('attention.0.important',true);
        $this->postJson('/notifications/'.$notice->id.'/read')->assertOk();
        $this->getJson($url)->assertOk()->assertJsonCount(0,'attention');
    }
}
