<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Models\SessionReport;
use App\Models\HelperCompetencyHistory;
use App\Models\Session;
use App\Models\AdviserFeedback;
use App\Models\Notification;
use App\Events\EvaluationCompleted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdviserEvaluationController extends Controller
{
    /**
     * Show all pending evaluations
     */
    public function index()
    {
        // Get all session reports that haven't been reviewed
        $pendingReports = SessionReport::with(['session', 'session.seeker', 'session.helper', 'session.concern'])
            ->where('adviser_reviewed', false)
            ->orderBy('created_at', 'asc')
            ->get();

        // Get completed evaluations for reference
        $completedReports = SessionReport::with(['session', 'session.seeker', 'session.helper'])
            ->where('adviser_reviewed', true)
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get();

        // Get statistics
        $totalPending = $pendingReports->count();
        $highRiskPending = $pendingReports->filter(function ($report) {
            return in_array($report->session->risk_level ?? '', ['high', 'emergency']);
        })->count();

        // Get adviser feedback count
        $feedbackCount = AdviserFeedback::where('adviser_id', Auth::user()->adviser->id ?? 0)->count();

        return view('adviser.evaluations', compact(
            'pendingReports',
            'completedReports',
            'totalPending',
            'highRiskPending',
            'feedbackCount'
        ));
    }

    /**
     * Show the evaluation form for a specific session report
     */
    public function show($id)
    {
        $report = SessionReport::with(['session', 'session.seeker', 'session.helper', 'session.concern'])
            ->findOrFail($id);

        // Check if already evaluated
        $existingFeedback = AdviserFeedback::where('report_id', $report->id)
            ->where('adviser_id', Auth::user()->adviser->id)
            ->first();

        return view('adviser.evaluate', compact('report', 'existingFeedback'));
    }

    /**
     * Process the competency evaluation
     */
    public function store(Request $request, $id)
    {
        $report = SessionReport::findOrFail($id);
        $adviser = Auth::user()->adviser;

        $validated = $request->validate([
            'active_listening' => 'required|integer|min:1|max:5',
            'empathy' => 'required|integer|min:1|max:5',
            'respect_professionalism' => 'required|integer|min:1|max:5',
            'ethical_practices' => 'required|integer|min:1|max:5',
            'referral_accuracy' => 'required|integer|min:1|max:5',
            'strengths' => 'nullable|string|max:1000',
            'improvement_areas' => 'nullable|string|max:1000',
            'recommendations' => 'nullable|string|max:1000',
            'recommended_action' => 'required|string',
        ]);

        // Calculate overall score
        $overallScore = (
            ($validated['active_listening'] * 0.25) +
            ($validated['empathy'] * 0.25) +
            ($validated['respect_professionalism'] * 0.20) +
            ($validated['ethical_practices'] * 0.20) +
            ($validated['referral_accuracy'] * 0.10)
        );

        // Determine competency level
        $level = $this->getCompetencyLevel($overallScore);

        // Save competency history
        $competency = HelperCompetencyHistory::create([
            'helper_id' => $report->session->helper_id,
            'adviser_id' => $adviser->id,
            'evaluation_date' => now(),
            'active_listening_score' => $validated['active_listening'],
            'empathy_score' => $validated['empathy'],
            'respect_score' => $validated['respect_professionalism'],
            'ethical_practices_score' => $validated['ethical_practices'],
            'referral_accuracy_score' => $validated['referral_accuracy'],
            'overall_score' => round($overallScore, 2),
            'competency_level' => $level,
            'evaluation_period' => now()->format('F Y'),
            'remarks' => $validated['recommendations'] ?? null
        ]);

        // Save adviser feedback
        $feedback = AdviserFeedback::create([
            'report_id' => $report->id,
            'adviser_id' => $adviser->id,
            'status' => 'completed',
            'feedback_text' => $validated['recommendations'] ?? null,
            'strengths' => $validated['strengths'] ?? null,
            'improvement_areas' => $validated['improvement_areas'] ?? null,
            'competency_rating' => round($overallScore, 2),
            'competency_level' => $level,
            'training_recommendation' => $validated['recommendations'] ?? null,
            'follow_up_action' => $validated['recommended_action'],
            'created_date' => now()
        ]);

        // Mark report as reviewed
        $report->update([
            'adviser_reviewed' => true,
            'reviewed_date' => now()
        ]);

        // Create notification for helper
        if ($report->session->helper?->user_account_id) {
            $helperUserId = $report->session->helper->user_account_id;

            Notification::create([
                'user_account_id' => $helperUserId,
                'title' => 'Competency Evaluation Completed',
                'message' => 'Your competency evaluation has been reviewed by your adviser.',
                'notification_type' => 'evaluation',
                'type_icon' => '📊',
                'link' => '/helper/competency',
            ]);

            // Update the helper's competency level from the evaluation
            $report->session->helper->update([
                'competency_level' => max(1, min(5, ceil($overallScore))),
            ]);

            try {
                broadcast(new EvaluationCompleted($competency, $helperUserId));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // Mark the session evaluated so the workflow reflects a completed review
        $report->session->update(['session_status' => Session::STATUS_EVALUATED]);

        return redirect()->route('adviser.evaluations')
            ->with('success', 'Competency evaluation submitted successfully!');
    }

    /**
     * Get competency level based on score
     */
    private function getCompetencyLevel($score)
    {
        if ($score >= 4.5) return 'Outstanding';
        if ($score >= 3.5) return 'Very Good';
        if ($score >= 2.5) return 'Satisfactory';
        if ($score >= 1.5) return 'Needs Improvement';
        return 'Unsatisfactory';
    }

    /**
     * Skip evaluation (mark as reviewed without feedback)
     */
    public function skip($id)
    {
        $report = SessionReport::findOrFail($id);

        $report->update([
            'adviser_reviewed' => true,
            'reviewed_date' => now()
        ]);

        return redirect()->route('adviser.evaluations')
            ->with('info', 'Evaluation marked as reviewed.');
    }
}