<?php

namespace App\Http\Controllers\Helper;

use App\Http\Controllers\Controller;
use App\Models\Helper;
use App\Models\AdviserFeedback;
use App\Models\HelpSeekerEvaluation;
use App\Models\HelperCompetencyHistory;
use Illuminate\View\View;

class HelperCompetencyController extends Controller
{
    public function index(): View
    {
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
            ->orderBy('evaluation_date', 'asc')
            ->limit(6)
            ->get()
            ->map(function (HelperCompetencyHistory $record) {
                return [
                    'label' => $record->evaluation_date?->format('Y-m'),
                    'score' => max(8, min(100, (int) round($record->overall_score))),
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
                    'overall' => $record->overall_score,
                    'level' => $record->level_label,
                    'adviser' => $record->adviser?->full_name ?: 'Adviser',
                    'remarks' => $record->remarks,
                ];
            }),
        ]);
    }

    public function show(int $id): View
    {
        $helper = Helper::where('user_account_id', auth()->id())->firstOrFail();

        $evaluation = HelperCompetencyHistory::with('adviser')
            ->where('helper_id', $helper->id)
            ->findOrFail($id);

        return view('helper.competency-detail', compact('evaluation'));
    }

    public function feedback(): View
    {
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

    public function feedbackShow(int $id): View
    {
        $helper = Helper::where('user_account_id', auth()->id())->firstOrFail();

        $feedback = AdviserFeedback::with(['adviser', 'report.session'])
            ->whereHas('report.session', fn ($query) => $query->where('helper_id', $helper->id))
            ->findOrFail($id);

        return view('helper.feedback-detail', compact('feedback'));
    }
}
