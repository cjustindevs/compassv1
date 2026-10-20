<?php

namespace Tests\Feature;

use App\Events\NotificationsChanged;
use App\Models\{Adviser, EmergencyAlert, HelpSeeker, Helper, Notification, Referral, Session, User};
use App\Services\EmergencyStaffNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Event};
use Tests\TestCase;

class NotificationPopupDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private function notice(User $user, array $values = []): Notification
    {
        Event::fake([NotificationsChanged::class]);
        return Notification::create(array_merge(['user_account_id'=>$user->id, 'title'=>'Review referral',
            'message'=>'A new recommendation needs review.', 'notification_type'=>'referral', 'link'=>'/notifications', 'status'=>'unread'], $values));
    }

    public function test_delivery_is_once_per_recipient_and_event_across_tabs_and_devices_without_marking_read(): void
    {
        $user = User::factory()->create(['role'=>'adviser']);
        $one = $this->notice($user, ['popup_event_key'=>'referral:review:version:1']);
        $duplicate = $this->notice($user, ['popup_event_key'=>'referral:review:version:1']);
        $this->actingAs($user)->postJson(route('notifications.present', $one))->assertOk()->assertJsonPath('present', true);
        $this->postJson(route('notifications.present', $one))->assertJsonPath('present', false);
        $this->postJson(route('notifications.present', $duplicate))->assertJsonPath('present', false);
        $this->getJson(route('notifications.feed'))->assertJsonPath('count', 2)->assertJsonCount(0, 'attention');
        $this->assertSame('unread', $one->fresh()->status);
        $this->assertDatabaseCount('notification_popup_deliveries', 1);
        $other = User::factory()->create(['role'=>'adviser']); $otherNotice = $this->notice($other);
        $this->actingAs($other)->postJson(route('notifications.present', $otherNotice))->assertJsonPath('present', true);
        $this->postJson(route('notifications.present', $one))->assertNotFound();
        $this->assertDatabaseCount('notification_popup_deliveries', 2);
    }

    public function test_new_business_updates_with_identical_wording_still_have_independent_delivery(): void
    {
        $user = User::factory()->create(['role'=>'seeker']);
        $one = $this->notice($user); $two = $this->notice($user);
        $this->actingAs($user)->postJson(route('notifications.present', $one))->assertJsonPath('present', true);
        $this->postJson(route('notifications.present', $two))->assertJsonPath('present', true);
    }

    public function test_old_read_archived_and_inactive_notices_never_popup_but_history_is_preserved(): void
    {
        $user = User::factory()->create(['role'=>'helper']);
        $old = $this->notice($user); $old->forceFill(['created_at'=>now()->subMinutes(6)])->save();
        $read = $this->notice($user, ['status'=>'read']);
        $archived = $this->notice($user); $archived->archive();
        $this->actingAs($user)->postJson(route('notifications.present', $old))->assertJsonPath('present', false);
        $this->postJson(route('notifications.present', $read))->assertJsonPath('present', false);
        $this->postJson(route('notifications.present', $archived))->assertNotFound();
        $this->getJson(route('notifications.feed'))->assertJsonPath('count', 1)->assertJsonCount(0, 'fresh')->assertJsonCount(2, 'recent');
        $fresh = $this->notice($user); $user->update(['is_active'=>false]);
        $this->postJson(route('notifications.present', $fresh))->assertForbidden();
        $this->assertDatabaseCount('notification_popup_deliveries', 0);
    }

    public function test_completed_referral_review_and_reassigned_helper_alerts_stay_quiet_but_completion_updates_can_popup(): void
    {
        $user = User::factory()->create(['role'=>'helper']);
        $helper = Helper::create(['user_account_id'=>$user->id,'first_name'=>'Peer','last_name'=>'Helper','email'=>$user->email]);
        $seekerUser = User::factory()->create(['role'=>'seeker']);
        $seeker = HelpSeeker::create(['user_account_id'=>$seekerUser->id,'generated_alias'=>'DemoSeeker']);
        $session = Session::create(['seeker_id'=>$seeker->id,'helper_id'=>$helper->id,'session_status'=>'active']);
        $referral = Referral::create(['session_id'=>$session->id,'helper_id'=>$helper->id,'status'=>'completed','referral_reason'=>'Demo recommendation']);
        $review = $this->notice($user, ['link'=>'/helper/referral/'.$referral->id]);
        $completed = $this->notice($user, ['title'=>'Referral completion confirmed','link'=>'/helper/referral/'.$referral->id]);
        $assignment = $this->notice($user, ['notification_type'=>'assignment','link'=>'/helper/session/'.$session->id.'/pre-assessment']);
        $session->update(['helper_id'=>null]);
        $this->actingAs($user)->postJson(route('notifications.present', $review))->assertJsonPath('present', false);
        $this->postJson(route('notifications.present', $assignment))->assertJsonPath('present', false);
        $this->postJson(route('notifications.present', $completed))->assertJsonPath('present', true);
    }

    public function test_migration_silences_historical_popups_without_deleting_or_reading_notifications(): void
    {
        $migration = require database_path('migrations/2026_10_20_000001_add_notification_popup_delivery.php');
        $migration->down();
        $user = User::factory()->create();
        $id = DB::table('notifications')->insertGetId(['user_account_id'=>$user->id, 'title'=>'Historical alert',
            'message'=>'Keep this history', 'notification_type'=>'system', 'status'=>'unread', 'created_at'=>now(), 'updated_at'=>now()]);
        $migration->up();
        $this->assertDatabaseHas('notifications', ['id'=>$id, 'status'=>'unread', 'message'=>'Keep this history', 'read_at'=>null]);
        $this->assertNotNull(Notification::findOrFail($id)->popup_presented_at);
        $this->assertDatabaseCount('notification_popup_deliveries', 1);
    }

    public function test_new_emergency_report_supersedes_old_popup_then_a_later_report_can_alert_once_again(): void
    {
        Event::fake([NotificationsChanged::class]);
        $mod = User::factory()->create(['role'=>'moderator']);
        $adviserUser = User::factory()->create(['role'=>'adviser']);
        $adviser = Adviser::create(['user_account_id'=>$adviserUser->id,'first_name'=>'Demo','last_name'=>'Reviewer','email'=>$adviserUser->email]);
        $seekerUser = User::factory()->create(['role'=>'seeker']);
        $seeker = HelpSeeker::create(['user_account_id'=>$seekerUser->id,'generated_alias'=>'PrivateFox']);
        $session = Session::create(['seeker_id'=>$seeker->id,'session_status'=>'active','risk_level'=>'emergency','review_adviser_id'=>$adviser->id]);
        $alert = EmergencyAlert::create(['seeker_id'=>$seeker->id,'session_id'=>$session->id,'adviser_id'=>$adviser->id,'trigger_reason'=>'Demo concern']);
        $delivery = app(EmergencyStaffNotifications::class);
        $delivery->deliver($alert); $delivery->deliver($alert, true);
        $popup = $this->actingAs($mod)->getJson(route('emergency-notice.next'))->assertOk()->json('notice');
        $this->postJson($popup['present_url'])->assertJsonPath('present', true);
        $this->notice($mod, ['title'=>'Emergency acknowledgment pending','notification_type'=>'emergency',
            'emergency_alert_id'=>$alert->id,'popup_event_key'=>'emergency:alert:'.$alert->id,'link'=>'/moderator/emergency']);
        $this->getJson(route('emergency-notice.next'))->assertJsonPath('notice', null);
        $this->getJson(route('emergency-notice.next', ['current'=>$popup['reference']]))->assertJsonPath('notice.id', $popup['id']);
        $delivery->deliver($alert, true);
        $next = $this->getJson(route('emergency-notice.next'))->json('notice');
        $this->assertNotSame($popup['popup_key'], $next['popup_key']);
        $this->postJson($next['present_url'])->assertJsonPath('present', true);
        $this->postJson($next['present_url'])->assertJsonPath('present', false);
        $this->assertSame('active', $session->fresh()->session_status);
        $this->assertSame(4, Notification::where('user_account_id', $mod->id)->unread()->count());
    }
}
