<?php

namespace App\Services;

use App\Events\EmergencyEscalationInitiated;
use App\Models\Adviser;
use App\Models\AuditLog;
use App\Models\EmergencyAlert;
use App\Models\HelpSeeker;
use App\Models\Notification;
use App\Models\PsychologyProfessional;
use App\Models\Referral;
use App\Models\Session;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class EmergencyEscalationService
{
    public function escalateEmergency(Session $session, HelpSeeker $seeker, array $context = []): EmergencyAlert
    {
        $alert = $this->createEmergencyAlert($session, $seeker, $context);

        $this->displayEmergencyResources($seeker, $alert);
        $this->sendEmergencyNotifications($seeker, $session, $alert);
        $this->flagEmergencyCase($session, $seeker, $context);
        $this->initiateEmergencyReferral($seeker, $session, $alert, $context);
        $this->logEmergencyEscalation($alert);

        event(new EmergencyEscalationInitiated($alert, $seeker, $session));

        return $alert->refresh();
    }

    private function createEmergencyAlert(Session $session, HelpSeeker $seeker, array $context): EmergencyAlert
    {
        return EmergencyAlert::create([
            'seeker_id' => $seeker->id,
            'session_id' => $session->id,
            'alert_type' => $context['type'] ?? 'safety_threat',
            'triggered_by' => Auth::id(),
            'trigger_reason' => $context['reason'] ?? 'Immediate safety threat detected',
            'status' => 'pending',
        ]);
    }

    private function displayEmergencyResources(HelpSeeker $seeker, EmergencyAlert $alert): void
    {
        if (! $seeker->user_account_id) {
            return;
        }

        Notification::create([
            'user_account_id' => $seeker->user_account_id,
            'title' => 'Emergency support resources',
            'message' => 'If you are in immediate danger, call 911. You may also contact the National Mental Health Crisis Hotline 1553 or the DWCC Guidance Office.',
            'notification_type' => 'emergency',
            'type_icon' => 'SOS',
            'link' => '/emergency',
        ]);
    }

    private function sendEmergencyNotifications(HelpSeeker $seeker, Session $session, EmergencyAlert $alert): void
    {
        $adviser = $session->helper?->adviser ?? Adviser::query()->oldest()->first();

        if ($adviser) {
            $this->notifyUser($adviser->user_account_id, 'Emergency risk detected', 'Emergency risk detected for ' . $seeker->generated_alias . '. Review immediately.', '/adviser/session/' . $session->id);
            $alert->forceFill([
                'adviser_id' => $adviser->id,
                'adviser_notified' => true,
                'adviser_notified_at' => now(),
            ])->save();
        }

        foreach (User::whereIn('role', ['moderator', 'adviser'])->pluck('id') as $userId) {
            $this->notifyUser($userId, 'Emergency escalation', 'Emergency workflow initiated for ' . $seeker->generated_alias . '.', '/moderator/emergency');
        }

        $alert->forceFill([
            'notification_sent' => true,
            'notification_sent_at' => now(),
            'status' => 'notified',
        ])->save();
    }

    private function flagEmergencyCase(Session $session, HelpSeeker $seeker, array $context): void
    {
        $session->forceFill([
            'risk_level' => RiskClassificationService::RISK_EMERGENCY,
            'escalation_required' => true,
            'requires_immediate_action' => true,
            'requires_adviser_review' => true,
            'emergency_triggered_at' => now(),
        ])->save();

        $seeker->forceFill([
            'current_risk_level' => RiskClassificationService::RISK_EMERGENCY,
            'risk_last_updated' => now(),
            'has_emergency' => true,
            'last_emergency_at' => now(),
        ])->save();

        // Escalation flags the case; only a separately authorized responder may open identity.
    }

    private function initiateEmergencyReferral(HelpSeeker $seeker, Session $session, EmergencyAlert $alert, array $context): void
    {
        if (! $session->helper_id) {
            return;
        }

        $professional = PsychologyProfessional::where('is_available', true)->oldest()->first();

        $referral = Referral::create([
            'session_id' => $session->id,
            'helper_id' => $session->helper_id,
            'adviser_id' => $alert->adviser_id,
            'professional_id' => $professional?->id,
            'priority_level' => Referral::PRIORITY_EMERGENCY,
            'help_seeker_consent' => false,
            'identity_disclosed' => false,
            'referral_reason' => $context['reason'] ?? 'Emergency escalation referral',
            'referral_date' => now(),
            'status' => $professional ? Referral::STATUS_PENDING_PROFESSIONAL : Referral::STATUS_PENDING_ADVISER,
            'professional_notified_at' => $professional ? now() : null,
        ]);

        if ($professional) {
            $this->notifyUser($professional->user_account_id, 'Emergency referral assigned', 'An emergency referral requires professional review.', '/professional/referral/' . $referral->id);
        }

        $alert->forceFill([
            'referral_id' => $referral->id,
            'professional_referred' => true,
            'professional_referred_at' => now(),
            'status' => 'referred',
        ])->save();
    }

    private function logEmergencyEscalation(EmergencyAlert $alert): void
    {
        AuditLog::create([
            'user_account_id' => Auth::id(),
            'action' => 'emergency_escalation',
            'module' => 'escalation',
            'description' => 'Emergency alert #' . $alert->id . ' created for session #' . $alert->session_id,
        ]);
    }

    private function notifyUser(?int $userId, string $title, string $message, string $link): void
    {
        if (! $userId) {
            return;
        }

        Notification::create([
            'user_account_id' => $userId,
            'title' => $title,
            'message' => $message,
            'notification_type' => 'emergency',
            'type_icon' => 'SOS',
            'link' => $link,
        ]);
    }
}
