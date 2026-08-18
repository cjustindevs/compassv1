<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Models\Adviser;
use App\Models\Helper;
use App\Models\HelperCompetencyHistory;
use App\Models\Session;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdviserHelperController extends Controller
{
    /**
     * Show helper management page with status filtering.
     */
    public function index(Request $request): View
    {
        $statusFilter = $request->get('status', 'all');

        $helpers = Helper::with(['user', 'latestReadiness', 'adviser'])
            ->get();

        $helperData = $helpers->map(function (Helper $helper) {
            $activeSessions = Session::where('helper_id', $helper->id)
                ->where('session_status', 'active')
                ->count();

            $competency = HelperCompetencyHistory::where('helper_id', $helper->id)
                ->latest('evaluation_date')
                ->first();

            $averageScore = HelperCompetencyHistory::where('helper_id', $helper->id)
                ->avg('overall_score') ?? 0;

            $readiness = $helper->latestReadiness;

            return [
                'helper' => $helper,
                'initials' => $this->getInitials($helper->first_name, $helper->last_name),
                'level' => $this->getCompetencyLevel($helper->competency_level),
                'competency_score' => $competency ? round($competency->overall_score, 1) : 0,
                'average_rating' => round($averageScore, 1),
                'active_cases' => $activeSessions,
                'status' => $this->getHelperStatus($helper, $readiness),
                'is_available' => $helper->isOnline(),
                'readiness' => $readiness,
            ];
        });

        if ($statusFilter !== 'all') {
            $helperData = $helperData->where('status', $statusFilter);
        }

        $helperData = $helperData->sortByDesc('competency_score')->values();

        $totalHelpers = $helpers->count();
        $availableHelpers = $helpers->where('status', 'available')->count();
        $highCompetency = $helpers->filter(fn (Helper $helper) => $helper->competency_level >= 4)->count();

        // Available advisers for assignment
        $advisers = Adviser::orderBy('first_name')->get();

        return view('adviser.helpers', compact(
            'helperData',
            'totalHelpers',
            'availableHelpers',
            'highCompetency',
            'statusFilter',
            'advisers'
        ));
    }

    /**
     * Show helper details.
     */
    public function show(int $id): View
    {
        $helper = Helper::with([
            'user',
            'latestReadiness',
            'adviser',
            'sessions' => function ($query) {
                $query->where('session_status', 'active');
            },
        ])->findOrFail($id);

        $competencyHistory = HelperCompetencyHistory::where('helper_id', $helper->id)
            ->with('adviser')
            ->orderBy('evaluation_date', 'desc')
            ->limit(10)
            ->get();

        $advisers = Adviser::orderBy('first_name')->get();

        return view('adviser.helper-detail', compact('helper', 'competencyHistory', 'advisers'));
    }

    /**
     * Assign (or reassign) an adviser to a helper.
     */
    public function assign(Request $request, int $id): RedirectResponse
    {
        $helper = Helper::findOrFail($id);

        $validated = $request->validate([
            'adviser_id' => 'required|exists:advisers,id',
        ]);

        $helper->update(['adviser_id' => $validated['adviser_id']]);

        return back()->with('success', 'Adviser assigned to ' . $helper->getFullNameAttribute() . '.');
    }

    /**
     * Export helper data as CSV.
     */
    public function export(Request $request)
    {
        $statusFilter = $request->get('status', 'all');

        $helpers = Helper::with(['adviser'])
            ->get()
            ->map(function (Helper $helper) {
                $competency = HelperCompetencyHistory::where('helper_id', $helper->id)
                    ->latest('evaluation_date')
                    ->first();

                return [
                    'helper' => $helper,
                    'status' => $this->getHelperStatus($helper, $helper->latestReadiness),
                    'competency_score' => $competency ? round($competency->overall_score, 1) : 0,
                    'active_cases' => Session::where('helper_id', $helper->id)
                        ->where('session_status', 'active')
                        ->count(),
                    'total_sessions' => Session::where('helper_id', $helper->id)->count(),
                ];
            });

        if ($statusFilter !== 'all') {
            $helpers = $helpers->where('status', $statusFilter);
        }

        $filename = 'compass-helpers-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($helpers) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'Name',
                'Email',
                'Status',
                'Competency Level',
                'Latest Score',
                'Active Cases',
                'Total Sessions',
                'Assigned Adviser',
                'Joined',
            ]);

            foreach ($helpers as $row) {
                $helper = $row['helper'];
                fputcsv($file, [
                    $helper->getFullNameAttribute(),
                    $helper->email,
                    ucfirst($row['status']),
                    $helper->competency_level,
                    $row['competency_score'],
                    $row['active_cases'],
                    $row['total_sessions'],
                    $helper->adviser ? $helper->adviser->first_name . ' ' . $helper->adviser->last_name : 'Unassigned',
                    $helper->created_at?->format('Y-m-d') ?? '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get competency level based on the numeric competency_level column
     * (1 = trainee … 5 = expert).
     */
    private function getCompetencyLevel(?int $level): string
    {
        return match ($level) {
            5 => 'Expert',
            4 => 'Advanced',
            3 => 'Intermediate',
            2 => 'Beginner',
            default => 'Trainee',
        };
    }

    /**
     * Get helper status.
     */
    private function getHelperStatus(Helper $helper, $readiness): string
    {
        if ($helper->isOnline() && $readiness && $readiness->assessment_result === 'ready') {
            return 'online';
        }

        if ($readiness && $readiness->assessment_result === 'ready') {
            return 'available';
        }

        if ($readiness && $readiness->assessment_result === 'not_ready') {
            return 'offline';
        }

        return 'inactive';
    }

    /**
     * Get initials from first and last name.
     */
    private function getInitials(?string $firstName, ?string $lastName): string
    {
        return strtoupper(substr((string) $firstName, 0, 1) . substr((string) $lastName, 0, 1));
    }
}
