<?php

namespace App\Services;

use App\Models\Session;

class AdviserTranscriptAccess
{
    public const PURPOSES = ['competency_assessment', 'referral_review', 'emergency_verification', 'incident_investigation', 'quality_assurance'];

    /**
     * Adviser supervision review is covered by the seeker's general informed
     * consent captured at the start of the support flow, so no per-session
     * transcription consent is required. Structural authorization (active
     * adviser, current supervision of the helper, completed chat session)
     * still applies.
     */
    public function eligible(Session $session): bool
    {
        $actor = auth()->user();

        return (bool) $actor && $actor->is_active && $actor->role === 'adviser' && $actor->adviser
            && $session->helper?->adviser_id === $actor->adviser->id
            && in_array($session->session_status, [Session::STATUS_COMPLETED, Session::STATUS_EVALUATED], true)
            && $session->session_type === 'chat';
    }

    public function grant(Session $session, string $purpose, string $reason): void
    {
        abort_unless($this->eligible($session), 403, 'Completed-session access requires current supervision and an authorized review purpose.');
        abort_unless(in_array($purpose, self::PURPOSES, true) && mb_strlen(trim($reason)) >= 10 && mb_strlen($reason) <= 500, 422);
        request()->session()->put('adviser_transcript.'.$session->id, [
            'actor_id' => auth()->id(), 'purpose' => $purpose, 'expires_at' => now()->addMinutes(10)->timestamp,
        ]);
        SupportAudit::record('transcript_access_authorized', $session, ['purpose' => $purpose, 'reason' => $reason]);
    }

    public function allowed(Session $session): bool
    {
        if (! $this->eligible($session) || ! request()->hasSession()) {
            return false;
        }
        $grant = request()->session()->get('adviser_transcript.'.$session->id);

        return $grant && ($grant['actor_id'] ?? null) === auth()->id() && ($grant['expires_at'] ?? 0) > now()->timestamp;
    }
}
