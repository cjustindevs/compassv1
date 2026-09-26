<?php

namespace App\Services;

use App\Models\AdviserFeedback;
use App\Models\HelperCompetencyHistory;
use App\Models\Notification;
use App\Models\SessionReport;
use Illuminate\Support\Facades\DB;

class AdviserEvaluationService
{
    /**
     * True when the most recent save() was an idempotent re-submission that
     * recorded nothing, so the caller can report that accurately instead of
     * claiming a fresh evaluation was saved.
     */
    public bool $lastSaveWasUnchanged = false;

    public function save(SessionReport $report, array $data): HelperCompetencyHistory
    {
        $this->lastSaveWasUnchanged = false;
        $adviser = app(AdviserScope::class)->actor();

        return DB::transaction(function () use ($report, $data, $adviser) {
            $report = SessionReport::lockForUpdate()->findOrFail($report->id);
            abort_unless($report->session?->helper?->adviser_id === $adviser->id, 403);
            abort_unless(in_array($report->session->session_status, ['completed', 'evaluated'], true), 409);
            abort_unless(trim((string) $report->session_summary) !== '' || trim((string) $report->personal_reflection) !== '', 422, 'Submitted documentation is required as evaluation evidence.');
            $evaluation = HelperCompetencyHistory::where('report_id', $report->id)->first();
            $feedback = AdviserFeedback::where('report_id', $report->id)->latest('id')->first();
            $correction = trim($data['correction_reason'] ?? '');
            // A repeat submission without a correction reason changes nothing.
            // That stays idempotent (no duplicate row, no version), but the
            // flag lets the controller avoid reporting a fresh evaluation.
            if ($evaluation && ! $correction) {
                $this->lastSaveWasUnchanged = true;

                return $evaluation;
            }
            if ($feedback && ! $evaluation && ! $correction) {
                abort(409, 'This legacy review already exists. Supply a correction reason to attach a versioned evaluation.');
            }
            $versions = app(SupervisionVersions::class);
            foreach ([$evaluation, $feedback] as $record) {
                if ($record && $versions->history($record)->isEmpty()) {
                    $versions->record($record, 'Original record before versioned correction');
                }
            }
            $score = CompetencyRubric::score($data);
            $level = CompetencyRubric::level($score);
            $evaluation ??= new HelperCompetencyHistory;
            $values = ['report_id' => $report->id, 'helper_id' => $report->session->helper_id, 'adviser_id' => $adviser->id, 'evaluation_date' => now(),
                'rubric_version' => CompetencyRubric::VERSION, 'evidence' => ['session_report_id' => $report->id, 'summary_submitted_at' => $report->summary_submitted_at, 'reflection_submitted_at' => $report->reflection_submitted_at, 'documentation_versions' => DB::table('session_report_revisions')->where('report_id', $report->id)->pluck('id')->all()],
                'overall_score' => $score, 'competency_level' => $level, 'evaluation_period' => now('Asia/Manila')->format('F Y'), 'remarks' => $data['recommendations'] ?? null];
            foreach (CompetencyRubric::CRITERIA as $key => $criterion) {
                $values[$criterion[2]] = $data[$key];
            }
            $evaluation->forceFill($values)->save();
            $versions->record($evaluation, $correction ?: 'Initial structured evaluation');
            $feedback ??= new AdviserFeedback;
            $feedback->forceFill(['report_id' => $report->id, 'adviser_id' => $adviser->id, 'status' => 'completed', 'feedback_text' => $data['recommendations'] ?? null,
                'strengths' => $data['strengths'] ?? null, 'improvement_areas' => $data['improvement_areas'] ?? null, 'competency_rating' => $score, 'competency_level' => $level,
                'training_recommendation' => $data['recommendations'] ?? null, 'follow_up_action' => $data['recommended_action'], 'follow_up_date' => $data['follow_up_date'] ?? null,
                'acknowledgment_required' => true, 'acknowledged_at' => null, 'created_date' => now()])->save();
            $versions->record($feedback, $correction ?: 'Initial Adviser feedback');
            $report->update(['adviser_reviewed' => true, 'reviewed_date' => now()]);
            $report->session->helper->updateCompetency($evaluation);
            $report->session->update(['session_status' => 'evaluated']);
            SupportAudit::record($correction ? 'competency_corrected' : 'competency_evaluated', $evaluation, ['rubric_version' => CompetencyRubric::VERSION]);
            SupportAudit::record($correction ? 'feedback_corrected' : 'feedback_submitted', $feedback);
            Notification::create(['user_account_id' => $report->session->helper->user_account_id, 'title' => 'Evaluation and feedback available', 'message' => 'Your Adviser has recorded an evaluation. Review the feedback and any correction history.', 'notification_type' => 'evaluation', 'link' => '/helper/competency']);

            return $evaluation;
        }, 3);
    }
}
