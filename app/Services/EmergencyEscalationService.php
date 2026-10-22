<?php

namespace App\Services;

use App\Events\EmergencyEscalationInitiated;
use App\Models\Adviser;
use App\Models\AuditLog;
use App\Models\EmergencyAlert;
use App\Models\EmergencyResource;
use App\Models\HelpSeeker;
use App\Models\IncidentReport;
use App\Models\Notification;
use App\Models\QueueRequest;
use App\Models\Referral;
use App\Models\Session;
use App\Traits\BroadcastsSafely;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EmergencyEscalationService
{
    use BroadcastsSafely;

    public function escalateEmergency(Session $session, HelpSeeker $seeker, array $context = []): EmergencyAlert
    {
        return DB::transaction(function () use ($session, $seeker, $context) {
            $session = Session::lockForUpdate()->findOrFail($session->id);
            abort_unless((int) $session->seeker_id === (int) $seeker->id, 403);
            $chatReport = isset($context['chat_incident_id']);
            $existing = $session->emergencyAlerts()->when($chatReport, fn ($q) => $q->whereNotIn('status', ['resolved', 'closed']))->latest('id')->first();
            if ($existing) {
                if ($chatReport) {
                    // A distinct chat report needs a fresh stored signal even when
                    // an intake alert already exists. Duplicate form submissions
                    // are stopped by the locked incident check in the controller.
                    $this->sendEmergencyNotifications($seeker, $session, $existing, true);
                    $this->flagEmergencyCase($session, $seeker, $context);
                    $this->linkExistingReferral($existing, $session);
                    SupportAudit::record('chat_emergency_reported', $existing, ['incident_id' => $context['chat_incident_id']]);
                } else {
                    app(EmergencyStaffNotifications::class)->deliver($existing);
                }

                return $existing->refresh();
            }
            $alert = $this->createEmergencyAlert($session, $seeker, $context);

            $alert->forceFill(['screening_id' => $context['screening_id'] ?? null, 'rule_code' => $context['rule_code'] ?? 'reported_emergency',
                'resource_ids' => json_encode(EmergencyResource::where('status', 'active')->pluck('id')->all())])->save();
            $this->displayEmergencyResources($seeker, $alert);
            $this->sendEmergencyNotifications($seeker, $session, $alert, $chatReport);
            $this->flagEmergencyCase($session, $seeker, $context);
            if ($chatReport) {
                $this->linkExistingReferral($alert, $session);
            }
            $this->logEmergencyEscalation($alert);

            $this->broadcastSafely(new EmergencyEscalationInitiated($alert, $seeker, $session));

            return $alert->refresh();
        }, 3);
    }

    private function createEmergencyAlert(Session $session, HelpSeeker $seeker, array $context): EmergencyAlert
    {
        return EmergencyAlert::create([
            'seeker_id' => $seeker->id,
            'session_id' => $session->id,
            'alert_type' => $context['type'] ?? 'safety_threat',
            // Operational alert priority is separate from the original screening classification.
            'risk_level' => 'emergency',
            'triggered_by' => Auth::id(),
            'triggered_at' => now(),
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
            'status' => 'unread',
            'type_icon' => 'fa-life-ring',
            'link' => '/emergency',
        ]);
    }

    private function sendEmergencyNotifications(HelpSeeker $seeker, Session $session, EmergencyAlert $alert, bool $directLink = false): void
    {
        app(EmergencyStaffNotifications::class)->deliver($alert, $directLink);
        $alert->refresh();
        SupportAudit::record('emergency_notification_attempted', $alert, ['channel' => 'in_app', 'result' => $alert->notification_result]);
    }

    private function flagEmergencyCase(Session $session, HelpSeeker $seeker, array $context): void
    {
        if ($context['preserve_classification'] ?? false) {
            $session->update(['escalation_required' => true, 'requires_immediate_action' => true, 'requires_adviser_review' => true, 'emergency_triggered_at' => now()]);
            SupportAudit::record('helper_emergency_flagged', $session);

            return;
        }
        $session->forceFill([
            'session_status' => $session->helper_id ? $session->session_status : 'emergency',
            'workflow_state' => $session->helper_id ? $session->workflow_state : 'emergency_escalated',
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

        // Temporary peer support is separate from the emergency review.
        if (! $session->helper_id) {
            $queue = $session->queue ?: QueueRequest::create([
                'seeker_id' => $seeker->id, 'request_status' => 'waiting',
                'priority_level' => 'emergency', 'preferred_session_type' => 'chat',
                'request_date' => now(), 'queued_at' => now(),
            ]);
            $session->update(['queue_request_id' => $queue->id, 'submitted_at' => $session->submitted_at ?? now(),
                'session_status' => Session::STATUS_WAITING]);
            DB::afterCommit(fn () => app(HelperMatchingService::class)->processQueueRequest($queue));
        }
        SupportAudit::record('emergency_branch_activated', $session);

        $incident = IncidentReport::lockForUpdate()
            ->where('session_id', $session->id)
            ->where('incident_category', 'classification_emergency')
            ->whereIn('status', ['open', 'under_review', 'escalated'])
            ->oldest('id')
            ->first();

        if (! $incident) {
            IncidentReport::create([
                'session_id' => $session->id,
                'user_account_id' => $seeker->user_account_id,
                'incident_category' => 'classification_emergency',
                'description' => $context['reason'] ?? 'Emergency risk classification triggered immediate escalation.',
                'immediate_action' => 'Emergency review requested; human support is not yet confirmed.',
                'risk_level' => Referral::PRIORITY_EMERGENCY,
                'status' => 'open',
                'reported_at' => now(),
                'is_confidential' => true,
            ]);
            SupportAudit::record('classification_emergency_incident_created', $session, ['incident_category' => 'classification_emergency']);
        }
        // Escalation flags the case; only a separately authorized responder may open identity.
    }

    private function logEmergencyEscalation(EmergencyAlert $alert): void
    {
        AuditLog::create([
            'user_account_id' => Auth::id(),
            'action' => 'emergency_escalation',
            'module' => 'escalation',
            'description' => 'Emergency alert #'.$alert->id.' created for session #'.$alert->session_id,
        ]);
    }

    private function linkExistingReferral(EmergencyAlert $alert, Session $session): void
    {
        $referral = $session->referrals()->whereNotIn('status', ['closed', 'completed', 'cancelled', 'declined'])->latest('id')->first();
        if (! $referral) {
            return;
        }
        $alert->update(['referral_id' => $referral->id]);
        if ($referral->approved_at && $referral->help_seeker_consent && $referral->professional_notified_at && $referral->professional?->user?->is_active) {
            $alert->update(['professional_referred' => true, 'professional_referred_at' => now()]);
            $this->notifyUser($referral->professional->user_account_id, 'Emergency reported on your referral', 'A new emergency report requires attention on your assigned referral.', route('professional.referral.show', $referral->id, false), $alert->id);
        }
    }

    private function notifyUser(?int $userId, string $title, string $message, string $link, ?int $emergencyAlertId = null): void
    {
        if (! $userId) {
            return;
        }

        Notification::create([
            'emergency_alert_id' => $emergencyAlertId,
            'user_account_id' => $userId,
            'title' => $title,
            'message' => $message,
            'notification_type' => 'emergency',
            'status' => 'unread',
            'type_icon' => 'fa-life-ring',
            'link' => $link,
        ]);
    }
}
