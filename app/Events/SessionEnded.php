<?php

namespace App\Events;

use App\Models\Session;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast whenever a session ends, so the OTHER participant (and anyone
 * currently on the session page) is told immediately: the seeker is sent to
 * the evaluation page, the helper is sent to the session notes page.
 *
 * Fires on three PRIVATE channels:
 *   session.{id}   – both participants while they are in the chat room
 *   seeker.{userId} – the seeker anywhere in the app
 *   helper.{userId} – the helper anywhere in the app
 */
class SessionEnded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Session $session;

    /**
     * 'seeker' or 'helper' – who pressed the End button.
     */
    public string $endedBy;

    public function __construct(Session $session, string $endedBy)
    {
        $this->session = $session;
        $this->endedBy = $endedBy;
    }

    /**
     * @return PrivateChannel[]
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('session.' . $this->session->id),
        ];

        if ($this->session->seeker?->user_account_id) {
            $channels[] = new PrivateChannel('seeker.' . $this->session->seeker->user_account_id);
        }

        if ($this->session->helper?->user_account_id) {
            $channels[] = new PrivateChannel('helper.' . $this->session->helper->user_account_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'SessionEnded';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->id,
            'ended_by' => $this->endedBy,
            'message' => $this->session->auto_completed
                ? 'The 90-minute session limit has been reached.'
                : 'The session has been ended by the ' . $this->endedBy . '.',
            'seeker_redirect' => '/session/evaluation',
            'helper_redirect' => '/helper/session/' . $this->session->id . '/notes',
        ];
    }
}
