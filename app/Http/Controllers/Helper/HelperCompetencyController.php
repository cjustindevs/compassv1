<?php

namespace App\Http\Controllers\Helper;

use App\Http\Controllers\Controller;
use App\Models\HelperCompetencyHistory;
use Illuminate\Support\Facades\Auth;

class HelperCompetencyController extends Controller
{
    /**
     * Show competency evaluations pulled from helper_competency_history.
     */
    public function index()
    {
        $helper = Auth::user()->helper;

        $latest = HelperCompetencyHistory::with('adviser')
            ->where('helper_id', $helper->id)
            ->latest('evaluation_date')
            ->first();

        $history = HelperCompetencyHistory::with('adviser')
            ->where('helper_id', $helper->id)
            ->orderByDesc('evaluation_date')
            ->get()
            ->map(fn (HelperCompetencyHistory $record) => [
                'date' => $record->evaluation_date?->format('M d, Y'),
                'overall' => (float) $record->overall_score,
                'level' => $record->level_label,
                'period' => $record->evaluation_period,
                'adviser' => $record->adviser ? $record->adviser->full_name : 'Adviser',
                'remarks' => $record->remarks,
                'breakdown' => $record->score_breakdown,
            ]);

        $trend = $history->map(fn ($h) => [
            'label' => $h['date'],
            'score' => $h['overall'],
        ])->reverse()->values();

        return view('helper.competency', [
            'latest' => $latest,
            'history' => $history,
            'trend' => $trend,
            'totalEvaluations' => $history->count(),
        ]);
    }
}