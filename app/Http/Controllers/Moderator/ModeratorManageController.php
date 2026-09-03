<?php

namespace App\Http\Controllers\Moderator;

use App\Http\Controllers\Controller;
use App\Models\Adviser;
use App\Models\Helper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ModeratorManageController extends Controller
{
    public function index(Request $request)
    {
        $search = trim($request->get('search', ''));
        $adviserFilter = $request->get('adviser');

        $helpers = Helper::with(['adviser', 'latestCompetency', 'sessions'])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'ilike', "%{$search}%")
                        ->orWhere('last_name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                });
            })
            ->when($adviserFilter && $adviserFilter !== 'unassigned', fn ($query) => $query->where('adviser_id', $adviserFilter))
            ->when($adviserFilter === 'unassigned', fn ($query) => $query->whereNull('adviser_id'))
            ->orderBy('first_name')
            ->get()
            ->map(function (Helper $helper) {
                $helper->active_cases = $helper->sessions()
                    ->whereIn('session_status', ['active', 'helper_assigned'])
                    ->count();
                $helper->total_cases = $helper->sessions()->count();
                $helper->score = (float) ($helper->latestCompetency?->overall_score ?? 0);

                return $helper;
            });

        $advisers = Adviser::with('competencyEvaluations')
            ->withCount(['competencyEvaluations'])
            ->get()
            ->map(function (Adviser $adviser) {
                $adviser->assigned_helpers = Helper::where('adviser_id', $adviser->id)->count();
                $adviser->capacity = Helper::MAX_HELPERS_PER_ADVISER;
                $adviser->remaining_slots = Helper::getRemainingSlotsForAdviser($adviser->id);

                return $adviser;
            });

        $selectedAdviser = $request->get('workspace');
        $workspaceAdviser = $selectedAdviser
            ? $advisers->firstWhere('id', (int) $selectedAdviser)
            : $advisers->first();

        return view('moderator.manage', compact('helpers', 'advisers', 'search', 'adviserFilter', 'selectedAdviser', 'workspaceAdviser'));
    }

    public function assignToAdviser(Request $request): RedirectResponse
    {
        $request->validate([
            'helper_id' => 'required|exists:helpers,id',
            'adviser_id' => 'required|exists:advisers,id',
        ]);

        $adviser = Adviser::findOrFail($request->adviser_id);
        if (! Helper::canAddHelperToAdviser($adviser->id)) {
            return back()->with('error', $adviser->full_name . ' is at full capacity (' . Helper::MAX_HELPERS_PER_ADVISER . ' helpers).');
        }

        $helper = Helper::findOrFail($request->helper_id);
        $helper->update(['adviser_id' => $adviser->id]);

        return back()->with('success', $helper->full_name . ' assigned to ' . $adviser->full_name . '.');
    }

    public function unassignFromAdviser(Request $request): RedirectResponse
    {
        $request->validate([
            'helper_id' => 'required|exists:helpers,id',
        ]);

        $helper = Helper::findOrFail($request->helper_id);
        $helper->update(['adviser_id' => null]);

        return back()->with('success', $helper->full_name . ' moved back to the unassigned pool.');
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'total_helpers' => Helper::count(),
            'unassigned_helpers' => Helper::whereNull('adviser_id')->count(),
            'total_advisers' => Adviser::count(),
            'slots_taken' => Helper::whereNotNull('adviser_id')->count(),
            'slots_total' => Helper::MAX_HELPERS_PER_ADVISER * Adviser::count(),
        ]);
    }
}
