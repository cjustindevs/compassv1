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
        if ($existing = $session->emergencyAlerts()->latest('id')->first()) return $existing;
        $alert = $this->createEmergencyAlert($session, $seeker, $context);

        $alert->forceFill(['screening_id'=>$context['screening_id'] ?? null,'rule_code'=>$context['rule_code'] ?? 'reported_emergency',
            'resource_ids'=>json_encode(\App\Models\EmergencyResource::where('status','active')->pluck('id')->all())])->save();
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
            'message' => 'Emergency resources are available on the emergency page. COMPASS does not replace emergency services and cannot promise an immediate response.',
            'notification_type' => 'emergency',
            'type_icon' => 'fa-life-ring',
            'link' => '/emergency',
        ]);
    }

    private function sendEmergencyNotifications(HelpSeeker $seeker, Session $session, EmergencyAlert $alert): void
    {
        $adviser = $session->helper_id ? $session->helper?->adviser : Adviser::whereHas('user', fn($q)=>$q->where('is_active',true))->oldest()->first();

        if ($adviser && !$adviser->user?->is_active) $adviser=null;
        if ($adviser) {
            $this->notifyUser($adviser->user_account_id, 'Emergency risk detected', 'Emergency risk detected for ' . $seeker->generated_alias . '. Review immediately.', '/adviser/session/' . $session->id);
            $alert->forceFill([
                'adviser_id' => $adviser->id,
                'adviser_notified' => true,
                'adviser_notified_at' => now(),
            ])->save();
        }

        SupportAudit::record('emergency_notification_attempted',$alert,['channel'=>'in_app','result'=>$adviser?'delivered':'no_active_adviser']);
        $alert->forceFill([
            'notification_sent' => (bool) $adviser,
            'notification_result' => $adviser ? 'in_app_delivered' : 'no_active_adviser',
            'notification_sent_at' => $adviser ? now() : null,
            'status' => 'notified',
        ])->save();
    }

    private function flagEmergencyCase(Session $session, HelpSeeker $seeker, array $context): void
    {
        if ($context['preserve_classification'] ?? false) {
            $session->update(['escalation_required'=>true,'requires_immediate_action'=>true,'requires_adviser_review'=>true,'emergency_triggered_at'=>now()]);
            SupportAudit::record('helper_emergency_flagged',$session);
            return;
        }
        $session->forceFill([
            'session_status'=>'emergency', 'workflow_state'=>'emergency_escalated',
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

        $session->queue?->update(['request_status'=>'cancelled','cancelled_at'=>now()]);
        SupportAudit::record('emergency_branch_activated',$session);
        // Escalation flags the case; only a separately authorized responder may open identity.
    }

    private function initiateEmergencyReferral(HelpSeeker $seeker, Session $session, EmergencyAlert $alert, array $context): void
    {
        $hasReferableConsent = $seeker->user_account_id
            && app(ConsentService::class)->valid($seeker, 'referral');

        $referral = Referral::create([
            'session_id' => $session->id,
            'helper_id' => $session->helper_id,
            'adviser_id' => $alert->adviser_id,
            'professional_id' => null,
            'priority_level' => Referral::PRIORITY_EMERGENCY,
            'help_seeker_consent' => $hasReferableConsent,
            'identity_disclosed' => false,
            'referral_reason' => $context['reason'] ?? 'Emergency escalation referral',
            'referral_date' => now(),
            'consent_requested_at' => $hasReferableConsent ? null : now(),
            'status' => $hasReferableConsent ? Referral::STATUS_PENDING_ADVISER : Referral::STATUS_CONSENT_REQUESTED,
            'professional_notified_at' => null,
        ]);

        $alert->forceFill([
            'referral_id' => $referral->id,
            'professional_referred' => false,
            'professional_referred_at' => null,
            'status' => 'pending',
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
            'type_icon' => 'fa-life-ring',
            'link' => $link,
        ]);
    }
}
