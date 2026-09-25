<?php

namespace App\Services;

use App\Events\ReferralApproved;
use App\Events\ReferralCompleted;
use App\Events\ReferralConsentRequested;
use App\Events\ReferralConsentUpdated;
use App\Events\ReferralCreated;
use App\Models\Adviser;
use App\Models\AuditLog;
use App\Models\HelpSeeker;
use App\Models\Notification;
use App\Models\PsychologyProfessional;
use App\Models\Referral;
use App\Models\Session;
use App\Traits\BroadcastsSafely;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReferralManagementService
{
    use BroadcastsSafely;
    private const OPEN_STATUSES = [
        'status' => [Referral::STATUS_PENDING_ADVISER, Referral::STATUS_PENDING_CONSENT, Referral::STATUS_CONSENT_REQUESTED, Referral::STATUS_PENDING_PROFESSIONAL, Referral::STATUS_NO_PROFESSIONAL_AVAILABLE, Referral::STATUS_ACCEPTED, Referral::STATUS_IN_PROGRESS],
    ];

    /** Backward-compatible route name; recommendations now go to the adviser first. */
    public function requestConsent(Session $session, array $summary): Referral
    {
        abort_unless(Auth::user()?->is_active && Auth::user()->role === 'helper' && Auth::user()->helper?->id === $session->helper_id,403);
        return DB::transaction(function () use ($session,$summary) {
            $session = Session::whereKey($session->id)->lockForUpdate()->firstOrFail();
            abort_unless($session->isActive(),409,'Referral recommendations require an active session.');
            return $this->createReferral($session,$session->seeker,['reason'=>$summary['summary'] ?? '']);
        },3);
    }

    public function decideConsentRequest(Referral $referral, bool $accepted): Referral
    {
        return $this->processConsent($referral,$accepted);
    }

    public function submitAfterConsent(Referral $referral, array $data): Referral
    {
        $referral = Referral::lockForUpdate()->findOrFail($referral->id);
        abort_unless($referral->status === Referral::STATUS_CONSENT_REQUESTED, 409, 'Consent is required before submitting the referral.');
        abort_unless($referral->help_seeker_consent, 409, 'The help seeker must accept the referral consent first.');

        $seeker = $referral->session?->seeker;
        abort_unless($seeker, 409);
        abort_unless(app(ConsentService::class)->valid($seeker, 'referral', $referral->id), 409, 'The referral consent is no longer valid.');

        $referral = DB::transaction(function () use ($referral, $data) {
            $referral = Referral::lockForUpdate()->findOrFail($referral->id);
            abort_unless($referral->status === Referral::STATUS_CONSENT_REQUESTED, 409, 'This referral has already been submitted.');
            abort_unless($referral->help_seeker_consent, 409, 'The help seeker must accept the referral consent first.');

            $referral->forceFill([
                'referral_reason' => $data['referral_reason'],
                'priority_level' => $data['priority_level'] ?? $referral->priority_level,
                'status' => Referral::STATUS_PENDING_ADVISER,
            ])->save();

            SupportAudit::record('referral_submitted', $referral, ['status' => Referral::STATUS_PENDING_ADVISER]);
            $this->notifyAdviser($referral);

            return $referral->refresh();
        }, 3);

        return $referral;
    }

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

        SupportAudit::record('referral_proposed',$referral);
        $this->broadcastSafely(new ReferralCreated($referral));
        $this->notifyAdviser($referral);

        return $referral;
    }

    public function clarify(Referral $referral, string $text, bool $response = false, ?string $revisedReason = null): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($referral, $text, $response, $revisedReason) {
            $referral = Referral::lockForUpdate()->findOrFail($referral->id);
            $actor = Auth::user();
            if ($response) {
                abort_unless($actor?->is_active && $actor->role === 'helper' && $actor->helper?->id === $referral->helper_id, 403);
            } else app(AdviserScope::class)->referral($referral);
            abort_unless($referral->status === Referral::STATUS_PENDING_ADVISER, 409);
            abort_unless(strlen(trim($text)) >= 10 && mb_strlen($text) <= 2000, 422);
            if ($response) {
                abort_unless($referral->clarification_requested_at && !$referral->clarification_received_at, 409);
                $referral->forceFill(['clarification_response'=>$text, 'clarification_received_at'=>now()])->save();
                if ($revisedReason !== null) {
                    abort_unless(trim($revisedReason) !== '' && mb_strlen($revisedReason) <= 1000, 422, 'A revised referral reason cannot be empty.');
                    $referral->forceFill(['referral_reason'=>trim($revisedReason)])->save();
                    app(SupervisionVersions::class)->record($referral, 'Helper revised the referral recommendation');
                }
            } else {
                abort_if($referral->clarification_requested_at && !$referral->clarification_received_at, 409);
                $referral->forceFill(['clarification_question'=>$text, 'clarification_requested_at'=>now(), 'clarification_received_at'=>null, 'clarification_response'=>null])->save();
            }
            app(SupervisionVersions::class)->record($referral, $response ? 'Helper clarification submitted' : 'Adviser requested clarification');
            SupportAudit::record($response ? 'referral_clarification_received' : 'referral_clarification_requested', $referral);
            $this->notifyUser($response ? $referral->adviser?->user_account_id : $referral->helper?->user_account_id,
                'Referral clarification', 'A referral clarification requires your attention.',
                $response ? '/adviser/referral/'.$referral->id : '/helper/referral/status/'.$referral->id, 'referral');
        }, 3);
    }

    public function reviewReferral(Referral $referral, Adviser $adviser, array $data): Referral
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($referral, $adviser, $data) {
            $referral = Referral::lockForUpdate()->findOrFail($referral->id);
            return $this->recordReview($referral, $adviser, $data);
        }, 3);
    }

    private function recordReview(Referral $referral, Adviser $adviser, array $data): Referral
    {
        $this->validateAdviserAuthorization($adviser, $referral);
        abort_unless(in_array($referral->status,[Referral::STATUS_PENDING_ADVISER,Referral::STATUS_CONSENT_REQUESTED],true) && !$referral->reviewed_at, 409, 'This referral has already been reviewed.');

        abort_unless(array_key_exists('approved', $data), 422);
        $approved = (bool) $data['approved'];
        abort_if($approved && $referral->clarification_requested_at && !$referral->clarification_received_at,409,'The Helper must respond to the clarification request before approval.');
        abort_unless(trim((string) ($approved ? ($data['notes'] ?? '') : ($data['decline_reason'] ?? ''))) !== '', 422, 'A review reason is required.');
        abort_if(!empty($data['consent_obtained']) || !empty($data['professional_id']), 422, 'Approval cannot assert consent or assign a professional.');

        $consentAlreadyGiven = (bool) $referral->help_seeker_consent;

        $referral->forceFill([
            'adviser_id' => $adviser->id,
            'reviewed_at' => now(),
            'review_notes' => $data['notes'] ?? null,
        ]);

        if ($approved) {
            $referral->forceFill([
                'professional_id' => null,
                'approved_at' => now(),
                'status' => $consentAlreadyGiven ? Referral::STATUS_PENDING_PROFESSIONAL : Referral::STATUS_PENDING_CONSENT,
                'help_seeker_consent' => $consentAlreadyGiven,
                'consent_requested_at' => $consentAlreadyGiven ? $referral->consent_requested_at : now(),
                'consent_obtained_at' => $consentAlreadyGiven ? ($referral->consent_obtained_at ?? now()) : null,
            ])->save();

            if ($consentAlreadyGiven) {
                $this->notifySeekerProvideIdentity($referral);
            } else {
                $this->notifySeekerPostApprovalConsent($referral);
            }

            if ($referral->helper?->user_account_id) {
                $this->broadcastSafely(new ReferralApproved($referral, $referral->helper->user_account_id));
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

        SupportAudit::record($approved ? 'referral_approved' : 'referral_rejected', $referral, ['from' => Referral::STATUS_PENDING_ADVISER, 'to' => $referral->status, 'purpose' => 'referral_review']);
        app(SupervisionVersions::class)->record($referral, $approved ? 'Adviser approved referral review' : 'Adviser rejected referral review');
        return $referral->refresh();
    }

    public function processConsent(Referral $referral, bool $consentGiven): Referral
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($referral,$consentGiven) {
            $referral=Referral::lockForUpdate()->findOrFail($referral->id);
            return $this->recordConsent($referral,$consentGiven);
        });
    }
    private function recordConsent(Referral $referral, bool $consentGiven): Referral
    {
        \Illuminate\Support\Facades\Gate::authorize('update',$referral);
        abort_unless($referral->approved_at && $referral->status === Referral::STATUS_PENDING_CONSENT, 409, 'Adviser approval is required before consent.');
        app(ConsentService::class)->decide(Auth::user(),'referral',$consentGiven?'accepted':'declined',$referral->session_id,$referral->id,'referral');
        if ($consentGiven) {
            $referral->forceFill([
                'help_seeker_consent' => true,
                'consent_obtained_at' => now(),
                'status' => Referral::STATUS_PENDING_PROFESSIONAL,
            ])->save();

            $this->notifySeekerProvideIdentity($referral);
        } else {
            $referral->forceFill([
                'help_seeker_consent' => false,
                'consent_declined_at' => now(),
                'status' => Referral::STATUS_CLOSED,
                'closure_notes' => 'Help seeker declined referral consent.',
                'closed_date' => now(),
            ])->save();

            $this->notifySeekerReferralDeclined($referral);
            if ($this->exceedsPeerSupportScope($referral)) {
                $this->initiateSafetyProtocol($referral);
            }
        }

        $this->notifyHelper($referral,'Referral consent updated','The seeker recorded a referral decision.');
        $this->notifyUser($referral->adviser?->user_account_id,'Referral consent updated','The seeker recorded a referral decision.','/adviser/referral/'.$referral->id,'referral');
        $this->broadcastSafely(new ReferralConsentUpdated($referral,$consentGiven));
        return $referral->refresh();
    }

    public function forwardToProfessional(Referral $referral): Referral
    {
        return DB::transaction(function () use ($referral) {
        $referral = Referral::whereKey($referral->id)->lockForUpdate()->firstOrFail();
        abort_unless(in_array($referral->status,[Referral::STATUS_PENDING_PROFESSIONAL,Referral::STATUS_NO_PROFESSIONAL_AVAILABLE],true),409);
        if ($referral->professional_id && $referral->professional_notified_at) return $referral;
        abort_unless($referral->approved_at && $referral->help_seeker_consent,409);
        abort_unless(app(IdentityVaultService::class)->hasCurrentSubmission($referral),409,'Complete identity submission before professional coordination.');
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

        SupportAudit::record('referral_forwarded',$referral,['professional_id'=>$professional->id]);
        return $referral->refresh();
        },3);
    }

    public function assignProfessional(Referral $referral, int $professionalId, string $reason): Referral
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($referral, $professionalId, $reason) {
            $referral = Referral::lockForUpdate()->findOrFail($referral->id);
            app(AdviserScope::class)->referral($referral);
            abort_unless(trim($reason) !== '' && mb_strlen($reason) <= 1000, 422);
            abort_unless($referral->approved_at && $referral->help_seeker_consent && in_array($referral->status, [Referral::STATUS_PENDING_PROFESSIONAL, Referral::STATUS_NO_PROFESSIONAL_AVAILABLE], true), 409);
            abort_unless(app(IdentityVaultService::class)->hasCurrentSubmission($referral),409,'Complete identity submission before professional coordination.');
            $professional = PsychologyProfessional::whereKey($professionalId)->where('is_available', true)
                ->whereHas('user', fn ($query) => $query->where('is_active', true)->where('role', 'professional'))->first();
            abort_unless($professional, 422, 'Select an active, available professional.');
            $previous = $referral->professional_id;
            abort_if($previous === $professionalId, 409, 'This professional is already assigned.');
            $referral->update(['professional_id' => $professionalId, 'status' => Referral::STATUS_PENDING_PROFESSIONAL, 'professional_notified_at' => now()]);
            SupportAudit::record('referral_professional_assigned', $referral, ['previous_professional_id' => $previous, 'professional_id' => $professionalId, 'reason' => $reason]);
            $this->notifyUser($professional->user_account_id, 'Referral assigned', 'A referral is pending your review.', '/professional/referral/'.$referral->id, 'referral');
            return $referral;
        }, 3);
    }

    public function declineProfessional(Referral $referral, string $reason): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($referral, $reason) {
            $referral = Referral::lockForUpdate()->findOrFail($referral->id);
            abort_unless(Auth::user()?->is_active && Auth::user()?->role === 'professional' && Auth::user()?->psychologyProfessional?->id === $referral->professional_id && $referral->professional_id,403);
            abort_unless($referral->approved_at && $referral->help_seeker_consent && $referral->status === Referral::STATUS_PENDING_PROFESSIONAL,409);
            abort_unless(trim($reason) !== '' && mb_strlen($reason) <= 500,422);
            $previous = $referral->professional_id;
            app(SupervisionVersions::class)->record($referral,'Before professional declined assignment');
            $referral->update(['professional_id'=>null,'status'=>Referral::STATUS_NO_PROFESSIONAL_AVAILABLE,'decline_reason'=>$reason]);
            app(SupervisionVersions::class)->record($referral,'Professional declined assignment; Adviser coordination required');
            SupportAudit::record('professional_assignment_declined',$referral,['previous_professional_id'=>$previous]);
            $this->notifyUser($referral->adviser?->user_account_id,'Professional assignment declined','A referral requires a new professional assignment.','/adviser/referral/'.$referral->id,'referral');
        },3);
    }

    public function acceptReferral(Referral $referral, PsychologyProfessional $professional): Referral
    {
        return \Illuminate\Support\Facades\DB::transaction(fn()=> $this->recordAcceptance(Referral::lockForUpdate()->findOrFail($referral->id),$professional),3);
    }
    private function recordAcceptance(Referral $referral, PsychologyProfessional $professional): Referral
    {
        abort_unless(Auth::user()?->role==='professional' && Auth::user()?->is_active && Auth::user()?->psychologyProfessional?->id===$professional->id && $referral->professional_id===$professional->id && $referral->approved_at && $referral->help_seeker_consent && $referral->status===Referral::STATUS_PENDING_PROFESSIONAL,403);
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
        return \Illuminate\Support\Facades\DB::transaction(fn()=> $this->recordOutcome(Referral::lockForUpdate()->findOrFail($referral->id),$data),3);
    }
    private function recordOutcome(Referral $referral, array $data): Referral
    {
        abort_unless(Auth::user()?->role==='professional' && Auth::user()?->is_active && Auth::user()?->psychologyProfessional?->id === $referral->professional_id && $referral->professional_id,403);
        $referral->refresh();
        abort_unless($referral->approved_at && $referral->help_seeker_consent && in_array($referral->status,[Referral::STATUS_ACCEPTED,Referral::STATUS_IN_PROGRESS]),409);
        $status = $data['status'] ?? $referral->status;
        abort_if($referral->status === Referral::STATUS_IN_PROGRESS && $status === Referral::STATUS_ACCEPTED,409,'A case cannot return to the accepted state.');
        abort_unless(in_array($status,[Referral::STATUS_ACCEPTED,Referral::STATUS_IN_PROGRESS,Referral::STATUS_COMPLETED,Referral::STATUS_CLOSED]),422);

        $referral->forceFill([
            'status' => $status,
            'outcome' => $data['outcome'] ?? $referral->outcome,
            'follow_up_required' => (bool) ($data['follow_up_required'] ?? $referral->follow_up_required),
            'follow_up_notes' => $data['follow_up_notes'] ?? $referral->follow_up_notes,
            'completed_at' => $status === Referral::STATUS_COMPLETED ? now() : $referral->completed_at,
            'closed_date' => in_array($status,[Referral::STATUS_CLOSED,Referral::STATUS_COMPLETED],true) ? now() : $referral->closed_date,
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
            ->whereIn('status', [Referral::STATUS_PENDING_ADVISER, Referral::STATUS_PENDING_CONSENT, Referral::STATUS_CONSENT_REQUESTED, Referral::STATUS_PENDING_PROFESSIONAL, Referral::STATUS_NO_PROFESSIONAL_AVAILABLE, Referral::STATUS_ACCEPTED, Referral::STATUS_IN_PROGRESS])
            ->first();
    }

    private function validateAdviserAuthorization(Adviser $adviser, Referral $referral): void
    {
        $actor = app(AdviserScope::class)->actor();
        abort_unless($actor->id === $adviser->id, 403);
        app(AdviserScope::class)->referral($referral);
    }

    private function getAvailableProfessional(Referral $referral): ?PsychologyProfessional
    {
        return PsychologyProfessional::where('is_available', true)->whereHas('user',fn($q)=>$q->where('is_active',true))->oldest()->first();
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

    private function notifySeekerPostApprovalConsent(Referral $referral): void
    {
        $seekerUserId = $referral->session?->seeker?->user_account_id;
        $this->notifyUser($seekerUserId, 'Referral consent requested', 'An adviser approved a referral recommendation. Please review your consent decision.', '/seeker/referrals', 'referral');
    }

    private function notifySeekerProvideIdentity(Referral $referral): void
    {
        $seekerUserId = $referral->session?->seeker?->user_account_id;
        $this->notifyUser($seekerUserId, 'Referral approved — provide contact details',
            'Your adviser approved the referral and you have consented. Provide your contact details so the assigned professional can coordinate when needed.',
            '/referrals/' . $referral->id . '/identity', 'referral');
    }

    private function notifySeekerReferralDeclined(Referral $referral): void
    {
        $this->notifyUser($referral->session?->seeker?->user_account_id, 'Referral declined',
            'Your decision is respected. You are not alone — rest, self-care tools, and crisis hotlines are always available to you.',
            '/selfhelp', 'referral');
    }

    private function notifySeekerConsentRequested(Referral $referral): void
    {
        $seekerUserId = $referral->session?->seeker?->user_account_id;
        $this->notifyUser($seekerUserId, 'Referral consent requested', 'Your helper recommended a professional referral. Please review it in the chat window.', '/session/chat', 'referral');
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
        $this->notifyUser($referral->adviser?->user_account_id, 'No professional available',
            'An authorized referral needs professional assignment.', '/adviser/referral/'.$referral->id, 'referral');
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
