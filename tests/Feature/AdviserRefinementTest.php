<?php

namespace Tests\Feature;

use App\Models\{Adviser, EmergencyAlert, Helper, HelperSchedule, HelpSeeker, Notification, Session, SessionReport, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AdviserRefinementTest extends TestCase
{
    use RefreshDatabase;

    private function records(string $name = 'Registered'): array
    {
        $user = User::factory()->create(['role'=>'adviser', 'is_active'=>true]);
        $adviser = Adviser::create(['user_account_id'=>$user->id,'first_name'=>'Assigned','last_name'=>'Adviser','email'=>$user->email]);
        $account = User::factory()->create(['role'=>'helper','name'=>'AccountAlias']);
        $helper = Helper::create(['user_account_id'=>$account->id,'adviser_id'=>$adviser->id,'first_name'=>$name,'last_name'=>'Helper','email'=>$account->email,'competency_level'=>4]);
        $seekerAccount = User::factory()->create(['role'=>'seeker']);
        $seeker = HelpSeeker::create(['user_account_id'=>$seekerAccount->id,'generated_alias'=>'SafeSeeker'.$seekerAccount->id,'age'=>20,'gender'=>'prefer-not-to-say']);
        $session = Session::create(['seeker_id'=>$seeker->id,'helper_id'=>$helper->id,'session_type'=>'chat','session_status'=>'completed','risk_level'=>'low','created_date'=>now()->subHour(),'start_time'=>now()->subMinutes(45),'end_time'=>now()->subMinutes(10)]);
        $report = SessionReport::create(['session_id'=>$session->id,'session_summary'=>'Submitted documentation','adviser_reviewed'=>true,'reviewed_date'=>now()]);
        return [$user,$helper,$session,$report];
    }

    public function test_grouped_reports_use_registered_names_and_status_filters_without_leaking_foreign_helpers(): void
    {
        [$user,$helper] = $this->records();
        $this->records('Foreign');
        $this->actingAs($user)->get(route('adviser.reports',['case_status'=>'completed']))
            ->assertOk()->assertSee('Registered Helper')->assertDontSee('Foreign Helper')->assertDontSee($helper->public_alias)
            ->assertSee('Sessions / Cases')->assertSee('Activity History')
            ->assertViewHas('sessions', fn($sessions)=>$sessions->total()===1);
        $this->get(route('adviser.reports',['tab'=>'performance','case_status'=>'active']))->assertOk()->assertSee('No data')->assertViewHas('metrics',fn($metrics)=>$metrics['total']===0);
        $this->get(route('adviser.reports',['tab'=>'safety']))->assertOk()->assertSee('Emergency case activity')->assertDontSee('Service performance');
        $this->get(route('adviser.reports',['tab'=>'activity']))->assertOk()->assertSee('Your recorded actions')->assertDontSee('Session records</h2>',false);
        $csv=$this->get(route('adviser.reports.export',['case_status'=>'active','format'=>'csv']))->assertOk()->streamedContent();
        $this->assertStringNotContainsString('Registered Helper',$csv);
        $this->getJson(route('adviser.reports',['tab'=>'invalid']))->assertUnprocessable();
        $this->getJson(route('adviser.reports',['from'=>'2026-10-10','to'=>'2026-10-09']))->assertUnprocessable();
    }

    public function test_concluded_reviewed_case_can_be_archived_and_restored_without_changing_its_status(): void
    {
        [$user,$helper,$session,$report] = $this->records();
        $data = ['record_type'=>'session','record_id'=>$session->id];
        $this->actingAs($user)->post(route('adviser.archive.store'),$data)->assertRedirect()->assertSessionHas('success');
        $this->assertNotNull($session->fresh()->archived_at);
        $this->assertDatabaseHas('counseling_sessions',['id'=>$session->id,'session_status'=>'completed']);
        $this->assertDatabaseHas('session_reports',['id'=>$report->id,'adviser_reviewed'=>true]);
        $this->get(route('adviser.reports'))->assertOk()->assertViewHas('sessions',fn($rows)=>$rows->total()===0);
        $this->get(route('adviser.reports',['history'=>'archived']))->assertOk()->assertSee('Restore')->assertViewHas('sessions',fn($rows)=>$rows->total()===1);
        $this->get(route('adviser.evaluations',['history'=>'archived']))->assertOk()->assertViewHas('completedReports',fn($rows)=>$rows->total()===1);
        $this->post(route('adviser.archive.store'),$data+['restore'=>1])->assertRedirect();
        $this->assertNull($session->fresh()->archived_at);
        $this->assertSame('completed',$session->fresh()->session_status);
        $this->assertDatabaseHas('audit_logs',['action'=>'record_archived','target_id'=>$session->id,'user_account_id'=>$user->id]);
        $this->assertDatabaseHas('audit_logs',['action'=>'record_restored','target_id'=>$session->id]);
    }

    public function test_archive_rejects_active_unreviewed_and_emergency_work_and_unrelated_advisers(): void
    {
        [$user,$helper,$session,$report] = $this->records();
        $data = ['record_type'=>'session','record_id'=>$session->id];
        $this->actingAs($user);
        $session = Session::withoutEvents(fn()=>tap($session, fn($s)=>$s->update(['session_status'=>'active'])));
        $this->postJson(route('adviser.archive.store'),$data)->assertUnprocessable();
        Session::withoutEvents(fn()=>$session->update(['session_status'=>'completed']));
        $report->update(['adviser_reviewed'=>false]);
        $this->postJson(route('adviser.archive.store'),$data)->assertUnprocessable();
        $report->update(['adviser_reviewed'=>true]);
        EmergencyAlert::create(['session_id'=>$session->id,'seeker_id'=>$session->seeker_id,'adviser_id'=>$user->adviser->id,'status'=>'open','risk_level'=>'emergency','trigger_reason'=>'Needs attention','triggered_at'=>now()]);
        $this->postJson(route('adviser.archive.store'),$data)->assertUnprocessable();
        [$other] = $this->records('Other');
        $this->actingAs($other)->postJson(route('adviser.archive.store'),$data)->assertForbidden();
        $this->actingAs($helper->user)->postJson(route('adviser.archive.store'),$data)->assertForbidden();
        $this->assertNull($session->fresh()->archived_at);
    }

    public function test_only_past_owned_duty_days_can_be_archived_and_archived_days_cannot_be_edited(): void
    {
        [$user,$helper] = $this->records();
        $past = HelperSchedule::create(['helper_id'=>$helper->id,'date'=>now('Asia/Manila')->subDays(2)->toDateString(),'is_active'=>true,'created_by'=>$user->id]);
        $current = HelperSchedule::create(['helper_id'=>$helper->id,'date'=>now('Asia/Manila')->toDateString(),'is_active'=>true,'created_by'=>$user->id]);
        $this->actingAs($user)->postJson(route('adviser.archive.store'),['record_type'=>'duty','record_id'=>$current->id])->assertUnprocessable();
        $this->post(route('adviser.archive.store'),['record_type'=>'duty','record_id'=>$past->id])->assertRedirect();
        $this->assertTrue($past->fresh()->is_active);
        $this->get(route('adviser.schedule',['date'=>$past->date->toDateString(),'history'=>'archived']))->assertOk()->assertSee('Registered Helper')->assertSee('All day')->assertSee('Past duty')->assertSee('Restore');
        $this->post(route('adviser.schedule.update'),['helper_id'=>$helper->id,'shift_id'=>$past->id,'date'=>now('Asia/Manila')->addDay()->toDateString()])->assertSessionHasErrors('shift_id');
        $this->post(route('adviser.schedule.destroy'),['helper_id'=>$helper->id,'shift_id'=>$past->id,'date'=>$past->date->toDateString()])->assertSessionHasErrors('shift_id');
        [$other] = $this->records('Other');
        $this->actingAs($other)->postJson(route('adviser.archive.store'),['record_type'=>'duty','record_id'=>$past->id,'restore'=>1])->assertForbidden();
    }

    public function test_notification_archive_is_separate_private_and_restorable_with_accurate_badges(): void
    {
        [$user] = $this->records();
        $notification = Notification::create(['user_account_id'=>$user->id,'title'=>'Emergency update','message'=>'An emergency requires your review.','notification_type'=>'emergency','status'=>'unread']);
        $this->actingAs($user)->get(route('adviser.notifications'))->assertOk()->assertSee('Emergency update')->assertSee('Archived Notifications');
        Cache::put('unread_count_'.$user->id,99,60);
        $this->delete(route('adviser.notifications.destroy',$notification->id))->assertRedirect();
        $this->get(route('adviser.notifications'))->assertOk()->assertDontSee('Emergency update');
        $this->get(route('adviser.notifications',['history'=>'archived']))->assertOk()->assertSee('Emergency update')->assertSee('Restore to inbox');
        $this->get(route('adviser.notifications.unread-count'))->assertJson(['count'=>0]);
        [$other] = $this->records('Other');
        $this->actingAs($other)->post(route('adviser.notifications.restore',$notification->id))->assertNotFound();
        $this->actingAs($user)->post(route('adviser.notifications.restore',$notification->id))->assertRedirect();
        $this->get(route('adviser.notifications.unread-count'))->assertJson(['count'=>1]);
        $this->post(route('adviser.notifications.read-all'))->assertRedirect();
        $this->get(route('adviser.notifications.unread-count'))->assertJson(['count'=>0]);
    }

    public function test_resources_have_one_emergency_directory_editor_using_the_existing_save_endpoint(): void
    {
        [$user] = $this->records();
        $this->actingAs($user)->get(route('adviser.resources'))->assertOk()->assertSee('Manage emergency contacts')->assertDontSee('Agency name');
        $this->get(route('adviser.resources',['section'=>'emergency']))->assertOk()->assertSee('Agency name')->assertSee(route('adviser.emergency-resources.save'));
        $this->post(route('adviser.emergency-resources.save'),['agency_name'=>'Verified test contact','hotline'=>'not a telephone','description'=>'Directory entry','status'=>'active','visibility'=>'public'])->assertSessionHasErrors('hotline');
        $this->post(route('adviser.emergency-resources.save'),['agency_name'=>'Verified test contact','hotline'=>'12345','status'=>'active','visibility'=>'public'])->assertRedirect(route('adviser.resources',['section'=>'emergency']));
        $this->assertDatabaseHas('emergency_resources',['agency_name'=>'Verified test contact','hotline'=>'12345']);
        $this->get(route('adviser.resources',['section'=>'emergency']))->assertOk()->assertSee('Verified test contact');
    }
}
