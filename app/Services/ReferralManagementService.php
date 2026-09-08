<?php

namespace App\Services;

use App\Events\ReferralApproved;
use App\Events\ReferralCompleted;
use App\Events\ReferralCreated;
use App\Models\Adviser;
use App\Models\AuditLog;
use App\Models\HelpSeeker;
use App\Models\Notification;
use App\Models\PsychologyProfessional;
use App\Models\Referral;
use App\Models\Session;
use Illuminate\Support\Facades\Auth;

class ReferralManagementService
{
    public function createReferral(Session $session, HelpSeeker $seeker, array $data): Referral
    {
        $this->validateReferralCriteria($session, $data);

        $existing = $this->checkExistingReferrals($seeker);
        if ($existing) {
            return $existing;
        }

        $referral = Referral::create([
            'session_id' => $session->id,
            'helper_id' => $session->helper_id,
            'adviser_id' => $session->helper?->adviser_id,
            'priority_level' => $data['urgency'] ?? $data['priority_level'] ?? $session->risk_level,
            'help_seeker_consent' => false,
            'identity_disclosed' => false,
            'referral_reason' => $data['reason'] ?? $data['referral_reason'],
            'referral_date' => now(),
            'status' => Referral::STATUS_PENDING_ADVISER,
        ]);

        event(new ReferralCreated($referral));
        $this->notifyAdviser($referral);

        return $referral;
    }

    public function reviewReferral(Referral $referral, Adviser $adviser, array $data): Referral
    {
        $this->validateAdviserAuthorization($adviser, $referral);
        abort_unless($referral->status === Referral::STATUS_PENDING_ADVISER, 409, 'This referral has already been reviewed.');

        $approved = (bool) ($data['approved'] ?? false);

        $referral->forceFill([
            'adviser_id' => $adviser->id,
            'reviewed_at' => now(),
            'review_notes' => $data['notes'] ?? null,
        ]);

        if ($approved) {
            $referral->forceFill([
                'approved_at' => now(),
                'status' => Referral::STATUS_PENDING_CONSENT,
                'help_seeker_consent' => false,
                'consent_requested_at' => now(),
                'consent_obtained_at' => null,
            ])->save();

            if ($referral->help_seeker_consent) {
                $this->forwardToProfessional($referral);
            } else {
                $this->requestConsent($referral);
            }

            if ($referral->helper?->user_account_id) {
                event(new ReferralApproved($referral, $referral->helper->user_account_id));
            }
        } else {
            $referral->forceFill([
                'status' => Referral::STATUS_DECLINED,
                'declined_at' => now(),
                'decline_reason' => $data['decline_reason'] ?? null,
                'closed_date' => now(),
            ])->save();

            $this->notifyHelper($referral, 'Referral declined', 'Your referral was declined by the adviser.');
            $this->notifySeeker($referral, 'Referral update', 'Your referral will not proceed at this time.');
        }

        return $referral->refresh();
    }

    public function processConsent(Referral $referral, bool $consentGiven): Referral
    {
        abort_unless($referral->approved_at && $referral->status === Referral::STATUS_PENDING_CONSENT, 409, 'Adviser approval is required before consent.');
        if ($consentGiven) {
            $referral->forceFill([
                'help_seeker_consent' => true,
                'consent_obtained_at' => now(),
                'status' => Referral::STATUS_PENDING_PROFESSIONAL,
            ])->save();

            $this->forwardToProfessional($referral);
        } else {
            $referral->forceFill([
                'help_seeker_consent' => false,
                'consent_declined_at' => now(),
                'status' => Referral::STATUS_CLOSED,
                'closure_notes' => 'Help seeker declined referral consent.',
                'closed_date' => now(),
            ])->save();

            if ($this->exceedsPeerSupportScope($referral)) {
                $this->initiateSafetyProtocol($referral);
            }
        }

        return $referral->refresh();
    }

    public function forwardToProfessional(Referral $referral): Referral
    {
        $professional = $referral->professional ?: $this->getAvailableProfessional($referral);

        if (! $professional) {
            $referral->forceFill(['status' => Referral::STATUS_NO_PROFESSIONAL_AVAILABLE])->save();
            $this->escalateNoProfessional($referral);
            return $referral->refresh();
        }

        $referral->forceFill([
            'professional_id' => $professional->id,
            'status' => Referral::STATUS_PENDING_PROFESSIONAL,
            'professional_notified_at' => now(),
        ])->save();

        $this->notifyUser($professional->user_account_id, 'New referral assigned', 'A referral is pending your review.', '/professional/referral/' . $referral->id, 'referral');

        return $referral->refresh();
    }

    public function acceptReferral(Referral $referral, PsychologyProfessional $professional): Referral
    {
        $referral->forceFill([
            'professional_id' => $professional->id,
            'status' => Referral::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ])->save();

        $this->transferResponsibility($referral, $professional);

        return $referral->refresh();
    }

