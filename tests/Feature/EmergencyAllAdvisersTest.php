<?php

namespace Tests\Feature;

use App\Events\NotificationsChanged;
use App\Models\Adviser;
use App\Models\ConcernCategory;
use App\Models\EmergencyAlert;
use App\Models\HelpSeeker;
use App\Models\Notification;
use App\Models\Session;
use App\Models\User;
use App\Services\CompactScreening;
use App\Services\EmergencyReviewReminders;
use App\Services\EmergencyStaffNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\SeekerWorkflowFixtures;
use Tests\TestCase;

class EmergencyAllAdvisersTest extends TestCase
{
    use RefreshDatabase, SeekerWorkflowFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['broadcasting.default' => 'null', 'app.enforce_duty_hours' => false]);
    }

    private function adviser(bool $active = true): User
    {
        $user = User::factory()->create(['role' => 'adviser', 'is_active' => $active]);
        Adviser::create(['user_account_id' => $user->id, 'first_name' => 'Reviewer', 'last_name' => (string) $user->id, 'email' => $user->email]);

        return $user;
    }

    private function seeker(): User
    {
        $user = User::factory()->create(['role' => 'seeker']);
        HelpSeeker::create(['user_account_id' => $user->id, 'generated_alias' => 'TestAlias'.$user->id]);
        $this->consentFixture($user);

        return $user;
    }

    public function test_single_yes_immediately_creates_notices_for_every_active_adviser_and_moderator_and_continues_queuing(): void
    {
        $advisers = [$this->adviser(), $this->adviser(), $this->adviser()];
        $inactive = $this->adviser(false);
        $mod = User::factory()->create(['role' => 'moderator']);
        $seeker = $this->seeker();
        $concern = ConcernCategory::firstOrCreate(['concern_name' => 'Test concern'], ['is_active' => true]);
        Event::fake([NotificationsChanged::class]);
        $this->actingAs($seeker)->post(route('request.screening.process'), [
            'screening_form_version' => CompactScreening::SINGLE_VERSION, 'safety_check' => 'yes',
            'concern_id' => $concern->id, 'description' => 'Private original intake narrative.',
        ])->assertRedirect(route('request.preferences'))->assertSessionHasNoErrors();
        $case = Session::firstOrFail();
        $alert = EmergencyAlert::sole();
        foreach ([...$advisers, $mod] as $recipient) {
            $this->assertDatabaseHas('notifications', ['user_account_id' => $recipient->id, 'notification_type' => 'emergency', 'status' => 'unread']);
        }
        $this->assertDatabaseMissing('notifications', ['user_account_id' => $inactive->id]);
        // No per-recipient synchronous network signal is needed before the response.
        Event::assertNotDispatched(NotificationsChanged::class);
        $this->post(route('request.preferences.process'), ['support_mode' => 'chat', 'preferred_language' => 'English'])->assertSessionHasNoErrors();
        $this->assertNotNull($case->fresh()->queue_request_id);
        $this->assertSame('high', $case->fresh()->risk_level);
        $this->assertDatabaseCount('referrals', 0);
        foreach ($advisers as $recipient) {
            $this->actingAs($recipient)->getJson(route('notifications.feed', ['after' => 0]))->assertOk()
                ->assertJsonPath('workflow.emergency_count', 1)->assertJsonPath('fresh.0.type', 'emergency');
            $popup = $this->getJson(route('emergency-notice.next'))->assertOk();
            $this->assertSame(route('adviser.emergencies.show', $alert->id, false), $popup->json('notice.url'));
            $this->get($popup->json('notice.url'))->assertOk();
            $this->get(route('adviser.emergencies'))->assertOk()->assertViewHas('openAlerts', fn ($rows) => $rows->contains('id', $alert->id));
        }
    }

    public function test_shared_alert_does_not_grant_private_session_access_or_resolution_to_another_adviser(): void
    {
        $owner = $this->adviser();
        $other = $this->adviser();
        $seeker = $this->seeker();
        $case = Session::create(['seeker_id' => $seeker->helpSeeker->id, 'review_adviser_id' => $owner->adviser->id, 'created_date' => now(), 'session_status' => 'waiting', 'risk_level' => 'high']);
        $alert = EmergencyAlert::create(['session_id' => $case->id, 'seeker_id' => $case->seeker_id, 'adviser_id' => $owner->adviser->id, 'trigger_reason' => 'Private clinical escalation narrative.']);
        app(EmergencyStaffNotifications::class)->deliver($alert);
        $this->actingAs($other)->get(route('adviser.emergencies.show', $alert))->assertOk()
            ->assertSee('Shared emergency coordination')->assertDontSee('Private clinical escalation narrative.')
            ->assertDontSee('name="resolution_notes"', false)->assertDontSee('Original pre-session screening');
        $this->post(route('adviser.emergencies.resolve', $alert), ['resolution_notes' => 'Another reviewer attempts to close.'])->assertForbidden();
        $this->post(route('adviser.emergencies.action', $alert), ['action' => 'acknowledged', 'notes' => 'Another reviewer attempts to act.'])->assertForbidden();
        $this->assertSame($owner->adviser->id, $alert->fresh()->adviser_id);
        $this->actingAs($owner)->get(route('adviser.emergencies.show', $alert))->assertOk()->assertSee('Private clinical escalation narrative.');
        $alert->update(['status' => 'resolved', 'resolved_at' => now()]);
        $this->actingAs($other)->getJson(route('emergency-notice.next'))->assertJsonPath('notice', null);
        $this->actingAs($other)->get(route('adviser.emergencies.show', $alert))->assertNotFound();
    }

    public function test_existing_open_cases_get_catch_up_without_repeat_popups_or_reminder_configuration(): void
    {
        $owner = $this->adviser();
        $other = $this->adviser();
        $mod = User::factory()->create(['role' => 'moderator']);
        $seeker = $this->seeker();
        $case = Session::create(['seeker_id' => $seeker->helpSeeker->id, 'review_adviser_id' => $owner->adviser->id, 'created_date' => now(), 'session_status' => 'waiting']);
        $alert = EmergencyAlert::create(['session_id' => $case->id, 'seeker_id' => $case->seeker_id, 'adviser_id' => $owner->adviser->id, 'trigger_reason' => 'Legacy open case']);
        config(['emergency.reminder_minutes' => 0]);
        app(EmergencyReviewReminders::class)->run();
        $this->assertSame(3, Notification::where('notification_type', 'emergency')->count());
        $this->assertDatabaseHas('notifications', ['user_account_id' => $other->id, 'link' => route('adviser.emergencies.show', $alert, false)]);
        Notification::where('user_account_id', $other->id)->firstOrFail()->forceFill(['popup_dismissed_at' => now(), 'archived_at' => now()])->save();
        $new = $this->adviser();
        $this->artisan('emergencies:sync-notifications')->assertSuccessful();
        $this->assertDatabaseHas('notifications', ['user_account_id' => $new->id, 'notification_type' => 'emergency']);
        $this->assertSame(4, Notification::withoutGlobalScopes()->where('notification_type', 'emergency')->count());
        $this->artisan('emergencies:sync-notifications')->assertSuccessful();
        $this->assertSame(4, Notification::withoutGlobalScopes()->where('notification_type', 'emergency')->count());
        $this->actingAs($other)->getJson(route('emergency-notice.next'))->assertJsonPath('notice', null);
        $alert->update(['status' => 'resolved', 'resolved_at' => now()]);
        $later = $this->adviser();
        app(EmergencyStaffNotifications::class)->reconcileOpen();
        $this->assertDatabaseMissing('notifications', ['user_account_id' => $later->id]);
    }

    public function test_emergency_notice_skips_synchronous_broadcast_but_regular_notifications_keep_it(): void
    {
        $user = $this->adviser();
        Event::fake([NotificationsChanged::class]);
        DB::transaction(fn () => Notification::create(['user_account_id' => $user->id, 'title' => 'Emergency', 'message' => 'Urgent', 'notification_type' => 'emergency']));
        Event::assertNotDispatched(NotificationsChanged::class);
        DB::transaction(fn () => Notification::create(['user_account_id' => $user->id, 'title' => 'Other update', 'message' => 'Routine', 'notification_type' => 'system']));
        Event::assertDispatched(NotificationsChanged::class, fn ($event) => $event->userId === $user->id);
        $this->actingAs($user)->get(route('adviser.emergencies'))->assertOk()->assertSee('js/staff-emergency-notice.js', false);
    }

    public function test_acknowledged_emergency_gets_one_stalled_progress_reminder_until_another_action(): void
    {
        config(['emergency.reminder_minutes'=>0,'emergency.progress_reminder_minutes'=>15]);
        $owner=$this->adviser();$seeker=$this->seeker();
        User::factory()->create(['role'=>'moderator']);
        $case=Session::create(['seeker_id'=>$seeker->helpSeeker->id,'review_adviser_id'=>$owner->adviser->id,'created_date'=>now(),'session_status'=>'waiting','risk_level'=>'high']);
        $alert=EmergencyAlert::create(['session_id'=>$case->id,'seeker_id'=>$case->seeker_id,'adviser_id'=>$owner->adviser->id,'risk_level'=>'emergency','status'=>'notified','triggered_at'=>now()->subMinutes(30),'trigger_reason'=>'Test coordination','acknowledged_at'=>now()->subMinutes(20),'acknowledged_by'=>$owner->id]);
        $alert->forceFill(['acknowledged_at'=>now()->subMinutes(20),'acknowledged_by'=>$owner->id])->save();
        app(EmergencyReviewReminders::class)->run();app(EmergencyReviewReminders::class)->run();
        $this->assertSame(1,\App\Models\AuditLog::where('action','emergency_progress_reminder')->count());
        DB::table('emergency_review_actions')->insert(['emergency_alert_id'=>$alert->id,'actor_id'=>$owner->id,'action'=>'coordination','notes'=>'Follow-up recorded','created_at'=>now()]);
        $this->travel(16)->minutes();app(EmergencyReviewReminders::class)->run();
        $this->assertSame(2,\App\Models\AuditLog::where('action','emergency_progress_reminder')->count());
        $alert->update(['status'=>'resolved','resolved_at'=>now()]);$this->travel(16)->minutes();app(EmergencyReviewReminders::class)->run();
        $this->assertSame(2,\App\Models\AuditLog::where('action','emergency_progress_reminder')->count());
    }


    public function test_resolved_tab_orders_by_resolution_and_keeps_foreign_history_private(): void
    {
        $owner=$this->adviser();$other=$this->adviser();$seeker=$this->seeker();
        $case=Session::create(['seeker_id'=>$seeker->helpSeeker->id,'review_adviser_id'=>$owner->adviser->id,'created_date'=>now(),'session_status'=>'completed','risk_level'=>'high']);
        $older=EmergencyAlert::create(['session_id'=>$case->id,'seeker_id'=>$case->seeker_id,'adviser_id'=>$owner->adviser->id,'triggered_at'=>now()->subDay(),'trigger_reason'=>'Older case','status'=>'resolved','resolved_at'=>now()->subHours(3),'resolution_notes'=>'Older resolution.']);
        $newer=EmergencyAlert::create(['session_id'=>$case->id,'seeker_id'=>$case->seeker_id,'adviser_id'=>$owner->adviser->id,'triggered_at'=>now()->subDays(5),'trigger_reason'=>'Newly resolved case','status'=>'resolved','resolved_at'=>now()->subHour(),'resolution_notes'=>'New resolution.']);
        $this->actingAs($owner)->get(route('adviser.emergencies',['tab'=>'resolved']))->assertOk()
            ->assertViewHas('tab','resolved')->assertViewHas('resolvedAlerts',fn($rows)=>$rows->first()->id===$newer->id && $rows->perPage()===10)
            ->assertSee('View case')->assertSee('Resolved on')->assertDontSee('Successfully handled');
        $this->get(route('adviser.emergencies',['tab'=>'resolved','q'=>'#'.$older->id]))->assertOk()
            ->assertViewHas('resolvedAlerts',fn($rows)=>$rows->total()===1 && $rows->first()->id===$older->id);
        $this->get(route('adviser.emergencies',['tab'=>'resolved','q'=>'testalias']))->assertOk()
            ->assertViewHas('resolvedAlerts',fn($rows)=>$rows->total()===2);
        $this->get(route('adviser.emergencies.show',$newer))->assertOk()->assertSee('New resolution.')
            ->assertDontSee('name="resolution_notes"',false)->assertDontSee('data-focus-label="Resolve emergency"',false);
        $this->actingAs($other)->get(route('adviser.emergencies',['tab'=>'resolved']))->assertOk()
            ->assertViewHas('resolvedAlerts',fn($rows)=>$rows->total()===0);
    }

}
