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

    public function generateSessionTranscript(Session $session): array
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
            'helper_name' => $session->helper?->user?->name ?? $session->helper?->full_name ?? 'Peer Helper',
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

        $session->forceFill(['transcript_generated_at' => now()])->save();

        return $transcript;
    }

    public function generateVoiceTranscript(Message $message, string $audioUrl): ?Message
    {
        if (! $this->transcriptionEnabled || ! $message->voice_consent_obtained) {
            Log::info('Voice transcription skipped', [
                'message_id' => $message->id,
                'consent' => (bool) $message->voice_consent_obtained,
            ]);

            return null;
        }

        $message->update([
            'audio_url' => $audioUrl,
            'transcript' => '[Voice transcription: ' . $audioUrl . ']',
            'is_transcript' => true,
            'transcript_generated_at' => now(),
        ]);

        return $message;
    }

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
        $allowed = match ($userRole) {
            'adviser' => $this->adviserCanAccess($session, $userId),
            'helper' => (int) $session->helper_id === (int) Auth::user()?->helper?->id,
            'seeker' => (int) $session->seeker_id === (int) Auth::user()?->helpSeeker?->id,
            'professional' => $this->professionalCanAccess($session, $userId),
            default => false,
        };

        if (! $allowed) {
            return null;
        }

        $transcript = $this->generateSessionTranscript($session);

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
        $helperIds = Helper::where('adviser_id', $adviserId)->pluck('id');

        return Session::whereIn('helper_id', $helperIds)
            ->whereIn('session_status', [Session::STATUS_COMPLETED, Session::STATUS_EVALUATED])
            ->where('transcript_verified', false)
            ->with(['seeker', 'helper.user'])
            ->latest('created_date')
            ->get()
            ->map(function (Session $session) {
                $messages = Message::where('session_id', $session->id)
                    ->where('is_transcript', true)
                    ->whereNull('transcript_verified_at')
                    ->orderBy('sent_datetime')
                    ->get();

                if ($messages->isEmpty()) {
                    return null;
                }

                return [
                    'session_id' => $session->id,
                    'seeker_alias' => $session->seeker?->generated_alias ?? 'Anonymous',
                    'helper_name' => $session->helper?->user?->name ?? $session->helper?->full_name ?? 'Peer Helper',
                    'session_date' => $session->created_date ?? $session->created_at,
                    'message_count' => $messages->count(),
                    'messages' => $messages,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function adviserCanAccess(Session $session, ?int $userId): bool
    {
        $adviser = Adviser::where('user_account_id', $userId)->first();

        return $adviser && $session->helper && (int) $session->helper->adviser_id === (int) $adviser->id;
    }

    private function professionalCanAccess(Session $session, int $userId): bool
    {
        return Referral::where('session_id', $session->id)
            ->whereHas('professional', fn ($query) => $query->where('user_account_id', $userId))
            ->exists();
    }
}
