<?php

namespace Database\Seeders;

use App\Models\Adviser;
use App\Models\AdviserFeedback;
use App\Models\HelpSeekerEvaluation;
use App\Models\IncidentReport;
use App\Models\Session;
use App\Models\SessionReport;
use Illuminate\Database\Seeder;

/**
 * Seeds post-session data:
 *  - help_seeker_evaluations for completed/evaluated sessions
 *  - adviser_feedback for reports already reviewed by the adviser
 *  - incident_reports for flagged emergencies
 */
class EvaluationSeeder extends Seeder
{
    public function run(): void
    {
        $adviser = Adviser::first();

        // ── Seeker evaluations ────────────────────────────────────────────
        $evaluated = Session::where('session_status', 'evaluated')->first();
        if ($evaluated) {
            HelpSeekerEvaluation::firstOrCreate(
                ['session_id' => $evaluated->id],
                [
                    'helpfulness_score' => 5,
                    'comfort_score' => 5,
                    'feeling_after_score' => 4,
                    'understood_score' => 5,
                    'reuse_score' => 5,
                    'overall_score' => 5.0,
                    'comments' => 'Maya was so patient and really listened. I felt safe the whole time.',
                ]
            );
        }

        $completed = Session::where('session_status', 'completed')
            ->where('risk_level', 'low')
            ->first();
        if ($completed) {
            HelpSeekerEvaluation::firstOrCreate(
                ['session_id' => $completed->id],
                [
                    'helpfulness_score' => 4,
                    'comfort_score' => 4,
                    'feeling_after_score' => 4,
                    'understood_score' => 4,
                    'reuse_score' => 4,
                    'overall_score' => 4.0,
                    'comments' => 'Noel helped me find a routine that works. Grateful for the session.',
                ]
            );
        }

        // ── Adviser feedback for the already-reviewed report ──────────────
        $reviewedReport = SessionReport::where('adviser_reviewed', true)->first();

        if ($adviser && $reviewedReport) {
            AdviserFeedback::firstOrCreate(
                ['report_id' => $reviewedReport->id],
                [
                    'adviser_id' => $adviser->id,
                    'status' => 'completed',
                    'feedback_text' => 'Consistently demonstrates strong active listening and a calm, non-judgmental presence.',
                    'strengths' => 'Excellent use of validation; maintained the seeker\'s sense of safety throughout.',
                    'improvement_areas' => 'Introduce grounding techniques earlier in sessions where anxiety is present.',
                    'competency_rating' => 92,
                    'competency_level' => 'expert',
                    'training_recommendation' => 'Optional: advanced crisis intervention refresher.',
                    'follow_up_action' => 'continue',
                    'created_date' => now()->subWeek()->addDay(),
                ]
            );
        }

        // ── Incident reports (open emergencies for the adviser dashboard) ─
        $activeEmergency = Session::where('session_status', 'active')
            ->where('risk_level', 'emergency')
            ->latest('created_date')
            ->first();

        if ($activeEmergency && $activeEmergency->seeker?->user_account_id) {
            IncidentReport::firstOrCreate(
                ['session_id' => $activeEmergency->id, 'incident_category' => 'emergency_flag'],
                [
                    'user_account_id' => $activeEmergency->seeker->user_account_id,
                    'description' => 'Seeker reported escalating family conflict with safety concerns during the session.',
                    'immediate_action' => 'Helper escalated to adviser; seeker offered emergency hotline resources.',
                    'risk_level' => 'emergency',
                    'status' => 'open',
                ]
            );
        }

        $highCompleted = Session::where('session_status', 'completed')
            ->where('risk_level', 'high')
            ->latest('created_date')
            ->first();

        if ($highCompleted && $highCompleted->seeker?->user_account_id) {
            IncidentReport::firstOrCreate(
                ['session_id' => $highCompleted->id, 'incident_category' => 'high_risk_monitoring'],
                [
                    'user_account_id' => $highCompleted->seeker->user_account_id,
                    'description' => 'Prolonged grief reaction flagged for follow-up monitoring.',
                    'immediate_action' => 'Referred to professional counseling; follow-up session scheduled.',
                    'risk_level' => 'high',
                    'status' => 'under_review',
                ]
            );
        }

        $this->command->info('EvaluationSeeder: seeker evaluations, adviser feedback, and incident reports seeded.');
    }
}