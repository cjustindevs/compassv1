<?php

namespace Tests\Feature;

use App\Models\Adviser;
use App\Models\CalendarEvent;
use App\Models\EmergencyAlert;
use App\Models\Helper;
use App\Models\HelpSeeker;
use App\Models\Moderator;
use App\Models\QueueRequest;
use App\Models\ReadinessCheck;
use App\Models\Referral;
use App\Models\Session;
use App\Models\SessionReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModeratorAdviserModuleTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\SeekerWorkflowFixtures;

    public function test_queue_explains_why_a_created_helper_cannot_be_assigned(): void
    {
        [$user] = $this->moderatorUser();
        $helper = $this->helper();
        $this->actingAs($user)->get(route('moderator.queue'))->assertOk()
            ->assertViewHas('availableHelpers', function ($helpers) use ($helper) {
                $row = $helpers->firstWhere('id', $helper->id);

                return $row !== null
                    && $row->assignable === false
                    && $row->assignment_reason === 'Pending verification'
                    && $row->assignment_detail === 'Institutional verification and training approval are required.';
            });
    }

    public function test_screening_reviews_are_scoped_to_the_assigned_adviser(): void
    {
        [$ownerUser, $owner] = $this->adviserUser('screen-owner@example.com');
        [$otherUser] = $this->adviserUser('screen-other@example.com');
        [$seeker] = $this->seeker();
        $session = Session::create(['seeker_id' => $seeker->id, 'session_type' => 'chat', 'session_status' => 'pending_review', 'workflow_state' => 'adviser_review_required', 'review_adviser_id' => $owner->id, 'requires_adviser_review' => true, 'completion_status' => 'pending', 'created_date' => now()]);

        $this->actingAs($otherUser)->get(route('adviser.screenings'))->assertOk()
            ->assertViewHas('sessions', fn ($sessions) => $sessions->doesntContain('id', $session->id));
        $this->actingAs($otherUser)->post(route('adviser.screenings.review', $session), ['risk_level' => 'moderate', 'reason' => 'Not my review.', 'evidence_source' => 'None', 'allow_peer_support' => 1])->assertForbidden();

        $this->actingAs($ownerUser)->get(route('adviser.screenings'))->assertOk()
            ->assertViewHas('sessions', fn ($sessions) => $sessions->contains('id', $session->id));
    }

    public function test_cancelled_closed_sessions_are_not_listed_and_resolve_review_error_gracefully(): void
    {
        [$ownerUser, $owner] = $this->adviserUser('closed-screen@example.com');
        [$seeker] = $this->seeker();
        $session = Session::create(['seeker_id' => $seeker->id, 'session_type' => 'chat', 'session_status' => 'cancelled', 'workflow_state' => 'closed', 'review_adviser_id' => $owner->id, 'requires_adviser_review' => true, 'completion_status' => 'cancelled', 'created_date' => now()]);

        $this->actingAs($ownerUser)->get(route('adviser.screenings'))->assertOk()
            ->assertViewHas('sessions', fn ($sessions) => $sessions->doesntContain('id', $session->id));

        $this->actingAs($ownerUser)->from(route('adviser.screenings'))
            ->post(route('adviser.screenings.review', $session), ['risk_level' => 'moderate', 'reason' => 'Too late to review.', 'evidence_source' => 'None', 'allow_peer_support' => 1])
            ->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseMissing('screening_responses', ['session_id' => $session->id]);
        $this->assertNull($session->fresh()->risk_level);
    }

    public function test_reviewing_adviser_can_read_the_chat_conversation_for_a_screening_review(): void
    {
        [$ownerUser, $owner] = $this->adviserUser('conv-owner@example.com');
        $helper = $this->helper($owner);
        [$seeker] = $this->seeker();
        $session = Session::create(['seeker_id' => $seeker->id, 'helper_id' => $helper->id, 'session_type' => 'chat', 'session_status' => 'completed', 'workflow_state' => 'adviser_review_required', 'review_adviser_id' => $owner->id, 'requires_adviser_review' => true, 'completion_status' => 'completed', 'created_date' => now()]);
        \App\Models\Message::create(['session_id' => $session->id, 'sender_id' => $helper->user_account_id, 'sender' => 'helper', 'message_text' => 'Sent an assessment tip.', 'transcript' => 'Sent an assessment tip.', 'is_transcript' => true, 'sent_datetime' => now()->subMinutes(5)]);
        \App\Models\Message::create(['session_id' => $session->id, 'sender_id' => $seeker->user_account_id, 'sender' => 'seeker', 'message_text' => 'Your suggestion helped, thank you.', 'transcript' => 'Your suggestion helped, thank you.', 'is_transcript' => true, 'sent_datetime' => now()->subMinutes(2)]);

        \App\Models\ConsentRecord::create(['seeker_id'=>$session->seeker_id,'session_id'=>$session->id,'purpose'=>'transcription','document_type'=>'informed_consent','version'=>\App\Services\ConsentService::VERSION,'decision'=>'accepted','consent_given'=>true,'withdrawn'=>false,'consent_date'=>now()]);
        $this->actingAs($ownerUser)->post(route('adviser.transcript.access',$session->id),['purpose'=>'incident_investigation','reason'=>'Investigate a documented screening concern.'])->assertOk();
        $this->actingAs($ownerUser)->get(route('adviser.screenings.conversation', $session))->assertOk()
            ->assertSee('Sent an assessment tip.')
            ->assertSee('Your suggestion helped, thank you.')
            ->assertSee($session->seeker->generated_alias)
            ->assertSee($helper->public_alias)
            ->assertDontSee('Helen');
        $this->assertDatabaseHas('audit_logs', ['action' => 'screening_conversation_viewed', 'target_id' => $session->id]);
        $this->assertSame(1, \App\Models\ConsentRecord::count());
    }

    public function test_conversation_access_follows_helper_supervision_for_a_reassessment(): void
    {
        [$ownerUser, $owner] = $this->adviserUser('conv-helper-owner@example.com');
        $helper = $this->helper($owner);
        [$seeker] = $this->seeker();
        $session = Session::create(['seeker_id' => $seeker->id, 'helper_id' => $helper->id, 'session_type' => 'chat', 'session_status' => 'completed', 'requires_adviser_review' => true, 'completion_status' => 'completed', 'created_date' => now()]);
        \App\Models\Message::create(['session_id' => $session->id, 'sender_id' => $helper->user_account_id, 'sender' => 'helper', 'message_text' => 'Helper reassessment note.', 'transcript' => 'Helper reassessment note.', 'is_transcript' => true, 'sent_datetime' => now()]);

        \App\Models\ConsentRecord::create(['seeker_id'=>$session->seeker_id,'session_id'=>$session->id,'purpose'=>'transcription','document_type'=>'informed_consent','version'=>\App\Services\ConsentService::VERSION,'decision'=>'accepted','consent_given'=>true,'withdrawn'=>false,'consent_date'=>now()]);
        $this->actingAs($ownerUser)->post(route('adviser.transcript.access',$session->id),['purpose'=>'incident_investigation','reason'=>'Investigate a documented screening concern.'])->assertOk();
        $this->actingAs($ownerUser)->get(route('adviser.screenings.conversation', $session))
            ->assertOk()->assertSee('Helper reassessment note.');
    }

    public function test_conversation_access_is_limited_to_the_reviewing_adviser(): void
    {
        [$ownerUser, $owner] = $this->adviserUser('conv-owner-b@example.com');
        [$otherUser] = $this->adviserUser('conv-other-b@example.com');
        $helper = $this->helper($owner);
        [$seeker] = $this->seeker();
        $session = Session::create(['seeker_id' => $seeker->id, 'helper_id' => $helper->id, 'session_type' => 'chat', 'session_status' => 'completed', 'workflow_state' => 'adviser_review_required', 'review_adviser_id' => $owner->id, 'requires_adviser_review' => true, 'completion_status' => 'completed', 'created_date' => now()]);
        \App\Models\Message::create(['session_id' => $session->id, 'sender_id' => $helper->user_account_id, 'sender' => 'helper', 'message_text' => 'Private note.', 'transcript' => 'Private note.', 'is_transcript' => true, 'sent_datetime' => now()]);

        \App\Models\ConsentRecord::create(['seeker_id'=>$session->seeker_id,'session_id'=>$session->id,'purpose'=>'transcription','document_type'=>'informed_consent','version'=>\App\Services\ConsentService::VERSION,'decision'=>'accepted','consent_given'=>true,'withdrawn'=>false,'consent_date'=>now()]);
        $this->actingAs($ownerUser)->post(route('adviser.transcript.access',$session->id),['purpose'=>'incident_investigation','reason'=>'Investigate a documented screening concern.'])->assertOk();
        $this->actingAs($ownerUser)->get(route('adviser.screenings.conversation', $session))->assertOk();
        $this->actingAs($otherUser)->get(route('adviser.screenings.conversation', $session))->assertForbidden();
    }

    public function test_adviser_schedule_save_displays_the_saved_date_and_updates_an_existing_day(): void
    {
        [$user, $adviser] = $this->adviserUser('schedule@example.com');
        $helper = $this->helper($adviser);
        $date = now()->addDays(2)->toDateString();
        $payload = ['helper_id' => $helper->id, 'date' => $date];

        $this->actingAs($user)->from(route('adviser.schedule'))->post(route('adviser.schedule.update'), $payload)
            ->assertRedirect(route('adviser.schedule', ['date' => $date]));
        $this->get(route('adviser.schedule', ['date' => $date]))->assertOk()->assertSee('All day');

        $shiftId = \App\Models\HelperSchedule::where('helper_id', $helper->id)->whereDate('date', $date)->value('id');

        // Rostering the same helper for the same day twice is refused.
        $this->post(route('adviser.schedule.update'), $payload)->assertSessionHasErrors('date');
        $this->assertSame(1, \App\Models\HelperSchedule::where('helper_id', $helper->id)->whereDate('date', $date)->count());

        // Passing the shift id edits that row in place instead of adding one.
        $this->post(route('adviser.schedule.update'), $payload + ['shift_id' => $shiftId])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, \App\Models\HelperSchedule::where('helper_id', $helper->id)->whereDate('date', $date)->count());

        // The form is per day, so it offers no shift times at all.
        $this->get(route('adviser.schedule'))->assertOk()
            ->assertDontSee('type="time"', false)
            ->assertDontSee('Select shift', false);
    }

    public function test_several_helpers_can_be_on_duty_on_the_same_date(): void
    {
        [$user, $adviser] = $this->adviserUser('multihelper@example.com');
        $first = $this->helper($adviser);
        $second = $this->helper($adviser);
        $third = $this->helper($adviser);
        $date = now()->addDays(3)->toDateString();

        // A date carries as many helpers as are rostered on it.
        foreach ([$first, $second, $third] as $helper) {
            $this->actingAs($user)->post(route('adviser.schedule.update'), [
                'helper_id' => $helper->id, 'date' => $date,
            ])->assertSessionHasNoErrors();
        }

        $this->assertSame(3, \App\Models\HelperSchedule::whereDate('date', $date)->count());

        foreach ([$first, $second, $third] as $helper) {
            $this->assertSame(1, \App\Models\HelperSchedule::where('helper_id', $helper->id)
                ->whereDate('date', $date)->count());
        }

        // Removing one helper's duty leaves the other helpers on that date.
        $shift = \App\Models\HelperSchedule::where('helper_id', $second->id)->whereDate('date', $date)->firstOrFail();

        $this->actingAs($user)->post(route('adviser.schedule.destroy'), [
            'helper_id' => $second->id, 'date' => $date, 'shift_id' => $shift->id,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('helper_schedules', ['id' => $shift->id]);
        $this->assertSame(2, \App\Models\HelperSchedule::whereDate('date', $date)->count());
        $this->assertTrue($first->fresh()->schedules()->whereDate('date', $date)->first()->isOnDuty() === false);
    }

    public function test_a_duty_day_covers_the_whole_date(): void
    {
        [$user, $adviser] = $this->adviserUser('wholeday@example.com');
        $helper = $this->helper($adviser);
        $date = now('Asia/Manila')->toDateString();

        $shift = app(\App\Services\HelperShiftService::class)->scheduleDuty(
            $helper,
            $date,
            attributes: ['created_by' => $user->id],
        );

        // Duty is a date, so no times are stored and the day spans midnight to
        // midnight rather than any narrower block.
        $this->assertNull($shift->shift_start);
        $this->assertNull($shift->shift_end);
        $this->assertSame('All day', $shift->shift_label);

        [$start, $end] = $shift->window();
        $this->assertSame('00:00', $start->format('H:i'));
        $this->assertSame('00:00', $end->format('H:i'));
        $this->assertSame(1, (int) $start->diffInDays($end));
        $this->assertTrue($shift->isOnDuty());
    }

    public function test_a_helper_cannot_be_on_duty_twice_on_the_same_date(): void
    {
        [$user, $adviser] = $this->adviserUser('twice@example.com');
        $helper = $this->helper($adviser);
        $date = now()->addDays(4)->toDateString();
        $service = app(\App\Services\HelperShiftService::class);

        $service->scheduleDuty($helper, $date, attributes: ['created_by' => $user->id]);

        try {
            $service->scheduleDuty($helper, $date, attributes: ['created_by' => $user->id]);
            $this->fail('The same helper should not be rostered twice for one date.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('date', $exception->errors());
        }

        $this->assertSame(1, \App\Models\HelperSchedule::where('helper_id', $helper->id)->whereDate('date', $date)->count());

        // A different date is fine, and so is a different helper on that date.
        $other = $this->helper($adviser);
        $service->scheduleDuty($helper, now()->addDays(5)->toDateString(), attributes: ['created_by' => $user->id]);
        $service->scheduleDuty($other, $date, attributes: ['created_by' => $user->id]);
        $this->assertSame(2, \App\Models\HelperSchedule::whereDate('date', $date)->count());
    }

    public function test_a_moderator_can_remove_a_helper_from_a_day(): void
    {
        [$moderatorUser] = $this->moderatorUser();
        $helper = $this->helper();
        $other = $this->helper();
        $date = now()->addDays(2)->toDateString();

        // The regression: the route supplies no {shift}, so the removal used to
        // fail on a missing argument and return a server error.
        foreach ([$helper, $other] as $each) {
            $this->actingAs($moderatorUser)->post(route('moderator.schedules.store'), [
                'helper_id' => $each->id, 'event_date' => $date,
            ])->assertSessionHasNoErrors();
        }

        $shift = \App\Models\HelperSchedule::where('helper_id', $helper->id)->whereDate('date', $date)->firstOrFail();
        $eventId = \App\Models\CalendarEvent::where('helper_schedule_id', $shift->id)->value('id');

        $this->assertNotNull($eventId, 'the duty event should be linked to its day');

        $this->actingAs($moderatorUser)->from(route('moderator.schedules'))->post(route('moderator.schedules.destroy'), [
            'helper_id' => $helper->id, 'date' => $date, 'shift_id' => $shift->id,
        ])->assertRedirect(route('moderator.schedules', ['date' => $date]))->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('helper_schedules', ['id' => $shift->id]);
        $this->assertDatabaseMissing('calendar_events', ['id' => $eventId]);

        // Only the removed helper loses the day.
        $this->assertSame(1, \App\Models\HelperSchedule::whereDate('date', $date)->count());
        $this->assertSame(1, \App\Models\HelperSchedule::where('helper_id', $other->id)->whereDate('date', $date)->count());
    }

    public function test_moderator_assignment_requires_current_ready_helper_and_rejects_override(): void
    {
        [$moderatorUser] = $this->moderatorUser();
        $helper = $this->helper();
        [$seeker, $seekerUser] = $this->seeker();
        $this->consentFixture($seekerUser);

        $queue = QueueRequest::create([
            'seeker_id' => $seeker->id,
            'request_status' => 'waiting',
            'priority_level' => 'low',
            'preferred_session_type' => 'chat',
        ]);

        $session = Session::create([
            'seeker_id' => $seeker->id,
            'queue_request_id' => $queue->id,
            'session_status' => Session::STATUS_WAITING,
            'session_type' => 'chat',
            'risk_level' => 'low',
            'submitted_at' => now(),
            'created_date' => now(),
        ]);

        // A not-yet-verified / off-duty helper cannot be assigned normally.
        $this->actingAs($moderatorUser)->post(route('moderator.queue.assign'), [
            'queue_id' => $queue->id,
            'helper_id' => $helper->id,
        ])->assertRedirect(route('moderator.queue'));

        $this->assertDatabaseHas('queue_requests', ['id' => $queue->id, 'request_status' => 'waiting', 'assigned_helper_id' => null]);

        // A forged override cannot bypass the same eligibility checks used by automatic matching.
        $this->actingAs($moderatorUser)->postJson(route('moderator.queue.assign'), [
            'queue_id'=>$queue->id,'helper_id'=>$helper->id,'emergency_override'=>1,
        ])->assertUnprocessable()->assertJsonValidationErrors('emergency_override');
        $this->assertDatabaseHas('queue_requests',['id'=>$queue->id,'request_status'=>'waiting']);
        $this->readyDutyHelper($helper);
        $this->actingAs($moderatorUser)->post(route('moderator.queue.assign'), ['queue_id'=>$queue->id,'helper_id'=>$helper->id])->assertSessionHas('success');
        $this->assertDatabaseHas('counseling_sessions',['id'=>$session->id,'helper_id'=>$helper->id,'session_status'=>Session::STATUS_HELPER_ASSIGNED]);
    }

    public function test_emergency_override_cannot_exceed_shift_capacity(): void
    {
        [$moderatorUser] = $this->moderatorUser();
        $helper = $this->helper();
        [$seeker, $seekerUser] = $this->seeker();
        $this->consentFixture($seekerUser);

        \App\Models\HelperSchedule::create([
            'helper_id' => $helper->id,
            'date' => now('Asia/Manila')->toDateString(),
            'shift_start' => '00:00:00',
            'shift_end' => '23:59:59',
            'created_by' => $helper->user_account_id,
            'is_active' => true,
        ]);

        // Fill the helper's two-session duty-shift limit.
        foreach (range(1, 2) as $i) {
            Session::create([
                'seeker_id' => $seeker->id,
                'session_status' => Session::STATUS_HELPER_ASSIGNED,
                'session_type' => 'chat',
                'risk_level' => 'low',
                'helper_id' => $helper->id,
                'start_time' => now('Asia/Manila')->setTime(18, 30),
                'created_date' => now(),
            ]);
        }

        $queue = QueueRequest::create([
            'seeker_id' => $seeker->id,
            'request_status' => 'waiting',
            'priority_level' => 'low',
            'preferred_session_type' => 'chat',
        ]);
        Session::create([
            'seeker_id' => $seeker->id,
            'queue_request_id' => $queue->id,
            'session_status' => Session::STATUS_WAITING,
            'session_type' => 'chat',
            'risk_level' => 'low',
            'submitted_at' => now(),
            'created_date' => now(),
        ]);

        $this->actingAs($moderatorUser)->post(route('moderator.queue.assign'), [
            'queue_id' => $queue->id,
            'helper_id' => $helper->id,
        ])->assertRedirect(route('moderator.queue'))->assertSessionHas('error');

        $this->assertDatabaseHas('queue_requests', ['id' => $queue->id, 'request_status' => 'waiting', 'assigned_helper_id' => null]);
    }

    public function test_moderator_can_roster_a_helper_for_a_day_and_rejects_a_duplicate(): void
    {
        [$moderatorUser] = $this->moderatorUser();
        $helper = $this->helper();
        $other = $this->helper();
        $date = now()->toDateString();

        $payload = [
            'helper_id' => $helper->id,
            'event_date' => $date,
            'description' => 'Queue coverage',
        ];

        $this->actingAs($moderatorUser)->post(route('moderator.schedules.store'), $payload)
            ->assertRedirect(route('moderator.schedules', ['date' => $date]));

        $this->assertDatabaseHas('helper_schedules', [
            'helper_id' => $helper->id, 'shift_start' => null, 'shift_end' => null,
        ]);
        $this->assertDatabaseHas('calendar_events', [
            'title' => 'Duty: '.$helper->full_name,
            'event_type' => CalendarEvent::TYPE_MEETING,
        ]);

        // Another helper can be rostered on the same date.
        $this->actingAs($moderatorUser)->from(route('moderator.schedules'))->post(route('moderator.schedules.store'), [
            'helper_id' => $other->id,
            'event_date' => $date,
        ])->assertRedirect(route('moderator.schedules', ['date' => $date]))->assertSessionHasNoErrors();

        $this->assertSame(2, \App\Models\HelperSchedule::whereDate('date', $date)->count());

        // Only the same helper twice on one date is refused.
        $this->actingAs($moderatorUser)->from(route('moderator.schedules'))->post(route('moderator.schedules.store'), $payload)
            ->assertRedirect(route('moderator.schedules'))
            ->assertSessionHasErrors('event_date');
        $this->assertSame(1, \App\Models\HelperSchedule::where('helper_id', $helper->id)->whereDate('date', $date)->count());
    }

    public function test_moderator_duty_day_counts_as_duty_for_eligibility(): void
    {
        [$moderatorUser] = $this->moderatorUser();
        $helper = $this->helper();
        $date = now('Asia/Manila')->toDateString();

        $this->actingAs($moderatorUser)->post(route('moderator.schedules.store'), [
            'helper_id' => $helper->id,
            'event_date' => $date,
            'description' => 'All-day duty date',
        ])->assertRedirect(route('moderator.schedules', ['date' => $date]));

        $this->assertDatabaseHas('helper_schedules', ['helper_id' => $helper->id, 'shift_start' => null, 'shift_end' => null]);
        $this->assertSame(1, \App\Models\HelperSchedule::where('helper_id', $helper->id)->whereDate('date', $date)->count());
        $this->assertDatabaseHas('calendar_events', [
            'title' => 'Duty: '.$helper->full_name,
            'event_type' => CalendarEvent::TYPE_MEETING,
            'start_time' => null,
            'end_time' => null,
        ]);

        // A duty day counts for the whole date, so the helper is on duty.
        $this->assertTrue($helper->fresh()->schedules()->whereDate('date', $date)->first()->isOnDuty());

        // The form is per day, so it offers no shift times at all.
        $this->get(route('moderator.schedules'))->assertOk()
            ->assertDontSee('type="time"', false)
            ->assertDontSee('Select shift', false);
    }

    public function test_adviser_can_save_a_duty_day_and_sees_all_day(): void
    {
        [$user, $adviser] = $this->adviserUser('dateonly@example.com');
        $helper = $this->helper($adviser);
        $date = now('Asia/Manila')->addDays(2)->toDateString();

        $this->actingAs($user)->from(route('adviser.schedule'))->post(route('adviser.schedule.update'), [
            'helper_id' => $helper->id,
            'date' => $date,
        ])->assertRedirect(route('adviser.schedule', ['date' => $date]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('helper_schedules', ['helper_id' => $helper->id, 'shift_start' => null, 'shift_end' => null]);
        $this->assertSame(1, \App\Models\HelperSchedule::where('helper_id', $helper->id)->whereDate('date', $date)->count());
        $this->get(route('adviser.schedule', ['date' => $date]))->assertOk()->assertSee('All day');
    }

    public function test_adviser_session_supervision_loads_report_and_blocks_other_advisers(): void
    {
        [$adviserUser, $adviser] = $this->adviserUser('one@example.com');
        [$otherUser] = $this->adviserUser('two@example.com');
        $helper = $this->helper($adviser);
        [$seeker] = $this->seeker();

        $session = Session::create([
            'seeker_id' => $seeker->id,
            'helper_id' => $helper->id,
            'session_status' => Session::STATUS_ACTIVE,
            'session_type' => 'chat',
            'risk_level' => 'high',
            'created_date' => now(),
        ]);
        SessionReport::create(['session_id' => $session->id, 'session_summary' => 'Needs review']);

        $this->actingAs($adviserUser)->get(route('adviser.session.show', $session->id))->assertOk();
        $this->actingAs($otherUser)->get(route('adviser.session.show', $session->id))->assertForbidden();
    }

    public function test_adviser_referrals_are_scoped_and_other_adviser_cannot_manage_them(): void
    {
        [$adviserUser, $adviser] = $this->adviserUser('scope-one@example.com');
        [$otherUser, $otherAdviser] = $this->adviserUser('scope-two@example.com');
        $ownReferral = $this->referralFor($adviser, 'Own referral');
        $otherReferral = $this->referralFor($otherAdviser, 'Other referral');

        $this->actingAs($adviserUser)->get(route('adviser.referrals'))
            ->assertOk()
            ->assertSee('Own referral')
            ->assertDontSee('Other referral');

        $this->actingAs($otherUser)->post(route('adviser.referral.approve', $ownReferral->id), [])
            ->assertForbidden();

        $this->actingAs($adviserUser)->post(route('adviser.referral.reject', $ownReferral->id), [
            'rejection_reason' => 'Not clinically indicated yet.',
        ])->assertRedirect(route('adviser.referrals'));

        $this->assertDatabaseHas('referrals', [
            'id' => $ownReferral->id,
            'status' => Referral::STATUS_DECLINED,
            'decline_reason' => 'Not clinically indicated yet.',
        ]);
        $this->assertDatabaseHas('referrals', ['id' => $otherReferral->id, 'status' => Referral::STATUS_PENDING_ADVISER]);
    }

    public function test_adviser_emergency_resolution_is_scoped_to_supervised_helpers(): void
    {
        [$adviserUser, $adviser] = $this->adviserUser('emergency-one@example.com');
        [$otherUser] = $this->adviserUser('emergency-two@example.com');
        $referral = $this->referralFor($adviser, 'Emergency referral', 'emergency');

        $alert = EmergencyAlert::create([
            'seeker_id' => $referral->session->seeker_id,
            'session_id' => $referral->session_id,
            'adviser_id' => $adviser->id,
            'alert_type' => 'screening',
            'risk_level' => 'emergency',
            'triggered_at' => now(),
            'trigger_reason' => 'Immediate threat identified.',
            'status' => 'pending',
        ]);

        $this->actingAs($otherUser)->post(route('adviser.emergencies.resolve', $alert->id), [
            'resolution_notes' => 'Resolved by wrong adviser.',
        ])->assertForbidden();

        $this->actingAs($adviserUser)->post(route('adviser.emergencies.resolve', $alert->id), [
            'resolution_notes' => 'Coordinated with crisis response and professional.',
        ])->assertRedirect(route('adviser.emergencies'));

        $this->assertDatabaseHas('emergency_alerts', [
            'id' => $alert->id,
            'status' => 'resolved',
            'resolution_notes' => 'Coordinated with crisis response and professional.',
        ]);
    }

    public function test_moderator_can_unassign_a_clear_helper_back_to_the_pool(): void
    {
        [$user] = $this->moderatorUser();
        [, $adviser] = $this->adviserUser('unassign-free@example.com');
        $helper = $this->helper($adviser);

        $this->actingAs($user)->from(route('moderator.manage'))
            ->post(route('moderator.manage.unassign'), ['helper_id' => $helper->id])
            ->assertRedirect(route('moderator.manage'))->assertSessionHas('success');

        $this->assertDatabaseHas('helpers', ['id' => $helper->id, 'adviser_id' => null]);
        $this->assertNotNull(\Illuminate\Support\Facades\DB::table('adviser_helper_assignments')->where('helper_id', $helper->id)->where('adviser_id', $adviser->id)->value('ended_at'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'supervision_relationship_changed', 'target_id' => $helper->id]);
    }

    public function test_moderator_unassign_records_reason_and_actor_on_the_closed_history(): void
    {
        [$user] = $this->moderatorUser();
        [, $adviser] = $this->adviserUser('unassign-audit@example.com');
        $helper = $this->helper($adviser);

        $this->actingAs($user)->from(route('moderator.manage'))
            ->post(route('moderator.manage.unassign'), ['helper_id' => $helper->id])
            ->assertRedirect(route('moderator.manage'))->assertSessionHas('success');

        $closed = \Illuminate\Support\Facades\DB::table('adviser_helper_assignments')
            ->where('helper_id', $helper->id)->where('adviser_id', $adviser->id)->first();
        $this->assertNotNull($closed->ended_at);
        $this->assertSame($user->id, $closed->actor_id);
        $this->assertStringContainsString('Supervision ended by Moderator', (string) $closed->reason);
    }

    public function test_moderator_cannot_unassign_a_helper_with_open_referrals_or_cases(): void
    {
        [$user] = $this->moderatorUser();
        [, $adviser] = $this->adviserUser('unassign-blocked@example.com');
        $referral = $this->referralFor($adviser, 'Open referral', 'high');
        $helper = $referral->helper;

        $this->actingAs($user)->from(route('moderator.manage'))
            ->post(route('moderator.manage.unassign'), ['helper_id' => $helper->id])
            ->assertStatus(409);

        $this->assertNotNull($helper->fresh()->adviser_id);
    }

    public function test_supervision_transfer_moves_active_referrals_and_blocks_previous_owner(): void
    {
        [$user, $adviser] = $this->adviserUser('transfer-one@example.com');
        [$nextUser, $next] = $this->adviserUser('transfer-two@example.com');
        $referral = $this->referralFor($adviser, 'Transfer referral');
        $helper = $referral->helper;
        $this->actingAs($user)->get(route('adviser.helper.show', $helper->id))->assertOk()->assertSee('No competency evaluations');
        $this->post(route('adviser.helpers.reassign'), [
            'helper_ids' => [$helper->id], 'adviser_id' => $next->id, 'reason' => 'Coverage change',
        ])->assertRedirect(route('adviser.helpers'))->assertSessionHas('success');
        $this->assertDatabaseHas('helpers', ['id' => $helper->id, 'adviser_id' => $next->id]);
        $this->assertDatabaseCount('adviser_helper_assignments', 2);
        $this->assertDatabaseHas('adviser_helper_assignments', ['helper_id'=>$helper->id, 'adviser_id'=>$next->id, 'ended_at'=>null, 'reason'=>'Coverage change']);
        $this->assertNotNull(\Illuminate\Support\Facades\DB::table('adviser_helper_assignments')->where('helper_id',$helper->id)->where('adviser_id',$adviser->id)->value('ended_at'));
        $this->assertDatabaseHas('audit_logs', ['action'=>'helper_supervision_transferred','target_id'=>$helper->id]);
        $this->assertDatabaseHas('referrals', ['id' => $referral->id, 'adviser_id' => $next->id]);
        $this->get(route('adviser.helper.show', $helper->id))->assertForbidden();
        $this->post(route('adviser.helpers.reassign'), [
            'helper_ids' => [$helper->id], 'adviser_id' => $next->id, 'reason' => 'Not my helper',
        ])->assertForbidden();
        $this->actingAs($nextUser)->get(route('adviser.helper.show', $helper->id))->assertOk();
    }

    public function test_reports_handle_pending_referrals_and_first_helper_reply(): void
    {
        [$user, $adviser] = $this->adviserUser('reports@example.com');
        $referral = $this->referralFor($adviser, 'Pending review');
        $session = $referral->session;
        $session->update(['created_date' => now()->subMinutes(20)]);
        foreach ([[$session->seeker->user_account_id, 19], [$referral->helper->user_account_id, 15], [$referral->helper->user_account_id, 1]] as [$sender, $minutes]) {
            \App\Models\Message::create(['session_id' => $session->id, 'sender_id' => $sender, 'message_text' => 'Test reply', 'sent_datetime' => now()->subMinutes($minutes)]);
        }
        $this->actingAs($user)->get(route('adviser.reports'))->assertOk()
            ->assertViewHas('avgResponseTime', '5m')
            ->assertViewHas('metrics', fn ($metrics) => $metrics['referral_approval_rate'] === null && $metrics['referral_rate'] === null);
    }

    public function test_analytics_handles_submitted_at_sessions_without_type_errors(): void
    {
        // Freeze the clock: response_minutes is derived from two separate now()
        // reads, so an uncontrolled tick makes the interval 5.02m instead of 5m.
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-16 19:00', 'Asia/Manila')->utc());

        [$user, $adviser] = $this->adviserUser('reports-submitted@example.com');
        [$referral] = [$this->referralFor($adviser, 'Submitted review')];
        $session = $referral->session;
        $session->update(['submitted_at' => now()->subMinutes(20)]);
        \App\Models\Message::create(['session_id' => $session->id, 'sender_id' => $referral->helper->user_account_id, 'message_text' => 'First reply', 'sent_datetime' => now()->subMinutes(15)]);
        $this->actingAs($user)->get(route('adviser.reports'))->assertOk()
            ->assertViewHas('avgResponseTime', '5m')
            ->assertViewHas('metrics', fn ($metrics) => $metrics['response_minutes'] === 5.0 && $metrics['referral_approval_rate'] === null);
    }

    public function test_manual_assignment_cannot_assign_the_same_request_twice(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-16 19:00','Asia/Manila')->utc());
        [$user] = $this->moderatorUser();
        $helper = $this->helper();
        [$seeker] = $this->seeker();
        $queue = QueueRequest::create(['seeker_id' => $seeker->id, 'request_status' => 'waiting', 'priority_level' => 'low']);
        $this->consentFixture($seeker->user);
        Session::create(['seeker_id'=>$seeker->id,'queue_request_id'=>$queue->id,'submitted_at'=>now(),'risk_level'=>'low','session_status'=>'waiting','created_date'=>now()]);
        $this->readyDutyHelper($helper);
        $payload = ['queue_id' => $queue->id, 'helper_id' => $helper->id];
        $this->actingAs($user)->post(route('moderator.queue.assign'), $payload)->assertSessionHas('success');
        $this->post(route('moderator.queue.assign'), $payload)->assertSessionHas('error');
        $this->assertSame(1, Session::where('queue_request_id', $queue->id)->count());
        $next = $this->helper(); $this->readyDutyHelper($next);
        $payload['helper_id'] = $next->id;
        $this->post(route('moderator.queue.reassign'), $payload)->assertSessionHas('success');
        $this->assertDatabaseHas('counseling_sessions', ['queue_request_id' => $queue->id, 'submitted_at' => now(), 'helper_id' => $next->id]);
        $this->assertSame(0, $helper->fresh()->active_sessions_count);
    }

    public function test_emergency_contacts_can_be_published_and_hidden_by_advisers(): void
    {
        [$user] = $this->adviserUser('contacts@example.com');
        $data = ['agency_name' => 'Test Support Desk', 'hotline' => '12345', 'description' => 'Test contact only', 'status' => 'active'];
        $this->actingAs($user)->post(route('adviser.emergency-resources.save'), $data)->assertSessionHas('success');
        $contact = \App\Models\EmergencyResource::where('agency_name', 'Test Support Desk')->firstOrFail();
        $this->get(route('emergency'))->assertOk()->assertSee('Test Support Desk')->assertDontSee('0917-123-4567');
        $this->post(route('adviser.emergency-resources.save'), [...$data, 'id' => $contact->id, 'status' => 'inactive'])->assertSessionHas('success');
        $this->get(route('emergency'))->assertDontSee('Test Support Desk');
        [$seeker, $seekerUser] = $this->seeker();
        $this->actingAs($seekerUser)->post(route('adviser.emergency-resources.save'), $data)->assertForbidden();
    }

    public function test_moderator_can_raise_waiting_queue_priority(): void
    {
        [$user] = $this->moderatorUser();
        [$seeker] = $this->seeker();
        $queue = QueueRequest::create(['seeker_id' => $seeker->id, 'request_status' => 'waiting', 'priority_level' => 'low']);
        $this->actingAs($user)->patch(route('moderator.queue.priority', $queue), ['priority_level' => 'high'])->assertSessionHas('success');
        $this->assertSame('high', $queue->fresh()->priority_level);
        $this->patch(route('moderator.queue.priority', $queue), ['priority_level' => 'low'])->assertSessionHasErrors('priority_level');
        $this->assertSame('high', $queue->fresh()->priority_level);
    }

    public function test_queue_assignment_starts_immediately_and_rejects_an_appointment_time(): void
    {
        [$moderatorUser] = $this->moderatorUser();
        $helper = $this->schedulableHelper();
        $this->readyDutyHelper($helper);
        [$seeker, $seekerUser] = $this->seeker();
        $this->consentFixture($seekerUser);

        $queue = QueueRequest::create(['seeker_id' => $seeker->id, 'request_status' => 'waiting', 'priority_level' => 'low', 'preferred_session_type' => 'chat']);
        $session = Session::create(['seeker_id' => $seeker->id, 'queue_request_id' => $queue->id, 'session_status' => Session::STATUS_WAITING, 'session_type' => 'chat', 'risk_level' => 'low', 'submitted_at' => now(), 'created_date' => now()]);

        $appointment = now('Asia/Manila')->addHours(6)->second(0);
        $this->actingAs($moderatorUser)->post(route('moderator.queue.assign'), [
            'queue_id' => $queue->id, 'helper_id' => $helper->id, 'scheduled_at' => $appointment->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors('scheduled_at');

        $this->assertDatabaseHas('counseling_sessions', [
            'id' => $session->id, 'session_status' => Session::STATUS_WAITING,
        ]);
        $this->assertDatabaseHas('queue_requests', ['id' => $queue->id, 'request_status' => 'waiting']);
    }

    public function test_assigning_a_helper_activates_the_session_immediately(): void
    {
        [$moderatorUser] = $this->moderatorUser();
        $helper = $this->schedulableHelper();
        $this->readyDutyHelper($helper);
        [$seeker, $seekerUser] = $this->seeker();
        $this->consentFixture($seekerUser);

        $queue = QueueRequest::create(['seeker_id' => $seeker->id, 'request_status' => 'waiting', 'priority_level' => 'low', 'preferred_session_type' => 'chat']);
        $session = Session::create(['seeker_id' => $seeker->id, 'queue_request_id' => $queue->id, 'session_status' => Session::STATUS_WAITING, 'session_type' => 'chat', 'risk_level' => 'low', 'submitted_at' => now(), 'created_date' => now()]);

        $this->actingAs($moderatorUser)->post(route('moderator.queue.assign'), [
            'queue_id' => $queue->id, 'helper_id' => $helper->id,
        ])->assertRedirect(route('moderator.queue'))->assertSessionHas('success');

        $this->assertDatabaseHas('counseling_sessions', [
            'id' => $session->id, 'helper_id' => $helper->id, 'session_status' => Session::STATUS_HELPER_ASSIGNED,
        ]);
        // The session waits for the helper to accept before it goes live.
        $this->assertDatabaseHas('counseling_sessions', ['id' => $session->id, 'match_status' => 'awaiting_acceptance']);
        $this->assertNull($session->fresh()->helper_accepted_at);
        $this->assertDatabaseHas('queue_requests', ['id' => $queue->id, 'request_status' => 'assigned']);
    }

    public function test_moderator_cannot_schedule_an_appointment_in_the_past(): void
    {
        [$moderatorUser] = $this->moderatorUser();
        $helper = $this->schedulableHelper();
        $this->readyDutyHelper($helper);
        [$seeker, $seekerUser] = $this->seeker();
        $this->consentFixture($seekerUser);

        $queue = QueueRequest::create(['seeker_id' => $seeker->id, 'request_status' => 'waiting', 'priority_level' => 'low', 'preferred_session_type' => 'chat']);
        $session = Session::create(['seeker_id' => $seeker->id, 'queue_request_id' => $queue->id, 'session_status' => Session::STATUS_WAITING, 'session_type' => 'chat', 'risk_level' => 'low', 'submitted_at' => now(), 'created_date' => now()]);

        $this->actingAs($moderatorUser)->post(route('moderator.queue.assign'), [
            'queue_id' => $queue->id, 'helper_id' => $helper->id,
        ])->assertRedirect(route('moderator.queue'));

        $this->actingAs($moderatorUser)->post(route('moderator.queue.schedule'), [
            'queue_id' => $queue->id, 'scheduled_at' => now('Asia/Manila')->subHours(1)->format('Y-m-d H:i:s'),
        ])->assertStatus(422);
        $this->assertDatabaseHas('counseling_sessions', ['id' => $session->id, 'session_status' => Session::STATUS_HELPER_ASSIGNED]);
    }

    public function test_moderator_can_change_the_appointment_time_of_an_assigned_request(): void
    {
        [$moderatorUser] = $this->moderatorUser();
        $helper = $this->schedulableHelper();
        $this->readyDutyHelper($helper);
        [$seeker, $seekerUser] = $this->seeker();
        $this->consentFixture($seekerUser);

        $queue = QueueRequest::create(['seeker_id' => $seeker->id, 'request_status' => 'waiting', 'priority_level' => 'low', 'preferred_session_type' => 'chat']);
        $session = Session::create(['seeker_id' => $seeker->id, 'queue_request_id' => $queue->id, 'session_status' => Session::STATUS_WAITING, 'session_type' => 'chat', 'risk_level' => 'low', 'submitted_at' => now(), 'created_date' => now()]);

        $this->actingAs($moderatorUser)->post(route('moderator.queue.assign'), [
            'queue_id' => $queue->id, 'helper_id' => $helper->id,
        ])->assertRedirect(route('moderator.queue'));

        $appointment = now('Asia/Manila')->addDay()->second(0);
        $this->actingAs($moderatorUser)->from(route('moderator.queue'))->post(route('moderator.queue.schedule'), [
            'queue_id' => $queue->id, 'scheduled_at' => $appointment->format('Y-m-d H:i:s'),
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('counseling_sessions', [
            'id' => $session->id, 'scheduled_start' => $appointment->utc()->format('Y-m-d H:i:s'),
            'pre_session_brief_expires_at' => $appointment->utc()->format('Y-m-d H:i:s'),
        ]);
        $this->assertDatabaseHas('queue_requests', ['id' => $queue->id, 'scheduled_date' => $appointment->utc()->format('Y-m-d H:i:s')]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'session_scheduled', 'target_id' => $session->id]);
    }

    public function test_scheduled_appointment_time_is_visible_to_the_helper_and_seeker(): void
    {
        [$moderatorUser] = $this->moderatorUser();
        $helperUser = User::factory()->create(['role' => 'helper', 'is_active' => true]);
        $helper = $this->schedulableHelper($helperUser);
        $this->readyDutyHelper($helper);
        [$seeker, $seekerUser] = $this->seeker();
        $this->consentFixture($seekerUser);

        $queue = QueueRequest::create(['seeker_id' => $seeker->id, 'request_status' => 'waiting', 'priority_level' => 'low', 'preferred_session_type' => 'chat']);
        $session = Session::create(['seeker_id' => $seeker->id, 'queue_request_id' => $queue->id, 'session_status' => Session::STATUS_WAITING, 'session_type' => 'chat', 'risk_level' => 'low', 'submitted_at' => now(), 'created_date' => now()]);

        $appointment = now('Asia/Manila')->addHours(8)->second(0);
        $this->actingAs($moderatorUser)->post(route('moderator.queue.assign'), [
            'queue_id' => $queue->id, 'helper_id' => $helper->id,
        ])->assertRedirect(route('moderator.queue'));
        $this->actingAs($moderatorUser)->post(route('moderator.queue.schedule'), [
            'queue_id' => $queue->id, 'scheduled_at' => $appointment->format('Y-m-d H:i:s'),
        ])->assertRedirect()->assertSessionHas('success');

        $label = $appointment->format('M d, h:i A');
        $this->actingAs($helperUser)->get(route('helper.cases'))->assertOk()->assertSee($label);
        $this->actingAs($helperUser)->get(route('helper.cases.show', $session->id))->assertOk()->assertSee($label);
        $this->actingAs($seekerUser)->get(route('request.matching'))->assertOk()->assertSee($label);
    }

    private function schedulableHelper(?User $user = null): Helper
    {
        $user ??= User::factory()->create(['role' => 'helper', 'is_active' => true]);
        $helper = Helper::create([
            'user_account_id' => $user->id,
            'first_name' => 'Helen', 'last_name' => 'Helper' . $user->id,
            'email' => $user->email, 'status' => 'available', 'availability' => 'available',
            'competency_level' => 3, 'competency_risk_level' => 3, 'max_concurrent_sessions' => 2,
        ]);
        $this->verifiedHelperFixture($helper);
        \App\Models\HelperSchedule::create(['helper_id' => $helper->id, 'date' => now('Asia/Manila')->toDateString(), 'shift_start' => '00:00:00', 'shift_end' => '23:59:59', 'created_by' => $user->id, 'is_active' => true]);
        \App\Models\ReadinessCheck::create(['helper_id' => $helper->id, 'assessment_date' => now(), 'valid_until' => now()->addHours(4), 'assessment_result' => 'ready', 'emotionally_ready' => true, 'willing_to_listen' => true, 'stress_level' => 'low', 'availability_status' => 'available', 'skills_confirmed' => \App\Services\HelperReadinessService::SKILLS, 'is_active' => true]);

        return $helper->fresh();
    }

    private function moderatorUser(): array
    {
        $user = User::factory()->create(['role' => 'moderator']);
        $moderator = Moderator::create([
            'user_account_id' => $user->id,
            'first_name' => 'Mara',
            'last_name' => 'Moderator',
            'email' => $user->email,
        ]);

        return [$user, $moderator];
    }

    private function adviserUser(string $email): array
    {
        $user = User::factory()->create(['role' => 'adviser', 'email' => $email]);
        $adviser = Adviser::create([
            'user_account_id' => $user->id,
            'first_name' => 'Ada',
            'last_name' => 'Adviser',
            'email' => $email,
        ]);

        return [$user, $adviser];
    }

    private function readyDutyHelper(Helper $helper): void {
        $this->travelTo(now('Asia/Manila')->setTime(19,0)->utc());
        $this->verifiedHelperFixture($helper);
        $helper->update(['status'=>'available','availability'=>'available']);
        \App\Models\HelperSchedule::updateOrCreate(['helper_id'=>$helper->id,'date'=>now('Asia/Manila')->startOfDay()],['shift_start'=>'18:00:00','shift_end'=>'23:00:00','created_by'=>$helper->user_account_id,'is_active'=>true]);
        \App\Models\ReadinessCheck::create(['helper_id'=>$helper->id,'assessment_date'=>now(),'valid_until'=>now()->addHours(3),'assessment_result'=>'ready','emotionally_ready'=>true,'willing_to_listen'=>true,'stress_level'=>'low','availability_status'=>'available','is_active'=>true]);
    }

    private function helper(?Adviser $adviser = null): Helper
    {
        $user = User::factory()->create(['role' => 'helper']);

        return Helper::create([
            'user_account_id' => $user->id,
            'adviser_id' => $adviser?->id,
            'first_name' => 'Helen',
            'last_name' => 'Helper' . $user->id,
            'email' => $user->email,
            'status' => 'available',
            'competency_level' => 3,
            'max_concurrent_sessions' => 2,
        ]);
    }

    private function seeker(): array
    {
        $user = User::factory()->create(['role' => 'seeker']);
        $seeker = HelpSeeker::create([
            'user_account_id' => $user->id,
            'generated_alias' => 'Seeker' . $user->id,
            'age' => 20,
            'gender' => 'prefer-not-to-say',
        ]);

        return [$seeker, $user];
    }

    private function referralFor(Adviser $adviser, string $reason, string $priority = 'high'): Referral
    {
        $helper = $this->helper($adviser);
        [$seeker] = $this->seeker();

        $session = Session::create([
            'seeker_id' => $seeker->id,
            'helper_id' => $helper->id,
            'session_status' => Session::STATUS_ACTIVE,
            'session_type' => 'chat',
            'risk_level' => $priority,
            'created_date' => now(),
        ]);

        return Referral::create([
            'session_id' => $session->id,
            'helper_id' => $helper->id,
            'adviser_id' => $adviser->id,
            'priority_level' => $priority,
            'referral_reason' => $reason,
            'status' => Referral::STATUS_PENDING_ADVISER,
        ]);
    }
}
