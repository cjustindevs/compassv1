<?php

namespace Tests\Feature;

use App\Models\{ConcernCategory, EmergencyAlert, HelpSeeker, Helper, HelperSchedule, Message, Notification, QueueRequest, ReadinessCheck, ScreeningResponse, Session, User, VoiceCall};
use App\Services\{CompactScreening, HelperMatchingService, NotificationPopupDelivery, SeekerWorkflowService, UnassignedRequestReminders};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Concerns\SeekerWorkflowFixtures;
use Tests\TestCase;

class October21QaWorkflowTest extends TestCase
{
    use RefreshDatabase, SeekerWorkflowFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-10-21 20:00', 'Asia/Manila')->utc());
        Event::fake();
    }

    private function seeker(): User
    {
        $user = User::factory()->create(['role'=>'seeker', 'is_active'=>true]);
        HelpSeeker::create(['user_account_id'=>$user->id, 'generated_alias'=>'QaSeeker'.$user->id]);
        $this->consentFixture($user);
        return $user;
    }

    private function helper(): Helper
    {
        $user = User::factory()->create(['role'=>'helper', 'is_active'=>true]);
        $helper = Helper::create(['user_account_id'=>$user->id, 'first_name'=>'Ready', 'last_name'=>'Helper', 'email'=>$user->email,
            'availability'=>'available', 'status'=>'available', 'competency_level'=>4, 'competency_risk_level'=>4]);
        $this->verifiedHelperFixture($helper);
        HelperSchedule::create(['helper_id'=>$helper->id, 'date'=>now('Asia/Manila')->toDateString(), 'is_active'=>true, 'created_by'=>$user->id]);
        ReadinessCheck::create(['helper_id'=>$helper->id, 'assessment_date'=>now(), 'valid_until'=>now()->addHours(3), 'assessment_result'=>'ready', 'is_active'=>true]);
        return $helper->fresh();
    }

    private function room(User $user, Helper $helper, array $attributes = []): Session
    {
        return Session::create($attributes + ['seeker_id'=>$user->helpSeeker->id, 'helper_id'=>$helper->id, 'risk_level'=>'low',
            'session_type'=>'chat', 'session_status'=>'active', 'workflow_state'=>'session_active', 'start_time'=>now(), 'helper_accepted_at'=>now(), 'submitted_at'=>now()]);
    }

    private function message(Session $session, string $sender): void
    {
        Message::create(['session_id'=>$session->id, 'sender'=>$sender, 'sender_id'=>$sender === 'seeker' ? $session->seeker->user_account_id : $session->helper->user_account_id,
            'message_text'=>'A real conversation message.', 'sent_datetime'=>now()]);
    }

    private function intake(User $user, string $answer): Session
    {
        $concern = ConcernCategory::firstOrCreate(['concern_name'=>'General support'], ['is_active'=>true]);
        return app(SeekerWorkflowService::class)->screen($user, ['suicidal_thoughts'=>$answer],
            ['screening_form_version'=>CompactScreening::SINGLE_VERSION, 'concern_id'=>$concern->id, 'description'=>'I need support with school.']);
    }

    public function test_old_evaluation_pointer_cannot_take_over_new_chat_or_voice(): void
    {
        $user = $this->seeker(); $helper = $this->helper();
        $old = $this->room($user, $helper, ['session_status'=>'completed', 'end_time'=>now()->subHour()]);
        $live = $this->room($user, $helper);
        $this->actingAs($user)->withSession(['session_id'=>$old->id])->get(route('session.chat'))
            ->assertOk()->assertViewIs('session.chat')->assertViewHas('session', fn ($s) => $s->id === $live->id);
        config(['voice.enabled'=>true]); $live->update(['session_type'=>'voice']);
        $this->withSession(['session_id'=>$old->id])->get(route('session.voice'))->assertOk()->assertViewIs('session.voice');
        $this->get(route('session.evaluation', ['session_id'=>$old->id]))->assertOk()->assertViewHas('sessionId', $old->id);
        $this->get(route('session.chat'))->assertOk()->assertViewHas('session', fn ($s) => $s->id === $live->id);
    }

    public function test_explicit_old_tab_cannot_end_or_check_in_to_another_live_session(): void
    {
        $user = $this->seeker(); $helper = $this->helper();
        $old = $this->room($user, $helper, ['session_status'=>'completed', 'end_time'=>now()->subHour()]);
        $live = $this->room($user, $helper);
        $this->actingAs($user)->post(route('session.end', ['session_id'=>$old->id]))
            ->assertRedirect(route('session.evaluation', ['session_id'=>$old->id]));
        $this->postJson(route('session.checkin', ['session_id'=>$old->id]), ['condition'=>'coping_well'])->assertStatus(409);
        $this->assertSame('active', $live->fresh()->session_status);
        $this->get(route('session.chat', ['session_id'=>$this->room($this->seeker(), $helper)->id]))->assertNotFound();
    }

    public function test_cancelled_request_goes_to_history_instead_of_evaluation(): void
    {
        $user = $this->seeker(); $case = $this->room($user, $this->helper(), ['session_status'=>'cancelled']);
        $this->actingAs($user)->get(route('session.chat', ['session_id'=>$case->id]))->assertRedirect(route('seeker.requests.show', $case));
        $this->getJson(route('chat.status', $case->id))->assertJsonPath('session.seeker_redirect', '/seeker/requests');
    }

    public function test_yes_no_and_undisclosed_intakes_can_queue_and_start_without_adviser_action(): void
    {
        foreach (['yes', 'no', 'prefer_not_to_say'] as $answer) {
            $user = $this->seeker(); $helper = $this->helper(); $this->actingAs($user);
            $case = $this->intake($user, $answer);
            $this->assertSame('session_preferences_required', $case->workflow_state);
            if ($answer === 'yes') $case->emergencyAlerts()->update(['status'=>'resolved', 'resolved_at'=>now()]);
            $this->post(route('request.preferences.process'), ['support_mode'=>'chat', 'preferred_language'=>'English'])->assertRedirect(route('request.matching'));
            $case->refresh();
            $this->assertSame($answer === 'yes' ? 'high' : ($answer === 'no' ? 'low' : null), $case->risk_level);
            $this->assertSame($answer === 'yes' ? 'emergency' : ($answer === 'no' ? 'low' : 'high'), $case->queue->priority_level);
            app(HelperMatchingService::class)->processQueueRequest($case->queue);
            $case->refresh();
            $this->assertNotNull($case->helper_id);
            $workflow = app(SeekerWorkflowService::class);
            $workflow->accept($case->helper->user, $case);
            $workflow->start($case->helper->user, $case);
            $this->get(route('session.chat'))->assertOk()->assertViewIs('session.chat');
            $this->assertSame($answer, $case->screeningResponses()->oldest('id')->first()->responses['suicidal_thoughts']);
        }
    }

    public function test_existing_blocked_intake_resumes_preferences_without_another_request(): void
    {
        $user = $this->seeker(); $this->actingAs($user); $case = $this->intake($user, 'prefer_not_to_say');
        $case->update(['session_status'=>'pending_review', 'workflow_state'=>'adviser_review_required']);
        $this->get(route('request.matching'))->assertRedirect(route('request.preferences'));
        $this->assertSame(1, Session::where('seeker_id', $case->seeker_id)->count());
        $this->assertNull($case->fresh()->risk_level);
    }

    public function test_undisclosed_intake_uses_qualified_helpers_without_saving_a_risk_classification(): void
    {
        $helper = $this->helper(); $helper->update(['competency_level'=>1, 'competency_risk_level'=>1]);
        $user = $this->seeker(); $this->actingAs($user); $case = $this->intake($user, 'prefer_not_to_say');
        app(SeekerWorkflowService::class)->submit($user, ['support_mode'=>'chat', 'preferred_language'=>'English']);
        $case->refresh();
        app(HelperMatchingService::class)->processQueueRequest($case->queue);
        $this->assertNull($case->fresh()->helper_id);
        $this->assertNull($case->fresh()->risk_level);
        $this->assertSame('waiting', $case->fresh()->session_status);
        $this->assertNotContains($helper->id, array_column(app(\App\Services\QueueHelperOptions::class)->forRequest($case->queue, [$helper])['helpers'], 'id'));
    }

    public function test_recording_a_parallel_review_does_not_claim_approval_or_rewind_chat(): void
    {
        $helper = $this->helper(); $user = $this->seeker(); $this->actingAs($user); $case = $this->intake($user, 'yes');
        $workflow = app(SeekerWorkflowService::class); $workflow->submit($user, ['support_mode'=>'chat', 'preferred_language'=>'English']);
        $case->refresh(); $workflow->accept($case->helper->user, $case); $workflow->start($case->helper->user, $case);
        $this->actingAs($helper->adviser->user)->post(route('adviser.screenings.review', $case),
            ['risk_level'=>'high', 'review_action'=>'record', 'reason'=>'Documented the review and continued coordination.', 'evidence_source'=>'Original saved screening'])->assertSessionHasNoErrors();
        $this->assertSame('active', $case->fresh()->session_status);
        $this->assertTrue($case->fresh()->requires_adviser_review);
        $this->assertNull($case->fresh()->peer_support_approved_at);
    }

    public function test_adviser_follow_up_and_review_do_not_rewind_live_peer_support(): void
    {
        $helper = $this->helper(); $user = $this->seeker(); $this->actingAs($user);
        $case = $this->intake($user, 'prefer_not_to_say');
        app(SeekerWorkflowService::class)->submit($user, ['support_mode'=>'chat', 'preferred_language'=>'English']);
        $case->refresh();
        $workflow = app(SeekerWorkflowService::class);
        $workflow->accept($case->helper->user, $case); $workflow->start($case->helper->user, $case);
        $case->refresh(); $before = $case->start_time->toIso8601String();
        $this->actingAs($helper->adviser->user)->get(route('adviser.screenings'))->assertOk()->assertSee($user->helpSeeker->generated_alias);
        $this->post(route('adviser.screenings.follow-up', $case), [])->assertSessionHasNoErrors();
        $original = $case->screeningResponses()->oldest('id')->first();
        $this->actingAs($user)->get(route('seeker.requests.show', $case))->assertOk()->assertSee('Send response');
        $this->post(route('request.follow-up.respond', $case), ['screening_id'=>$original->id, 'follow_up_answer'=>'no'])->assertSessionHasNoErrors();
        $this->actingAs($helper->adviser->user)->post(route('adviser.screenings.review', $case),
            ['use_seeker_follow_up'=>1, 'review_action'=>'approve', 'reason'=>'Reviewed the saved response alongside the live session.'])->assertSessionHasNoErrors();
        $this->assertSame('active', $case->fresh()->session_status);
        $this->assertSame('session_active', $case->fresh()->workflow_state);
        $this->assertSame($before, $case->fresh()->start_time->toIso8601String());
        $this->assertSame('prefer_not_to_say', $original->fresh()->responses['suicidal_thoughts']);
    }

    public function test_empty_room_blocks_actions_but_one_message_allows_immediate_emergency(): void
    {
        $helper = $this->helper(); $case = $this->room($this->seeker(), $helper);
        $this->actingAs($helper->user)->getJson(route('session.referral-prompt', $case->id))->assertJsonPath('actions.referral', false)->assertJsonPath('actions.emergency', false);
        $this->postJson(route('helper.session.referral.consent', $case->id), ['summary'=>'Professional support recommended.'])->assertStatus(409);
        $this->postJson(route('helper.session.emergency', $case->id), ['description'=>'Immediate safety disclosure.'])->assertStatus(409);
        $this->message($case, 'seeker');
        $this->getJson(route('session.referral-prompt', $case->id))->assertJsonPath('actions.emergency', true)->assertJsonPath('actions.referral', false);
        $this->postJson(route('helper.session.emergency', $case->id), ['description'=>'Immediate safety disclosure.'])->assertOk();
        $this->message($case, 'helper');
        $this->postJson(route('helper.session.referral.consent', $case->id), ['summary'=>'Professional support recommended.'])->assertOk();
    }

    public function test_connected_voice_counts_but_accepting_or_ringing_does_not(): void
    {
        $helper = $this->helper(); $case = $this->room($this->seeker(), $helper, ['session_type'=>'voice']);
        $call = VoiceCall::create(['id'=>(string) Str::uuid(), 'session_id'=>$case->id, 'caller_user_id'=>$helper->user_account_id,
            'callee_user_id'=>$case->seeker->user_account_id, 'status'=>'connecting', 'accepted_at'=>now()]);
        $this->assertFalse($case->canRecommendReferral()); $this->assertFalse($case->canReportEmergency());
        $call->update(['connected_at'=>now(), 'status'=>'connected']);
        $this->assertTrue($case->canRecommendReferral()); $this->assertTrue($case->canReportEmergency());
        $call->update(['status'=>'ended', 'ended_at'=>now()]); $case->update(['session_status'=>'completed']);
        $this->assertTrue($case->canRecommendReferral()); $this->assertFalse($case->canReportEmergency());
    }

    public function test_schedules_show_two_started_sessions_as_at_capacity(): void
    {
        $helper = $this->helper(); $user = $this->seeker();
        $this->room($user, $helper, ['session_status'=>'completed', 'start_time'=>now()->subHour()]);
        $this->room($user, $helper, ['start_time'=>now()->subMinutes(20)]);
        $this->actingAs(User::factory()->create(['role'=>'moderator', 'is_active'=>true]))->get(route('moderator.schedules'))
            ->assertOk()->assertSee('At Capacity')->assertSee('2/2 sessions');
        $this->assertSame(0, $helper->getRemainingCapacity());
        $future = HelperSchedule::create(['helper_id'=>$helper->id, 'date'=>now()->addDay()->toDateString(), 'is_active'=>true, 'created_by'=>$helper->user_account_id]);
        $this->assertSame('Scheduled', $future->duty_status); $this->assertSame(0, $future->capacity_used);
    }

    public function test_fifteen_minute_reminder_is_per_recipient_once_and_stops_after_assignment(): void
    {
        $user = $this->seeker(); $mod = User::factory()->create(['role'=>'moderator', 'is_active'=>true]);
        $queue = QueueRequest::create(['seeker_id'=>$user->helpSeeker->id, 'request_status'=>'waiting', 'priority_level'=>'low', 'queued_at'=>now(), 'request_date'=>now()->subHour()]);
        $case = Session::create(['seeker_id'=>$queue->seeker_id, 'queue_request_id'=>$queue->id, 'session_status'=>'waiting']);
        EmergencyAlert::create(['session_id'=>$case->id, 'seeker_id'=>$case->seeker_id, 'status'=>'resolved', 'trigger_reason'=>'Resolved synthetic alert']);
        $reminders = app(UnassignedRequestReminders::class); $reminders->run(); $this->assertDatabaseCount('notifications', 0);
        $this->travel(14)->minutes(); $this->travel(59)->seconds(); $reminders->run(); $this->assertDatabaseCount('notifications', 0);
        $this->travel(1)->seconds(); $reminders->run();
        $notice = Notification::where('user_account_id', $mod->id)->sole();
        $delivery = app(NotificationPopupDelivery::class);
        $this->assertTrue($delivery->eligible($notice, $mod));
        $this->assertTrue($delivery->claim($notice->id, $mod)); $this->assertFalse($delivery->claim($notice->id, $mod));
        $notice->archive(); $reminders->run();
        $this->assertSame(1, Notification::withoutGlobalScopes()->where('user_account_id', $mod->id)->count());
        $another = User::factory()->create(['role'=>'moderator', 'is_active'=>true]); $reminders->run();
        $second = Notification::where('user_account_id', $another->id)->sole();
        $this->actingAs($another)->get($second->link)->assertOk()->assertViewHas('queueItems', fn ($items) => $items->count() === 1 && $items[0]->id === $queue->id);
        $queue->update(['request_status'=>'assigned']); $case->update(['session_status'=>'helper_assigned']);
        $this->assertFalse($delivery->actionable($second, $another)); $reminders->run();
        $this->assertSame(2, Notification::withoutGlobalScopes()->count());
    }
}
