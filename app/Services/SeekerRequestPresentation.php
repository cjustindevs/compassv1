<?php

namespace App\Services;

use App\Models\Session;

class SeekerRequestPresentation
{
    public const STATES = [
        'screening_required'=>'Screening required', 'concern_required'=>'Concern required',
        'session_preferences_required'=>'Review request', 'ready_for_submission'=>'Ready to submit',
        'submitted'=>'Request submitted', 'queued'=>'Waiting for a helper', 'matching'=>'Finding a helper',
        'helper_pending_acceptance'=>'Awaiting helper acceptance', 'session_ready'=>'Helper preparing',
        'session_active'=>'Chat ready', 'evaluation_pending'=>'Completed', 'closed'=>'Closed',
        'adviser_review_required'=>'Awaiting Adviser review', 'emergency_escalated'=>'Emergency support coordination',
    ];

    public static function status(Session $session): string
    {
        if ($session->expired_at) return 'Expired';
        return match ($session->session_status) {
            'completed'=>'Completed', 'evaluated'=>'Completed · Feedback submitted',
            'cancelled'=>'Cancelled', 'no_show'=>'No-show', 'scheduled'=>'Scheduled',
            default=>self::STATES[$session->workflow_state] ?? 'Request in progress',
        };
    }

    public static function duration(?string $duration): string
    {
        if (preg_match('/^\s*(\d+)(?:\s*(?:min(?:ute)?s?))*\s*$/i', $duration ?? '', $match) && (int)$match[1] > 0) {
            return (int)$match[1].' min';
        }
        return 'Duration not specified';
    }

    public static function waitLabel(Session $session): string
    {
        $started = $session->queue?->queued_at ?? $session->queue?->request_date ?? $session->submitted_at;
        if (!$started) return 'Waiting time not recorded';
        $minutes = max(0, (int)floor($started->diffInMinutes(now(), false)));
        return $minutes < 1 ? 'Waiting less than a minute' : 'Waiting '.($minutes >= 60 ? intdiv($minutes,60).' hr ' : '').($minutes % 60).' min';
    }

    public static function explanation(Session $session): string
    {
        if ($session->expired_at) return 'This waiting request expired. If you still need support, you can submit a new request.';
        return match ($session->session_status) {
            'completed'=>'Your conversation ended. You can leave feedback if you have not already submitted it.',
            'evaluated'=>'Your conversation ended and your feedback was recorded.',
            'cancelled'=>'This request was cancelled. It remains in your history.',
            'no_show'=>'This request ended without a completed conversation.',
            default=>match ($session->workflow_state) {
                'session_active'=>'Your helper started the session. Open your active request to join chat.',
                'session_ready'=>'Your helper accepted and is preparing. Chat opens when they start the session.',
                'helper_pending_acceptance'=>'A helper has been invited and has not yet accepted.',
                'adviser_review_required'=>'Your screening needs Adviser review before ordinary peer support can continue.',
                'session_preferences_required','concern_required'=>'Finish the remaining request steps before entering the matching queue.',
                default=>$session->permitsEmergencySupport() ? 'Emergency coordination and temporary peer support are tracked separately. Open your active request for both statuses.' : 'Your request is saved. Matching follows the existing priority and eligibility rules.',
            },
        };
    }
}
