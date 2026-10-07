<?php

namespace Tests\Feature;

use App\Models\Helper;
use App\Models\HelpSeeker;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Session;
use App\Models\SessionReconnection;
use App\Models\User;
use App\Services\SessionDurationService;
use App\Services\SessionReconnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeekerWorkflowFixtures;
use Tests\TestCase;

class SessionReconnectionTest extends TestCase
{
    use RefreshDatabase;
    use SeekerWorkflowFixtures;

    private Session $session;

    private User $helperUser;

    private User $seekerUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfDay()->addHours(11));
        config(['app.relax_duty_hours' => true]);
        $this->helperUser = User::factory()->create(['role' => 'helper', 'is_active' => true]);
        $helper = Helper::create(['user_account_id' => $this->helperUser->id, 'email' => $this->helperUser->email, 'first_name' => 'Original', 'last_name' => 'Helper', 'status' => 'available', 'availability' => 'available', 'competency_level' => 2]);
        $this->verifiedHelperFixture($helper);
        $this->seekerUser = User::factory()->create(['role' => 'seeker', 'is_active' => true]);
        $seeker = HelpSeeker::create(['user_account_id' => $this->seekerUser->id, 'generated_alias' => 'TestSeeker']);
        $this->seekerUser->unsetRelations();
        $this->consentFixture($this->seekerUser);
        $this->session = Session::create(['helper_id' => $helper->id, 'seeker_id' => $seeker->id, 'session_status' => 'active', 'session_type' => 'chat', 'risk_level' => 'low', 'start_time' => now()->subMinutes(10), 'helper_accepted_at' => now()->subMinutes(10), 'created_date' => now()]);
        $this->session->forceFill(['helper_heartbeat_at' => now()->subSeconds(61)])->save();
    }

    private function incident(): SessionReconnection
    {
        app(SessionReconnectionService::class)->detect();

        return SessionReconnection::firstOrFail();
    }

    private function replacement(): User
    {
        $u = User::factory()->create(['role' => 'helper', 'is_active' => true]);
        $h = Helper::create(['user_account_id' => $u->id, 'email' => $u->email, 'first_name' => 'Replacement', 'last_name' => 'Helper', 'status' => 'available', 'availability' => 'available', 'competency_level' => 2]);
        $this->verifiedHelperFixture($h);
        \App\Models\ReadinessCheck::create(['helper_id'=>$h->id,'assessment_date'=>now(),'valid_until'=>now()->addHours(2),'assessment_result'=>'ready','availability_status'=>'available','is_active'=>true]);

        return $u;
    }

    public function test_detection_is_idempotent_and_notifies_moderator(): void
    {
        $m = User::factory()->create(['role' => 'moderator', 'is_active' => true]);
        $this->incident();
        $count = Notification::count();
        app(SessionReconnectionService::class)->detect();
        $this->assertDatabaseCount('session_reconnections', 1);
        $this->assertSame($count, Notification::count());
        $this->assertDatabaseHas('notifications', ['user_account_id' => $m->id, 'title' => 'Helper connection interrupted']);
    }

    public function test_only_owner_can_heartbeat_and_reconnect_cancels_offer(): void
    {
        $i = $this->incident();
        $replacement = $this->replacement();
        $i->update(['status' => 'offered', 'offered_helper_id' => $replacement->helper->id, 'offered_at' => now()]);
        $this->actingAs($replacement)->postJson(route('reconnections.heartbeat', $this->session))->assertForbidden();
        $this->actingAs($this->helperUser)->postJson(route('reconnections.heartbeat', $this->session))->assertNoContent();
        $this->assertSame('reconnected', $i->fresh()->status);
        $this->actingAs($replacement)->postJson(route('reconnections.accept', $i), ['accept' => true])->assertUnprocessable();
    }

    public function test_grace_period_and_duplicate_choice(): void
    {
        $i = $this->incident();
        $this->actingAs($this->seekerUser)->postJson(route('reconnections.choose', $this->session), ['decision' => 'replace'])->assertUnprocessable();
        $this->travel(2)->minutes();
        $this->postJson(route('reconnections.choose', $this->session), ['decision' => 'replace'])->assertOk();
        $count = Notification::count();
        $this->postJson(route('reconnections.choose', $this->session), ['decision' => 'replace'])->assertOk();
        $this->assertSame($count, Notification::count());
        $this->assertSame('requested', $i->fresh()->status);
    }

    public function test_handoff_preserves_deadline_and_isolates_chat(): void
    {
        $i = $this->incident();
        Message::create(['session_id' => $this->session->id, 'sender_id' => $this->seekerUser->id, 'sender' => 'seeker', 'message_text' => 'Private original conversation', 'sent_datetime' => now()]);
        $u = $this->replacement();
        $this->travel(2)->minutes();
        $this->actingAs($this->seekerUser)->postJson(route('reconnections.choose', $this->session), ['decision' => 'replace'])->assertOk();
        $m = User::factory()->create(['role' => 'moderator', 'is_active' => true]);
        $this->actingAs($m)->post(route('reconnections.offer', $i))->assertSessionHasNoErrors();
        $this->assertSame($u->helper->id, $i->fresh()->offered_helper_id);
        $deadline = app(SessionDurationService::class)->state($this->session)['remaining_seconds'];
        $this->actingAs($u)->post(route('reconnections.accept', $i), ['accept' => 1])->assertSessionHasNoErrors();
        $next = Session::findOrFail($i->fresh()->continuation_id);
        $this->assertSame($deadline, app(SessionDurationService::class)->state($next)['remaining_seconds']);
        $this->assertSame('connection_handoff', $this->session->fresh()->completion_reason);
        $this->assertSame(0, $next->messages()->count());
        $this->actingAs($this->helperUser)->getJson(route('reconnections.state', $next))->assertForbidden();
        $this->postJson(route('reconnections.heartbeat', $this->session))->assertStatus(409);
        $this->actingAs($this->seekerUser)->withSession(['session_id' => $this->session->id])->get('/session/chat')->assertOk()->assertViewHas('session', fn ($s) => $s->id === $next->id);
    }

    public function test_emergency_replacement_preserves_original_review_without_copying_messages(): void
    {
        $alert = app(\App\Services\EmergencyEscalationService::class)->escalateEmergency($this->session, $this->session->seeker);
        $i = $this->incident();
        $i->update(['status'=>'requested']);
        $u = $this->replacement();
        $u->helper->update(['competency_level'=>4]);
        $moderator = User::factory()->create(['role'=>'moderator','is_active'=>true]);
        $this->actingAs($moderator)->post(route('reconnections.offer', $i))->assertSessionHasNoErrors();
        $this->assertSame($u->helper->id, $i->fresh()->offered_helper_id);
        $this->actingAs($u)->post(route('reconnections.accept', $i), ['accept'=>1])->assertSessionHasNoErrors();
        $next = Session::findOrFail($i->fresh()->continuation_id);
        $this->assertSame($alert->id, $next->supportEmergencyAlert->id);
        $this->assertTrue($next->permitsEmergencySupport());
        $this->assertSame($this->session->id, $alert->fresh()->session_id);
        $this->assertNull($alert->fresh()->resolved_at);
        $this->assertSame(0, $next->messages()->count());
        $this->assertSame($alert->adviser_id, $next->review_adviser_id);
    }

    public function test_emergency_replacement_decline_does_not_repeat_or_close_review(): void
    {
        $alert = app(\App\Services\EmergencyEscalationService::class)->escalateEmergency($this->session, $this->session->seeker);
        $i = $this->incident();
        $i->update(['status'=>'requested']);
        $u = $this->replacement();
        $u->helper->update(['competency_level'=>4]);
        $m = User::factory()->create(['role'=>'moderator','is_active'=>true]);
        $this->actingAs($m)->post(route('reconnections.offer', $i))->assertSessionHasNoErrors();
        $this->actingAs($u)->post(route('reconnections.accept', $i), ['accept'=>0])->assertSessionHasNoErrors();
        $this->actingAs($m)->postJson(route('reconnections.offer', $i))->assertUnprocessable();
        $this->assertNull($i->fresh()->offered_helper_id);
        $this->assertNull($alert->fresh()->resolved_at);
        $this->assertTrue($this->session->fresh()->isActive());
    }

    public function test_emergency_cannot_be_offered_and_unrelated_roles_blocked(): void
    {
        $i = $this->incident();
        $i->update(['status' => 'requested']);
        $this->session->update(['risk_level' => 'emergency']);
        $m = User::factory()->create(['role' => 'moderator', 'is_active' => true]);
        $this->actingAs($m)->postJson(route('reconnections.offer', $i))->assertUnprocessable();
        $this->actingAs($this->seekerUser)->get('/moderator/reconnections')->assertForbidden();
        $this->actingAs($this->helperUser)->get('/helper/reconnections')->assertOk();
    }

    public function test_expired_offer_returns_to_moderator_and_terminal_is_closed(): void
    {
        $i = $this->incident();
        $i->update(['status' => 'offered', 'offered_helper_id' => $this->replacement()->helper->id, 'offered_at' => now()->subMinutes(3)]);
        app(SessionReconnectionService::class)->detect();
        $this->assertSame('requested', $i->fresh()->status);
        $this->session->update(['session_status' => 'completed']);
        app(SessionReconnectionService::class)->detect();
        $this->assertSame('closed',$i->fresh()->status);
    }
}
