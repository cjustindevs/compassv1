<?php

namespace Tests\Feature;

use App\Models\EmergencyAlert;
use App\Models\Session;
use Tests\TestCase;

class EmergencyWaitingPresentationTest extends TestCase
{
    private function renderWaiting(array $alertAttributes = [], array $sessionAttributes = []): string
    {
        $session = new Session(array_merge(['session_status' => 'waiting'], $sessionAttributes));
        $session->setRelation('supportEmergencyAlert', new EmergencyAlert(array_merge([
            'status' => 'pending', 'triggered_at' => now()->subMinutes(75),
        ], $alertAttributes)));

        return view('request.partials.emergency-waiting', compact('session'))->render();
    }

    public function test_elapsed_time_uses_alert_timestamp_and_does_not_promise_a_response(): void
    {
        $this->travelTo(now()->startOfMinute());
        $html = $this->renderWaiting();
        $this->assertStringContainsString('1 hr 15 min', $html);
        $this->assertStringContainsString('Awaiting acknowledgment', $html);
        $this->assertStringContainsString('cannot promise an immediate emergency response', $html);
        $this->assertStringNotContainsString('Open chat', $html);
    }

    public function test_closed_review_freezes_duration_and_does_not_claim_it_is_open(): void
    {
        $html = $this->renderWaiting(['status' => 'resolved', 'resolved_at' => now()->subMinutes(60)]);
        $this->assertStringContainsString('15 min', $html);
        $this->assertStringContainsString('Review closed', $html);
        $this->assertStringNotContainsString('review remains open', $html);
    }

    public function test_missing_or_future_timestamps_do_not_create_negative_waits(): void
    {
        foreach ([null, now()->addHour()] as $timestamp) {
            $this->assertStringContainsString('Not recorded', $this->renderWaiting(['triggered_at' => $timestamp]));
        }
    }

    public function test_chat_action_requires_active_session_and_recorded_helper_acceptance(): void
    {
        $this->assertStringNotContainsString('Open chat', $this->renderWaiting([], ['session_status' => 'active']));
        $html = $this->renderWaiting([], ['session_status' => 'active', 'helper_accepted_at' => now()]);
        $this->assertStringContainsString('Helper connected', $html);
        $this->assertStringContainsString(route('session.chat'), $html);
    }
}
