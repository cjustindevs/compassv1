<?php

namespace App\Services;

use App\Events\IncidentReported;
use App\Events\IncidentResolved;
use App\Models\Adviser;
use App\Models\IncidentReport;
use App\Models\Moderator;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class IncidentReportService
{
    public const CATEGORIES = [
        'emergency_risk_disclosure',
        'suicidal_ideation',
        'threat_to_others',
        'abuse_disclosure',
        'breach_of_confidentiality',
        'inappropriate_user_behavior',
        'harassment',
        'technical_issue',
        'unauthorized_access',
        'helper_ethical_concern',
        'referral_process_concern',
        'emergency_flag',
    ];

    public function createIncidentReport(array $data): IncidentReport
    {
        $this->validateIncidentData($data);

        $incident = IncidentReport::create([
            'session_id' => $data['session_id'] ?? null,
            'user_account_id' => $data['user_account_id'] ?? Auth::id(),
            'moderator_id' => $data['moderator_id'] ?? null,
            'incident_category' => $data['category'] ?? $data['incident_category'],
            'description' => $data['description'],
            'immediate_action' => $data['immediate_action'] ?? null,
            'risk_level' => $data['risk_level'] ?? 'moderate',
            'recommendation' => $data['recommendation'] ?? null,
            'comments' => $data['comments'] ?? null,
            'status' => 'open',
            'reported_at' => now(),
            'is_confidential' => $data['is_confidential'] ?? true,
        ]);

        $this->notifyIncident($incident);
        event(new IncidentReported($incident));

        return $incident;
    }

    public function reviewIncident(IncidentReport $incident, array $data): IncidentReport
    {
        $reviewerId = $data['reviewer_id'] ?? Auth::id();
        $this->validateReviewerAuthorization($reviewerId);

        $incident->forceFill([
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'review_comments' => $data['comments'] ?? null,
            'status' => 'under_review',
        ])->save();

        return $incident->refresh();
    }

    public function escalateIncident(IncidentReport $incident, array $data): IncidentReport
    {
        $incident->forceFill([
            'status' => 'escalated',
            'escalated_at' => now(),
            'escalated_to' => $data['escalated_to'],
            'escalation_reason' => $data['reason'] ?? null,
        ])->save();

        $this->notifyUser($incident->escalated_to, 'Incident escalated', 'Incident #' . $incident->id . ' was escalated to you.', '/moderator/emergency', 'incident');

        return $incident->refresh();
    }

    public function resolveIncident(IncidentReport $incident, array $data): IncidentReport
    {
        $incident->forceFill([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => Auth::id(),
            'resolution_summary' => $data['resolution_summary'] ?? null,
            'corrective_actions' => $data['corrective_actions'] ?? null,
        ])->save();

        $this->notifyResolution($incident);
        event(new IncidentResolved($incident));

        return $incident->refresh();
    }

    public function closeIncident(IncidentReport $incident, array $data): IncidentReport
    {
        $incident->forceFill([
            'status' => 'closed',
            'closed_at' => now(),
            'closed_by' => Auth::id(),
            'closure_notes' => $data['closure_notes'] ?? null,
        ])->save();

        return $incident->refresh();
    }

    private function validateIncidentData(array $data): void
    {
        $category = $data['category'] ?? $data['incident_category'] ?? null;

        if (! $category || empty($data['description'])) {
            throw new \InvalidArgumentException('Incident category and description are required.');
        }

        if (! in_array($category, self::CATEGORIES, true)) {
            throw new \InvalidArgumentException('Invalid incident category.');
        }
    }

    private function validateReviewerAuthorization(?int $reviewerId): void
    {
        $reviewer = User::find($reviewerId);
        if (! $reviewer || ! in_array($reviewer->role, ['moderator', 'adviser', 'admin'], true)) {
            throw new \RuntimeException('User is not authorized to review incidents.');
        }
    }

    private function notifyIncident(IncidentReport $incident): void
    {
        foreach (Moderator::pluck('user_account_id') as $userId) {
            $this->notifyUser($userId, 'Incident reported', 'Incident #' . $incident->id . ' requires review.', '/moderator/emergency', 'incident');
        }

        if (in_array($incident->risk_level, ['high', 'emergency'], true)) {
            foreach (Adviser::pluck('user_account_id') as $userId) {
                $this->notifyUser($userId, 'High-risk incident reported', 'Incident #' . $incident->id . ' requires adviser review.', '/adviser/dashboard', 'incident');
            }
        }
    }

    private function notifyResolution(IncidentReport $incident): void
    {
        $this->notifyUser($incident->user_account_id, 'Incident resolved', 'Incident #' . $incident->id . ' has been resolved.', '/notifications', 'incident');

        if ($incident->reviewed_by) {
            $this->notifyUser($incident->reviewed_by, 'Incident resolved', 'Incident #' . $incident->id . ' has been resolved.', '/moderator/emergency', 'incident');
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
            'type_icon' => 'IR',
            'link' => $link,
        ]);
    }
}
