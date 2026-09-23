<?php

namespace App\Http\Controllers\Adviser;

use App\Events\EvaluationCompleted;
use App\Http\Controllers\Controller;
use App\Models\AdviserFeedback;
use App\Models\Helper;
use App\Models\HelperCompetencyHistory;
use App\Models\Notification;
use App\Models\Session;
use App\Models\SessionReport;
use App\Services\SupportAudit;
use App\Traits\BroadcastsSafely;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdviserEvaluationController extends Controller
{
    use BroadcastsSafely;

    /**
     * Show all pending evaluations
     */
    public function index()
    {
        $helperIds = Helper::where('adviser_id', Auth::user()->adviser?->id)->pluck('id');

        // Get all session reports that haven't been reviewed
        $pendingReports = SessionReport::with(['session', 'session.seeker', 'session.helper', 'session.concern'])
            ->where('adviser_reviewed', false)
            ->whereHas('session', fn ($query) => $query->whereIn('helper_id', $helperIds))
            ->orderBy('created_at', 'asc')
            ->paginate(15, ['*'], 'pending_page')->withQueryString();

        // Get completed evaluations for reference
        $completedReports = SessionReport::with(['session', 'session.seeker', 'session.helper'])
            ->where('adviser_reviewed', true)
            ->whereHas('session', fn ($query) => $query->whereIn('helper_id', $helperIds))
            ->orderBy('updated_at', 'desc')
            ->paginate(15, ['*'], 'completed_page')->withQueryString();

        // Get statistics
        $totalPending = $pendingReports->total();
        $highRiskPending = SessionReport::where('adviser_reviewed', false)
            ->whereHas('session', function ($query) use ($helperIds) {
                $query->whereIn('helper_id', $helperIds)
                    ->whereIn('risk_level', ['high', 'emergency']);
            })
            ->count();

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

        $this->authorizeReport($report);

        // Check if already evaluated
        $existingFeedback = AdviserFeedback::where('report_id', $report->id)
            ->latest('id')->first();

        $messages = collect();
        if (app(\App\Services\AdviserTranscriptAccess::class)->allowed($report->session)) {
            $messages = $report->session->messages()->orderBy('sent_datetime')->orderBy('id')->limit(500)->get();
            SupportAudit::record('authorized_conversation_viewed', $report, ['purpose'=>'competency_assessment']);
        }
        SupportAudit::record('session_documentation_viewed', $report);

        return view('adviser.evaluate', compact('report', 'existingFeedback', 'messages'));
    }

    /**
     * Process the competency evaluation
     */
    public function store(Request $request, $id)
    {
        $report = SessionReport::findOrFail($id);
        $adviser = Auth::user()->adviser;

        $this->authorizeReport($report);

        $validated = $request->validate([
            'active_listening' => 'required|integer|min:1|max:5',
            'empathy' => 'required|integer|min:1|max:5',
            'respect_professionalism' => 'required|integer|min:1|max:5',
            'ethical_practices' => 'required|integer|min:1|max:5',
            'referral_accuracy' => 'required|integer|min:1|max:5',
            'strengths' => 'nullable|string|max:1000',
            'improvement_areas' => 'nullable|string|max:1000',
            'recommendations' => 'nullable|string|max:1000',
            'recommended_action' => 'required|string|max:255',
            'correction_reason' => 'nullable|string|min:10|max:1000',
            'follow_up_date' => 'nullable|date',
        ]);

        app(\App\Services\AdviserEvaluationService::class)->save($report, $validated);

        return redirect()->route('adviser.evaluations')
            ->with('success', 'Competency evaluation submitted successfully!');
    }

    /**
     * Get competency level based on score
     */
    private function getCompetencyLevel($score)
    {
        if ($score >= 4.5) {
            return 'Outstanding';
        }
        if ($score >= 3.5) {
            return 'Very Good';
        }
        if ($score >= 2.5) {
            return 'Satisfactory';
        }
        if ($score >= 1.5) {
            return 'Needs Improvement';
        }

        return 'Unsatisfactory';
    }

    /**
     * Skip evaluation (mark as reviewed without feedback)
     */
    public function skip($id)
    {
        $report = SessionReport::findOrFail($id);

        $this->authorizeReport($report);

        abort_unless(in_array($report->session->session_status, ['completed', 'evaluated'], true), 409);
        $report->update(['adviser_reviewed' => true, 'reviewed_date' => now()]);
        SupportAudit::record('documentation_reviewed_without_new_score', $report);

        return redirect()->route('adviser.evaluations')
            ->with('info', 'Evaluation marked as reviewed.');
    }

    private function authorizeReport(SessionReport $report): void
    {
        abort_unless(Auth::user()?->role === 'adviser' && Auth::user()?->is_active, 403);
        $adviserId = app(\App\Services\AdviserScope::class)->actor()->id;
        $helperId = $report->session?->helper_id;

        abort_unless(
            $helperId && Helper::where('id', $helperId)->where('adviser_id', $adviserId)->exists(),
            403,
            'You are not authorized to review this session report.'
        );
    }
}
