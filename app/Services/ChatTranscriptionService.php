<?php

namespace App\Services;

use App\Models\Adviser;
use App\Models\AuditLog;
use App\Models\Helper;
use App\Models\Message;
use App\Models\Referral;
use App\Models\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ChatTranscriptionService
{
    protected bool $transcriptionEnabled = true;

    protected function generateSessionTranscript(Session $session): array
    {
        $session->loadMissing(['seeker', 'helper.user']);

        $messages = Message::where('session_id', $session->id)
            ->orderBy('sent_datetime')
            ->orderBy('id')
            ->get();

        $transcript = [
            'session_id' => $session->id,
            'reference_number' => $session->reference_number,
            'seeker_alias' => $session->seeker?->generated_alias ?? 'Anonymous',
            'helper_name' => $session->helper?->public_alias ?? 'Peer Helper',
            'session_date' => ($session->created_date ?? $session->created_at)?->toDateTimeString(),
            'session_type' => $session->session_type,
            'duration' => $session->duration ?? 'N/A',
            'message_count' => $messages->count(),
            'generated_at' => now()->toDateTimeString(),
            'messages' => [],
        ];

        foreach ($messages as $message) {
            $transcript['messages'][] = [
                'id' => $message->id,
                'timestamp' => ($message->sent_datetime ?? $message->created_at)?->toDateTimeString(),
                'sender' => $message->sender === 'seeker'
                    ? $transcript['seeker_alias']
                    : $transcript['helper_name'],
                'sender_type' => $message->sender_role,
                'message' => $message->transcript ?: $message->message_text,
                'is_transcript' => (bool) $message->is_transcript,
                'transcript_verified' => $message->isTranscriptVerified(),
                'audio_url' => $message->audio_url,
            ];
        }



        return $transcript;
    }

    public function generateVoiceTranscript(Message $message, string $audioUrl): ?Message { return null; }

    public function storeChatTranscript(Session $session, string $text, string $senderType): Message
    {
        return Message::create([
            'session_id' => $session->id,
            'sender_id' => Auth::id(),
            'sender' => $senderType,
            'message_text' => $text,
            'transcript' => $text,
            'sent_datetime' => now(),
            'is_reviewed' => false,
            'is_transcript' => true,
            'transcript_generated_at' => now(),
        ]);
    }

    public function markMessageAsTranscript(Message $message): Message
    {
        if (! $message->is_transcript) {
            $message->markAsTranscript($message->message_text);
        }

        return $message;
    }

    public function getTranscriptForUser(Session $session, int $userId, string $userRole): ?array
    {
        if ($session->session_type !== 'chat' || !Auth::user()?->is_active || Auth::id() !== $userId || Auth::user()->role !== $userRole) return null;
        $allowed = match ($userRole) {
            'adviser' => $this->adviserCanAccess($session, $userId),
            'helper' => false, // BP-87: use authorized session history; transcript exports are adviser-only.
            'seeker' => false,
            'professional' => false, // Explicit transcript-purpose grants are not implemented for this role.
            default => false,
        };

        if (! $allowed) {
            return null;
        }

        $transcript = $this->generateSessionTranscript($session);
        SupportAudit::record('transcript_content_viewed', $session, ['purpose' => request()->hasSession() ? request()->session()->get('adviser_transcript.'.$session->id.'.purpose') : 'authorized_referral']);

        if ($userRole === 'seeker') {
            foreach ($transcript['messages'] as &$message) {
                if ($message['sender_type'] === 'helper') {
                    $message['sender'] = 'Peer Helper';
                }
            }
            unset($message);
            $transcript['helper_name'] = 'Peer Helper';
        }

        AuditLog::create([
            'user_account_id' => $userId,
            'action' => 'transcript_accessed',
            'module' => 'transcripts',
            'description' => 'Transcript accessed for session #' . $session->id . ' by role ' . $userRole,
        ]);

        return $transcript;
    }

    public function verifyTranscript(int $sessionId, int $adviserId): bool
    {
        $session = Session::find($sessionId);

        if (! $session || ! $this->adviserCanAccess($session, Auth::id())) {
            return false;
        }

        abort_unless(Auth::user()?->adviser?->id === $adviserId, 403);
        SupportAudit::record('transcript_verified', $session);

        Message::where('session_id', $sessionId)
            ->where('is_transcript', true)
            ->whereNull('transcript_verified_at')
            ->get()
            ->each(fn (Message $message) => $message->verifyTranscript($adviserId));

        $session->update([
            'transcript_verified' => true,
            'transcript_verified_by' => $adviserId,
            'transcript_verified_at' => now(),
        ]);

        return true;
    }

    public function getUnverifiedTranscripts(int $adviserId): array
    {
        $adviser = app(AdviserScope::class)->actor();
        abort_unless($adviser->id === $adviserId, 403);
        return Session::whereHas('helper', fn ($query) => $query->where('adviser_id', $adviserId))
            ->whereIn('session_status', [Session::STATUS_COMPLETED, Session::STATUS_EVALUATED])
            ->where('transcript_verified', false)->with('helper')->latest('id')->get()
            ->map(fn (Session $session) => [
                'session_id' => $session->id,
                'helper_name' => $session->helper?->public_alias,
                'session_date' => $session->created_date ?? $session->created_at,
                'eligible' => app(AdviserTranscriptAccess::class)->eligible($session),
            ])->all();
    }

    private function adviserCanAccess(Session $session, ?int $userId): bool
    {
        return Auth::id() === $userId && app(AdviserTranscriptAccess::class)->allowed($session);
    }

    private function professionalCanAccess(Session $session, int $userId): bool
    {
        return Referral::where('session_id', $session->id)->whereNotNull('approved_at')->where('help_seeker_consent',true)->whereIn('status',['accepted','in_progress'])
            ->whereHas('professional', fn ($query) => $query->where('user_account_id', $userId))
            ->exists();
    }
}
