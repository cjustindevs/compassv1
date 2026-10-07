<?php
namespace Tests\Feature;
use App\Models\{User,Helper,Notification,ReadinessCheck};
use App\Services\HelperEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class SystemCorrectionsTest extends TestCase {
 use RefreshDatabase;
 use \Tests\Concerns\SeekerWorkflowFixtures;
 public function test_readiness_cannot_be_bypassed_by_testing_mode():void {
 config(['app.relax_duty_hours'=>true]);
 $u=User::factory()->create(['role'=>'helper','is_active'=>true]);
 $h=Helper::create(['user_account_id'=>$u->id,'first_name'=>'Test','last_name'=>'Helper','email'=>$u->email]);$this->verifiedHelperFixture($h);
 $service=app(HelperEligibilityService::class);
 $this->assertContains('A current passed readiness check is required.',$service->reasons($h));
 $r=ReadinessCheck::create(['helper_id'=>$h->id,'assessment_date'=>now(),'valid_until'=>now()->addHour(),'assessment_result'=>'not_ready','is_active'=>true]);
 $this->assertContains('A current passed readiness check is required.',$service->reasons($h));
 $r->update(['assessment_result'=>'ready']);$this->assertNotContains('A current passed readiness check is required.',$service->reasons($h));
 $r->update(['valid_until'=>now()->subMinute()]);$this->assertContains('A current passed readiness check is required.',$service->reasons($h));
 }
 public function test_notification_archive_retains_record_and_excludes_default_queries():void {
 $u=User::factory()->create(['role'=>'moderator','is_active'=>true]);
 $n=Notification::create(['user_account_id'=>$u->id,'title'=>'Review needed','message'=>'Open your queue','notification_type'=>'system','status'=>'unread']);
 $this->actingAs($u)->deleteJson(route('moderator.notifications.destroy',$n->id))->assertOk();
 $this->assertDatabaseHas('notifications',['id'=>$n->id]);$this->assertNotNull(Notification::withoutGlobalScope('unarchived')->findOrFail($n->id)->archived_at);
 $this->assertNull(Notification::find($n->id));$this->assertDatabaseHas('audit_logs',['action'=>'notification_archived']);
 }
 public function test_notification_archive_rejects_another_users_record():void {
 $u=User::factory()->create(['role'=>'moderator','is_active'=>true]);$other=User::factory()->create();
 $n=Notification::create(['user_account_id'=>$other->id,'title'=>'Private','message'=>'Private','notification_type'=>'system']);
 $this->actingAs($u)->deleteJson(route('moderator.notifications.destroy',$n->id))->assertNotFound();$this->assertDatabaseHas('notifications',['id'=>$n->id,'archived_at'=>null]);
 }
 public function test_moderator_cannot_resolve_an_emergency():void {
 $u=User::factory()->create(['role'=>'moderator','is_active'=>true]);
 $this->actingAs($u)->post(route('moderator.emergency.resolve',999))->assertForbidden();
 }
 public function test_moderator_cannot_escalate_through_either_endpoint():void {
 $u=User::factory()->create(['role'=>'moderator','is_active'=>true]);
 $incident=\App\Models\IncidentReport::create(['user_account_id'=>$u->id,'incident_category'=>'emergency_flag','description'=>'Support review needed','risk_level'=>'emergency','status'=>'open']);
 $this->actingAs($u)->post(route('moderator.emergency.escalate',$incident->id))->assertForbidden();
 $this->postJson(route('incidents.escalate',$incident),['escalated_to'=>$u->id,'reason'=>'Attempted escalation'])->assertForbidden();
 $this->assertSame('open',$incident->fresh()->status);
 $this->assertNull($incident->fresh()->escalated_at);
 $this->assertDatabaseCount('notifications',0);
 $this->get(route('moderator.emergency'))->assertOk()->assertDontSee(route('moderator.emergency.escalate',$incident->id),false);
 }
 public function test_concern_management_is_authorized_and_inactive_categories_are_retained():void {
 $admin=User::factory()->create(['role'=>'admin','is_active'=>true]);
 $this->actingAs($admin)->post(route('concerns.store'),['concern_name'=>'New concern','description'=>'Support category','is_active'=>1])->assertSessionHasNoErrors();
 $c=\App\Models\ConcernCategory::where('concern_name','New concern')->firstOrFail();
 $this->patch(route('concerns.update',$c),['concern_name'=>'New concern','is_active'=>0])->assertSessionHasNoErrors();
 $this->assertDatabaseHas('concern_categories',['id'=>$c->id,'is_active'=>false]);$this->assertFalse(\App\Models\ConcernCategory::active()->whereKey($c->id)->exists());
 $this->post(route('concerns.store'),['concern_name'=>'new concern','is_active'=>1])->assertSessionHasErrors('concern_name');
 $this->get(route('concerns.manage'))->assertOk();
 $other=User::factory()->create(['role'=>'helper','is_active'=>true]);$this->actingAs($other)->post(route('concerns.store'),['concern_name'=>'Forbidden','is_active'=>1])->assertForbidden();
 }
 public function test_emergency_popup_dismissal_keeps_notification_and_checks_ownership():void {
 $u=User::factory()->create(['role'=>'moderator','is_active'=>true]);$n=Notification::create(['user_account_id'=>$u->id,'title'=>'Emergency','message'=>'Private narrative must not appear','notification_type'=>'emergency']);
 $r=$this->actingAs($u)->getJson(route('emergency-notice.next'))->assertOk()->assertDontSee('Private narrative');
 $this->postJson(route('emergency-notice.dismiss'),['reference'=>$r->json('notice.reference')])->assertNoContent();
 $this->assertNotNull(Notification::find($n->id));$this->getJson(route('emergency-notice.next'))->assertJsonPath('notice',null);
 $other=User::factory()->create(['role'=>'moderator','is_active'=>true]);$this->actingAs($other)->postJson(route('emergency-notice.dismiss'),['reference'=>$r->json('notice.reference')])->assertNotFound();
 }
 public function test_admin_cannot_deactivate_helper_with_active_session():void {
 $admin=User::factory()->create(['role'=>'admin','is_active'=>true]);$u=User::factory()->create(['role'=>'helper','is_active'=>true]);
 $h=Helper::create(['user_account_id'=>$u->id,'email'=>$u->email,'first_name'=>'Test','last_name'=>'Helper']);
 $seekerUser=User::factory()->create(['role'=>'seeker']);$seeker=\App\Models\HelpSeeker::create(['user_account_id'=>$seekerUser->id,'generated_alias'=>'SafeAlias']);
 \App\Models\Session::create(['helper_id'=>$h->id,'seeker_id'=>$seeker->id,'session_status'=>'active','start_time'=>now()]);
 $this->actingAs($admin)->post(route('admin.users.deactivate',$u))->assertSessionHasErrors('account');$this->assertTrue($u->fresh()->is_active);
 }
 public function test_admin_can_deactivate_account_without_obligations():void {
 $admin=User::factory()->create(['role'=>'admin','is_active'=>true]);$u=User::factory()->create(['role'=>'helper','is_active'=>true]);
 Helper::create(['user_account_id'=>$u->id,'email'=>$u->email,'first_name'=>'Test','last_name'=>'Helper']);
 $this->actingAs($admin)->post(route('admin.users.deactivate',$u))->assertSessionHasNoErrors();$this->assertFalse($u->fresh()->is_active);$this->assertDatabaseHas('audit_logs',['action'=>'account_deactivated']);
 }
 public function test_emergency_is_separate_and_rejection_is_audited_without_resolution():void {
 \Illuminate\Support\Facades\Event::fake();
 $u=User::factory()->create(['role'=>'helper','is_active'=>true]);$h=Helper::create(['user_account_id'=>$u->id,'email'=>$u->email,'first_name'=>'Test','last_name'=>'Helper']);$this->verifiedHelperFixture($h);$h->refresh();
 $su=User::factory()->create(['role'=>'seeker']);$seeker=\App\Models\HelpSeeker::create(['user_account_id'=>$su->id,'generated_alias'=>'EmergencyAlias']);
 $session=\App\Models\Session::create(['helper_id'=>$h->id,'seeker_id'=>$seeker->id,'session_status'=>'active','start_time'=>now()]);
 $this->actingAs($u);$alert=app(\App\Services\EmergencyEscalationService::class)->escalateEmergency($session,$seeker,['reason'=>'Observed immediate safety concern','preserve_classification'=>true]);
 $this->assertDatabaseCount('referrals',0);$this->assertSame('Observed immediate safety concern',$alert->trigger_reason);
 $adviser=$h->adviser->user;
 $this->actingAs($adviser)->post(route('adviser.emergencies.action',$alert),['action'=>'rejected','notes'=>'Clarify observations and follow the support protocol.'])->assertSessionHasNoErrors();
 $alert->refresh();$this->assertSame('rejected',$alert->review_decision);$this->assertNull($alert->resolved_at);$this->assertSame($adviser->id,$alert->rejected_by);
 $this->post(route('adviser.emergencies.action',$alert),['action'=>'rejected','notes'=>'Repeated decision'])->assertStatus(409);
 $this->assertDatabaseHas('audit_logs',['action'=>'emergency_rejected']);
 $this->actingAs($su)->post(route('adviser.emergencies.resolve',$alert),['resolution_notes'=>'Attempted self resolution'])->assertForbidden();
 }
 public function test_helper_declares_date_only_duty_after_readiness():void {
 $u=User::factory()->create(['role'=>'helper','is_active'=>true]);$h=Helper::create(['user_account_id'=>$u->id,'email'=>$u->email,'first_name'=>'Test','last_name'=>'Helper']);$this->verifiedHelperFixture($h);
 $data=['date'=>now('Asia/Manila')->toDateString()];
 $this->actingAs($u)->post(route('helper.duty.declare'),$data)->assertSessionHasErrors('date');
 ReadinessCheck::create(['helper_id'=>$h->id,'assessment_date'=>now(),'valid_until'=>now()->addHours(2),'assessment_result'=>'ready','availability_status'=>'available','is_active'=>true]);
 $this->post(route('helper.duty.declare'),$data)->assertSessionHasNoErrors();
 $this->assertDatabaseHas('helper_schedules',['helper_id'=>$h->id,'shift_start'=>null,'shift_end'=>null]);
 $this->post(route('helper.duty.declare'),$data)->assertSessionHasErrors('date');
 }
 public function test_adviser_reports_and_analytics_render_separately():void {
 $u=User::factory()->create(['role'=>'adviser','is_active'=>true]);\App\Models\Adviser::create(['user_account_id'=>$u->id,'first_name'=>'Test','last_name'=>'Adviser','email'=>$u->email]);
 $this->actingAs($u)->get(route('adviser.analytics'))->assertOk()->assertSee('Monthly session activity')->assertDontSee('<h2 class="font-semibold mb-3">Session records</h2>',false);
 $this->get(route('adviser.reports'))->assertOk()->assertSee('Session records')->assertDontSee('Monthly session activity');
 }
}
