<?php

namespace App\Services;

use App\Models\{ConsentRecord, Session, User, VoiceCall, VoiceListen};
use Illuminate\Support\Facades\DB;

/**
 * Live voice supervision: the Adviser who supervises a session's Helper may listen, receive-only,
 * to a connected call when the Seeker agreed to supervision for that session. The Adviser's browser
 * never asks for a microphone, nothing is recorded, every listen is audited, and the Seeker and
 * Helper see a notice while an Adviser is listening.
 */
class VoiceSupervisionService
{
    public const PURPOSES = AdviserTranscriptAccess::PURPOSES;

    public function hasConsent(Session $session): bool
    {
        $consent = ConsentRecord::where('seeker_id', $session->seeker_id)->where('session_id', $session->id)
            ->where('purpose', 'voice_supervision')->latest('id')->first();

        return $consent && $consent->version === ConsentService::VERSION && $consent->consent_given
            && ! $consent->withdrawn && $consent->decision === 'accepted';
    }

    /** The open, answered call of a session, if any. */
    public function listenableCall(Session $session): ?VoiceCall
    {
        $call = VoiceCall::where('session_id', $session->id)->whereIn('status', ['connecting', 'connected'])->latest('created_at')->first();

        return $call && ! app(VoiceCallService::class)->stale($call) ? $call : null;
    }

    /** Why this Adviser cannot listen right now, or null when they can. */
    public function blocker(User $user, Session $session): ?string
    {
        $adviser = $user->role === 'adviser' && $user->is_active ? $user->adviser : null;
        if (! $adviser || (int) $session->helper?->adviser_id !== (int) $adviser->id) return 'Only the Adviser who supervises this Helper can listen.';
        if (! $session->isActive()) return 'This session is no longer active.';
        if (! app(VoiceConfiguration::class)->ready()) return 'Voice is unavailable right now.';
        if (! $this->listenableCall($session)) return 'There is no connected call to listen to.';
        if (! $this->hasConsent($session)) return 'The Seeker has not agreed to live supervision for this session, so this call cannot be listened to.';

        return null;
    }

    public function start(User $user, Session $session, string $purpose, string $reason): VoiceListen
    {
        abort_unless(in_array($purpose, self::PURPOSES, true), 422, 'Select one of the listed purposes.');
        abort_unless(mb_strlen(trim($reason)) >= 10 && mb_strlen($reason) <= 500, 422, 'Explain your reason in 10 to 500 characters.');

        return DB::transaction(function () use ($user, $session, $purpose, $reason) {
            $session = Session::whereKey($session->id)->lockForUpdate()->firstOrFail();
            abort_if($blocker = $this->blocker($user, $session), 409, $blocker);
            $call = $this->listenableCall($session);
            VoiceListen::where('call_id', $call->id)->where('adviser_user_id', $user->id)->active()->update(['ended_at' => now()]);
            $listen = VoiceListen::create(['call_id' => $call->id, 'session_id' => $session->id, 'adviser_user_id' => $user->id,
                'purpose' => $purpose, 'reason' => trim($reason), 'started_at' => now()]);
            SupportAudit::record('adviser_voice_listen_started', $session, ['listen_id' => $listen->id, 'call_id' => $call->id, 'purpose' => $purpose]);

            return $listen;
        });
    }

    public function stop(VoiceListen $listen, string $reason = 'adviser_stopped'): void
    {
        if ($listen->ended_at) return;
        $listen->update(['ended_at' => now()]);
        SupportAudit::record('adviser_voice_listen_ended', Session::findOrFail($listen->session_id), ['listen_id' => $listen->id, 'reason' => $reason]);
    }

    /** End every listen on a call (the call ended or failed). */
    public function endForCall(string $callId): void
    {
        foreach (VoiceListen::where('call_id', $callId)->active()->get() as $listen) $this->stop($listen, 'call_ended');
    }

    /** Listeners the call participants must connect to: only while the call is answered. */
    public function listenersFor(VoiceCall $call): array
    {
        if (! in_array($call->status, ['connecting', 'connected'], true)) return [];

        return VoiceListen::where('call_id', $call->id)->active()->orderBy('id')->pluck('id')->map(fn ($id) => ['id' => (int) $id])->all();
    }
}
