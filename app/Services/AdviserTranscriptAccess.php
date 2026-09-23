<?php

namespace App\Services;

use App\Models\ConsentRecord;
use App\Models\Session;

class AdviserTranscriptAccess
{
    public const PURPOSES = ['competency_assessment', 'referral_review', 'emergency_verification', 'incident_investigation', 'quality_assurance'];

    public function eligible(Session $session): bool
    {
        $actor = auth()->user();
        if (! $actor || ! $actor->is_active || $actor->role !== 'adviser' || ! $actor->adviser
            || $session->helper?->adviser_id !== $actor->adviser->id
            || ! in_array($session->session_status, [Session::STATUS_COMPLETED, Session::STATUS_EVALUATED], true)
            || $session->session_type !== 'chat') {
            return false;
        }
        $consent = ConsentRecord::where('seeker_id', $session->seeker_id)->where('session_id', $session->id)
            ->where('purpose', 'transcription')->latest('id')->first();

        return $consent && $consent->version === ConsentService::VERSION && $consent->consent_given
            && ! $consent->withdrawn && $consent->decision === 'accepted';
    }

    public function grant(Session $session, string $purpose, string $reason): void
    {
        abort_unless($this->eligible($session), 403, 'Completed-session access requires current supervision and session-specific transcription consent.');
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
