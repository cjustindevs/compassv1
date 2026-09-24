<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Session;
use App\Models\SessionReport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HelperDocumentationService
{
    public function save(User $actor, Session $session, array $data, string $mode = 'summary'): SessionReport
    {
        abort_unless($actor->is_active && $actor->role === 'helper' && $actor->helper?->id === $session->helper_id, 403);

        return DB::transaction(function () use ($actor, $session, $data, $mode) {
            $session = Session::lockForUpdate()->findOrFail($session->id);
            abort_unless($session->helper_id === $actor->helper->id && $session->start_time, 409, 'Only a session you have conducted can be documented.');
            $report = SessionReport::firstOrNew(['session_id' => $session->id]);

            $documentationFields = ['help_seeker_condition', 'session_summary', 'observations', 'actions_taken', 'session_result', 'follow_up_plan', 'risk_level_assessed'];
            $reflectionFields = ['personal_reflection', 'skills_applied'];
            $fields = match ($mode) {
                'reflection' => $reflectionFields,
                'both' => array_merge($documentationFields, $reflectionFields),
                default => $documentationFields,
            };
            $changes = array_intersect_key($data, array_flip($fields));
            $report->fill($changes);
            if ($report->exists && ! $report->isDirty()) {
                return $report;
            }

            $hasReflection = ! empty($data['skills_applied']) || trim((string) ($data['personal_reflection'] ?? '')) !== '';
            $summaryPending = in_array($mode, ['summary', 'both'], true);
            $reflectionPending = $mode === 'reflection' || ($mode === 'both' && $hasReflection);

            $previouslySubmitted = ($summaryPending && ($report->getOriginal('summary_submitted_at') || $report->getOriginal('session_summary')))
                || ($reflectionPending && ($report->getOriginal('reflection_submitted_at') || $report->getOriginal('personal_reflection')));
            if ($previouslySubmitted) {
                if (empty($data['correction_reason'])) {
                    throw ValidationException::withMessages(['correction_reason' => 'Explain why you are correcting this submission.']);
                }
                DB::table('session_report_revisions')->insert(['report_id' => $report->id, 'actor_id' => $actor->id, 'snapshot' => json_encode($report->getOriginal()), 'reason' => $data['correction_reason'], 'created_at' => now()]);
            }

            $timestamps = [];
            if ($summaryPending) {
                $timestamps['summary_submitted_at'] = now();
            }
            if ($reflectionPending) {
                $timestamps['reflection_submitted_at'] = now();
            }
            $report->fill($timestamps + ['documented_at' => now(), 'documentation_late' => $session->end_time?->lt(now()->subHours(24)) ?? false, 'adviser_reviewed' => false, 'reviewed_date' => null]);
            if ($mode !== 'reflection') {
                $report->referral_recommended = ($data['session_result'] === 'needs_referral');
                if (! empty($data['risk_level_assessed']) && $data['risk_level_assessed'] !== $session->risk_level) {
                    $report->reassessment_requested_at = now();
                    $report->reassessment_reviewed_at = null;
                    $session->update(['requires_adviser_review' => true, 'review_adviser_id' => $session->helper?->adviser_id]);
                    SupportAudit::record('helper_risk_reassessment_requested', $session);
                }
            }
            $report->save();
            $session->update(['documentation_status' => $report->summary_submitted_at && $report->reflection_submitted_at ? 'submitted' : 'incomplete']);
            SupportAudit::record($previouslySubmitted ? 'documentation_corrected' : ($mode === 'reflection' ? 'reflection_submitted' : 'summary_submitted'), $report);
            if ($adviserUser = $session->helper?->adviser?->user_account_id) {
                Notification::create(['user_account_id' => $adviserUser, 'title' => 'Session documentation needs review', 'message' => 'Session #'.$session->id.' has a new documentation submission.', 'notification_type' => 'evaluation', 'type_icon' => 'fa-file-lines', 'link' => '/adviser/evaluations']);
            }

            return $report;
        }, 3);
    }
}
