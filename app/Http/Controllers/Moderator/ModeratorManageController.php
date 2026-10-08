<?php

namespace App\Http\Controllers\Moderator;

use App\Http\Controllers\Controller;
use App\Models\Adviser;
use App\Models\Helper;
use App\Models\Referral;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ModeratorManageController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['search'=>'nullable|string|max:100','adviser'=>['nullable','regex:/^(unassigned|[1-9][0-9]*)$/'],'workspace'=>'nullable|integer']);
        $search = trim($request->get('search', ''));
        $adviserFilter = $request->get('adviser');

        $helpers = Helper::with(['adviser', 'latestCompetency'])
            ->withCount([
                'sessions as active_cases' => fn ($q) => $q->whereIn('session_status', ['active', 'helper_assigned']),
                'sessions as total_cases',
            ])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $term = '%'.mb_strtolower($search).'%';
                    $q->whereRaw('LOWER(first_name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$term]);
                    if (preg_match('/^(?:H-)?(\d+)$/i', $search, $match)) $q->orWhere('id',(int)$match[1]);
                });
            })
            ->when($adviserFilter && $adviserFilter !== 'unassigned', fn ($query) => $query->where('adviser_id', $adviserFilter))
            ->when($adviserFilter === 'unassigned', fn ($query) => $query->whereNull('adviser_id'))
            ->orderBy('first_name')
            ->paginate(15)->withQueryString()
            ->through(function (Helper $helper) {
                $helper->score = (float) ($helper->latestCompetency?->overall_score ?? 0);
                // Stored status alone can lag behind readiness expiry, so the
                // pill reflects the same eligibility rules matching enforces.
                $helper->eligibility = app(\App\Services\HelperEligibilityService::class)->status($helper);

                return $helper;
            });

        $advisers = Adviser::withCount(['competencyEvaluations'])
            ->withCount(['helpers as assigned_helpers'])
            ->get()
            ->map(function (Adviser $adviser) {
                $adviser->capacity = Helper::MAX_HELPERS_PER_ADVISER;
                $adviser->remaining_slots = Helper::getRemainingSlotsForAdviser($adviser->id);

                return $adviser;
            });

        $workspaceHelpers = Helper::with('adviser','latestCompetency')->where('adviser_id',$request->integer('workspace') ?: $advisers->first()?->id)->orderBy('first_name')->paginate(15,['*'],'workspace_page')->withQueryString();
        $poolHelpers = Helper::whereNull('adviser_id')->orderBy('first_name')->paginate(15,['*'],'pool_page')->withQueryString();
        $manageStats = $this->stats()->getData(true);
        $selectedAdviser = $request->get('workspace');
        $workspaceAdviser = $selectedAdviser
            ? $advisers->firstWhere('id', (int) $selectedAdviser)
            : $advisers->first();

        return view('moderator.manage', compact('helpers', 'advisers', 'search', 'adviserFilter', 'selectedAdviser', 'workspaceAdviser', 'workspaceHelpers','poolHelpers','manageStats'));
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
        abort_if($helper->adviser_id && $helper->adviser_id !== $adviser->id,403,'An existing supervision relationship must be transferred through the Adviser reassignment workflow.');
        abort_unless($adviser->user?->is_active && $adviser->user?->role === 'adviser',422);
        $helper->assignmentReason = 'Initial supervision assignment by Moderator';
        $helper->update(['adviser_id' => $adviser->id]);

        // Supervision affects eligibility: reconcile availability so a willing
        // helper already on shift becomes matchable right away.
        $helper = $helper->fresh();
        app(\App\Services\HelperWorkflowMaintenance::class)->reconcileHelperAvailability($helper);
        app(\App\Services\HelperMatchingService::class)->matchWaitingRequests();

        return back()->with('success', $helper->full_name . ' assigned to ' . $adviser->full_name . '.');
    }

    public function unassignFromAdviser(Request $request): RedirectResponse
    {
        $request->validate([
            'helper_id' => 'required|exists:helpers,id',
        ]);

        $helper = Helper::findOrFail($request->helper_id);

        if (! $helper->adviser_id) {
            return back()->with('success', $helper->full_name . ' moved back to the unassigned pool.');
        }

        $openReferrals = Referral::where('helper_id', $helper->id)
            ->whereIn('status', [
                Referral::STATUS_PENDING_ADVISER,
                Referral::STATUS_CONSENT_REQUESTED,
                Referral::STATUS_PENDING_CONSENT,
                Referral::STATUS_PENDING_PROFESSIONAL,
                Referral::STATUS_ACCEPTED,
                Referral::STATUS_IN_PROGRESS,
            ])->exists();

        $openCases = $helper->sessions()
            ->whereIn('session_status', ['active', 'helper_assigned'])
            ->exists();

        if ($openReferrals || $openCases) {
            return back()->with('error', 'Cannot unassign '.$helper->full_name.': this helper has '.($openReferrals ? 'an open referral' : 'an active or assigned session').'. Complete the case or transfer supervision through the Adviser transfer workflow first. The current Adviser assignment has been kept.');
        }

        $helper->assignmentReason = 'Supervision ended by Moderator; helper returned to the unassigned pool.';
        $helper->update(['adviser_id' => null]);
        app(\App\Services\HelperWorkflowMaintenance::class)->reconcileHelperAvailability($helper->fresh());

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
