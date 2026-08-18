<?php

namespace App\Http\Controllers\Helper;

use App\Http\Controllers\Controller;
use App\Models\Helper;
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
            ->get();

        $trend = $history->sortBy('evaluation_date')
            ->map(function (HelperCompetencyHistory $record) {
                return [
                    'label' => $record->evaluation_date?->format('Y-m'),
                    'score' => max(8, min(100, (int) round($record->overall_score))),
                ];
            })
            ->take(6)
            ->values();

        return view('helper.competency', [
            'latest' => $latest,
            'totalEvaluations' => $history->count(),
            'trend' => $trend,
            'history' => $history->map(function (HelperCompetencyHistory $record) {
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
}