    public function updateReferralOutcome(Referral $referral, array $data): Referral
    {
        $status = $data['status'] ?? $referral->status;

        $referral->forceFill([
            'status' => $status,
            'outcome' => $data['outcome'] ?? $referral->outcome,
            'follow_up_required' => (bool) ($data['follow_up_required'] ?? $referral->follow_up_required),
            'follow_up_notes' => $data['follow_up_notes'] ?? $referral->follow_up_notes,
            'completed_at' => $status === Referral::STATUS_COMPLETED ? now() : $referral->completed_at,
            'closed_date' => $status === Referral::STATUS_CLOSED ? now() : $referral->closed_date,
        ])->save();

        if ($status === Referral::STATUS_COMPLETED) {
            event(new ReferralCompleted($referral));
        }

        return $referral->refresh();
    }

    private function validateReferralCriteria(Session $session, array $data): void
    {
        if (! $session->helper_id) {
            throw new \InvalidArgumentException('Referral requires a helper-assigned session.');
        }

        if (empty($data['reason']) && empty($data['referral_reason'])) {
            throw new \InvalidArgumentException('Referral reason is required.');
        }
    }

    private function checkExistingReferrals(HelpSeeker $seeker): ?Referral
    {
        return Referral::whereHas('session', fn ($query) => $query->where('seeker_id', $seeker->id))
            ->whereIn('status', [Referral::STATUS_PENDING_ADVISER, Referral::STATUS_PENDING_CONSENT, Referral::STATUS_PENDING_PROFESSIONAL, Referral::STATUS_ACCEPTED, Referral::STATUS_IN_PROGRESS])
            ->first();
    }

    private function validateAdviserAuthorization(Adviser $adviser, Referral $referral): void
    {
        $helper = $referral->session?->helper;
        if (! $helper?->adviser_id || $helper->adviser_id !== $adviser->id) {
            throw new \RuntimeException('Adviser is not authorized to review this referral.');
        }
    }

    private function getAvailableProfessional(Referral $referral): ?PsychologyProfessional
    {
        return PsychologyProfessional::where('is_available', true)->oldest()->first();
    }

    private function exceedsPeerSupportScope(Referral $referral): bool
    {
        return in_array($referral->priority_level, [Referral::PRIORITY_HIGH, Referral::PRIORITY_EMERGENCY], true);
    }

    private function initiateSafetyProtocol(Referral $referral): void
    {
        AuditLog::create([
            'user_account_id' => Auth::id(),
            'action' => 'referral_consent_declined_safety_protocol',
            'module' => 'referrals',
            'description' => 'Referral #' . $referral->id . ' exceeded peer-support scope after consent was declined.',
        ]);

        if ($referral->adviser?->user_account_id) {
            $this->notifyUser($referral->adviser->user_account_id, 'Referral consent declined', 'Safety monitoring is required for referral #' . $referral->id . '.', '/adviser/referral/' . $referral->id, 'referral');
        }
    }

    private function requestConsent(Referral $referral): void
    {
        $seekerUserId = $referral->session?->seeker?->user_account_id;
        $this->notifyUser($seekerUserId, 'Referral consent requested', 'An adviser approved a referral recommendation. Please review consent.', '/referrals/' . $referral->id . '/identity', 'referral');
    }

    private function transferResponsibility(Referral $referral, PsychologyProfessional $professional): void
    {
        AuditLog::create([
            'user_account_id' => Auth::id(),
            'action' => 'referral_responsibility_transferred',
            'module' => 'referrals',
            'description' => 'Referral #' . $referral->id . ' accepted by professional #' . $professional->id . '.',
        ]);
    }

    private function notifyAdviser(Referral $referral): void
    {
        if ($referral->adviser?->user_account_id) {
            $this->notifyUser($referral->adviser->user_account_id, 'New referral request', 'A helper submitted referral #' . $referral->id . ' for review.', '/adviser/referral/' . $referral->id, 'referral');
        }
    }

    private function notifyHelper(Referral $referral, string $title, string $message): void
    {
        $this->notifyUser($referral->helper?->user_account_id, $title, $message, '/helper/cases', 'referral');
    }

    private function notifySeeker(Referral $referral, string $title, string $message): void
    {
        $this->notifyUser($referral->session?->seeker?->user_account_id, $title, $message, '/session/chat', 'referral');
    }

    private function escalateNoProfessional(Referral $referral): void
    {
        foreach (\App\Models\User::whereIn('role', ['moderator', 'adviser'])->pluck('id') as $userId) {
            $this->notifyUser($userId, 'No professional available', 'Referral #' . $referral->id . ' needs professional assignment.', '/adviser/referral/' . $referral->id, 'referral');
        }
    }

    private function notifyUser(?int $userId, string $title, string $message, string $link, string $type): void
    {
        if (! $userId) {
            return;
        }

        Notification::create([
            'user_account_id' => $userId,
            'title' => $title,
            'message' => $message,
            'notification_type' => $type,
            'type_icon' => $type === 'emergency' ? 'SOS' : 'REF',
            'link' => $link,
        ]);
    }
}
