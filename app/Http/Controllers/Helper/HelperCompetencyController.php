<?php

namespace App\Http\Controllers\Helper;

use App\Http\Controllers\Controller;
use App\Models\AdviserFeedback;
use App\Models\Helper;
use App\Models\HelperCompetencyHistory;
use App\Models\HelpSeekerEvaluation;
use App\Services\SupportAudit;
use Illuminate\View\View;

class HelperCompetencyController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->role === 'helper' && auth()->user()?->is_active, 403);
        $helper = Helper::where('user_account_id', auth()->id())->first();

        if (! $helper) {
            return view('helper.competency', [
                'latest' => null,
                'totalEvaluations' => 0,
                'trend' => collect(),
                'history' => collect(),
            ]);
        }

        $latest = $helper->latestCompetency;

        $history = HelperCompetencyHistory::with('adviser')
            ->where('helper_id', $helper->id)
            ->orderBy('evaluation_date', 'desc')
            ->paginate(15);

        $trend = HelperCompetencyHistory::where('helper_id', $helper->id)
            ->orderBy('evaluation_date', 'desc')
            ->limit(6)
            ->get()->reverse()
            ->map(function (HelperCompetencyHistory $record) {
                return [
                    'label' => $record->evaluation_date?->format('Y-m'),
                    'score' => round($record->normalized_score * 20, 1),
                ];
            })
            ->values();

        return view('helper.competency', [
            'latest' => $latest,
            'completedSessions' => $helper->completedSessions()->count(),
            'totalEvaluations' => $history->total(),
            'trend' => $trend,
            'history' => $history->through(function (HelperCompetencyHistory $record) {
                return [
                    'date' => $record->evaluation_date?->format('M d, Y') ?: '—',
                    'overall' => $record->normalized_score,
                    'level' => $record->level_label,
                    'adviser' => $record->adviser?->full_name ?: 'Adviser',
                    'remarks' => $record->remarks,
                ];
            }),
        ]);
    }

    public function show(int $id): View
    {
        abort_unless(auth()->user()?->role === 'helper' && auth()->user()?->is_active, 403);
        $helper = Helper::where('user_account_id', auth()->id())->firstOrFail();

        $evaluation = HelperCompetencyHistory::with('adviser')
            ->where('helper_id', $helper->id)
            ->findOrFail($id);

        return view('helper.competency-detail', compact('evaluation'));
    }

    public function feedback(): View
    {
        abort_unless(auth()->user()?->role === 'helper' && auth()->user()?->is_active, 403);
        $helper = Helper::where('user_account_id', auth()->id())->firstOrFail();

        $adviserFeedback = AdviserFeedback::with(['adviser', 'report.session'])
            ->whereHas('report.session', fn ($query) => $query->where('helper_id', $helper->id))
            ->latest('created_date')
            ->paginate(15);

        $seekerFeedback = HelpSeekerEvaluation::with('session.seeker')
            ->whereHas('session', fn ($query) => $query->where('helper_id', $helper->id))
            ->latest()
            ->paginate(15);

        return view('helper.feedback', compact('helper', 'adviserFeedback', 'seekerFeedback'));
    }

    public function acknowledgeFeedback(int $id)
    {
        abort_unless(auth()->user()?->role === 'helper' && auth()->user()?->is_active, 403);
        $feedback = AdviserFeedback::whereHas('report.session', fn ($q) => $q->where('helper_id', auth()->user()->helper->id))->findOrFail($id);
        if (! $feedback->acknowledged_at) {
            $feedback->update(['acknowledged_at' => now()]);
            SupportAudit::record('training_feedback_acknowledged', $feedback);
        }

        return back()->with('success', 'Feedback acknowledged. Training completion remains subject to adviser verification.');
    }

    public function feedbackShow(int $id): View
    {
        abort_unless(auth()->user()?->role === 'helper' && auth()->user()?->is_active, 403);
        $helper = Helper::where('user_account_id', auth()->id())->firstOrFail();

        $feedback = AdviserFeedback::with(['adviser', 'report.session'])
            ->whereHas('report.session', fn ($query) => $query->where('helper_id', $helper->id))
            ->findOrFail($id);

        return view('helper.feedback-detail', compact('feedback'));
    }
}
