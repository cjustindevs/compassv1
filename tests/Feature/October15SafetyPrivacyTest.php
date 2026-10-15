<?php

namespace Tests\Feature;

use App\Models\Adviser;
use App\Models\EmergencyAlert;
use App\Models\HelpSeeker;
use App\Models\Notification;
use App\Models\ScreeningResponse;
use App\Models\Session;
use App\Models\User;
use App\Services\CompactScreening;
use App\Services\EmergencyEscalationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class October15SafetyPrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['broadcasting.default' => 'null']);
    }

    private function reviewer(bool $active = true): User
    {
        $user = User::factory()->create(['role' => 'adviser', 'is_active' => $active]);
        Adviser::create(['user_account_id' => $user->id, 'first_name' => 'Assigned', 'last_name' => 'Reviewer', 'email' => $user->email]);

        return $user;
    }

    private function requestCase(User $reviewer, string $answer = 'yes'): Session
    {
        $user = User::factory()->create(['role' => 'seeker']);
        $seeker = HelpSeeker::create(['user_account_id' => $user->id, 'generated_alias' => 'PrivateAlias'.$user->id]);
        $case = Session::create(['seeker_id' => $seeker->id, 'risk_level' => 'high', 'session_type' => 'chat', 'created_date' => now(),
            'session_status' => 'pending_review', 'workflow_state' => 'adviser_review_required', 'requires_adviser_review' => true,
            'review_adviser_id' => $reviewer->adviser->id]);
        ScreeningResponse::create(['session_id' => $case->id, 'seeker_id' => $seeker->id, 'risk_level' => 'high',
            'responses' => ['suicidal_thoughts' => $answer, 'description' => 'Private original concern'],
            'instrument_version' => CompactScreening::SINGLE_VERSION, 'review_status' => 'adviser_review_required',
            'priority' => 1, 'action' => 'adviser_review_required']);

        return $case;
    }

    public function test_emergency_board_is_newest_first_including_timestamp_ties_and_exposes_no_private_session_link(): void
    {
        $reviewer = $this->reviewer();
        $mod = User::factory()->create(['role' => 'moderator']);
        $a = $this->requestCase($reviewer);
        $b = $this->requestCase($reviewer);
        $c = $this->requestCase($reviewer);
        $old = EmergencyAlert::create(['session_id' => $a->id, 'seeker_id' => $a->seeker_id, 'trigger_reason' => 'Private older report', 'triggered_at' => now()->subHour()]);
        $new = EmergencyAlert::create(['session_id' => $b->id, 'seeker_id' => $b->seeker_id, 'trigger_reason' => 'Private newer report', 'triggered_at' => now()]);
        $tie = EmergencyAlert::create(['session_id' => $c->id, 'seeker_id' => $c->seeker_id, 'trigger_reason' => 'Private tied report', 'triggered_at' => now()]);
        $this->actingAs($mod)->get(route('moderator.emergency'))->assertOk()
            ->assertViewHas('openIncidents', fn ($rows) => $rows->pluck('id')->all() === [$tie->id, $new->id, $old->id])
            ->assertDontSee('Private original concern')->assertDontSee('Private newer report')->assertDontSee('View session')
            ->assertDontSee('/moderator/sessions/');
        $this->getJson(route('notifications.feed'))->assertOk()->assertJsonPath('workflow.emergency_count', 3);
    }

    public function test_empty_moderator_badge_exists_so_live_updates_can_reveal_it(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'moderator']))->get(route('moderator.emergency'))
            ->assertOk()->assertSee('id="emergencyBadge"', false)->assertSee('staffEmergencyNotice', false);
    }

    public function test_screening_answers_are_read_only_and_manipulated_answers_or_risk_are_rejected(): void
    {
        $reviewer = $this->reviewer();
        $case = $this->requestCase($reviewer);
        $original = $case->screeningResponses()->first();
        $before = $original->getAttributes();
        $this->actingAs($reviewer)->get(route('adviser.screenings'))->assertOk()->assertSee('Private original concern')
            ->assertDontSee('name="answers[', false)->assertDontSee('name="clarified_safety_answer"', false)
            ->assertDontSee('<select name="risk_level"', false);
        $decision = ['risk_level' => 'high', 'review_action' => 'record', 'reason' => 'Review the saved original answers.', 'evidence_source' => 'Original screening'];
        foreach ([['answers' => ['suicidal_thoughts' => 'no']], ['clarified_safety_answer' => 'no'], ['use_clarified_answers' => 1]] as $replacement) {
            $this->postJson(route('adviser.screenings.review', $case), $decision + $replacement)->assertUnprocessable();
        }
        $this->postJson(route('adviser.screenings.review', $case), array_replace($decision, ['risk_level' => 'low']))
            ->assertUnprocessable()->assertJsonValidationErrors('risk_level');
        $this->assertSame($before, $original->fresh()->getAttributes());
        $this->assertDatabaseCount('screening_responses', 1);
        $this->assertSame('high', $case->fresh()->risk_level);
        $this->assertNull($case->fresh()->peer_support_approved_at);
    }

    public function test_pre_session_notice_falls_back_from_inactive_reviewer_and_survives_without_websockets(): void
    {
        $inactive = $this->reviewer(false);
        $active = $this->reviewer();
        $mod = User::factory()->create(['role' => 'moderator']);
        $disabledMod = User::factory()->create(['role' => 'moderator', 'is_active' => false]);
        $case = $this->requestCase($inactive);
        $this->actingAs($case->seeker->user);
        $alert = app(EmergencyEscalationService::class)->escalateEmergency($case, $case->seeker, ['preserve_classification' => true]);
        $this->assertSame($active->adviser->id, $alert->adviser_id);
        $this->assertDatabaseHas('notifications', ['user_account_id' => $active->id, 'notification_type' => 'emergency', 'status' => 'unread']);
        $this->assertDatabaseHas('notifications', ['user_account_id' => $mod->id, 'notification_type' => 'emergency', 'status' => 'unread']);
        $this->assertDatabaseMissing('notifications', ['user_account_id' => $inactive->id]);
        $this->assertDatabaseMissing('notifications', ['user_account_id' => $disabledMod->id]);
        $count = Notification::count();
        app(EmergencyEscalationService::class)->escalateEmergency($case, $case->seeker, ['preserve_classification' => true]);
        $this->assertSame($count, Notification::count());
        foreach ([$mod, $active] as $recipient) {
            $this->actingAs($recipient)->getJson(route('notifications.feed', ['after' => 0]))->assertOk()
                ->assertJsonPath('workflow.emergency_count', 1)->assertJsonPath('fresh.0.type', 'emergency');
            $notice = $this->getJson(route('emergency-notice.next'))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
            $this->assertNotNull($notice->json('notice.reference'));
            $this->postJson(route('emergency-notice.dismiss'), ['reference' => $notice->json('notice.reference')])->assertNoContent();
            $this->getJson(route('emergency-notice.next'))->assertJsonPath('notice', null);
        }
        $this->actingAs($active)->get(route('adviser.emergencies.show', $alert))->assertOk()
            ->assertSee('Original pre-session screening')->assertSee('Private original concern');
    }

    public function test_screening_list_uses_latest_first_with_a_stable_tie_breaker(): void
    {
        $reviewer = $this->reviewer();
        $old = $this->requestCase($reviewer);
        $new = $this->requestCase($reviewer);
        $old->update(['created_date' => now()->subDay()]);
        $tie = $this->requestCase($reviewer);
        $tie->update(['created_date' => $new->created_date]);
        $this->actingAs($reviewer)->get(route('adviser.screenings'))->assertOk()
            ->assertViewHas('sessions', fn ($rows) => $rows->pluck('id')->all() === [$tie->id, $new->id, $old->id]);
    }
}
