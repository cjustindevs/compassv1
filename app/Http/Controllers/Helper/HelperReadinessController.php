<?php

namespace App\Http\Controllers\Helper;

use App\Http\Controllers\Controller;
use App\Models\ReadinessCheck;
use App\Services\HelperEligibilityService;
use App\Services\HelperMatchingService;
use App\Services\HelperReadinessService;
use App\Services\HelperWorkflowMaintenance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class HelperReadinessController extends Controller
{
    private const VALIDITY_HOURS = 4;

    /**
     * Show the readiness check form
     */
    public function index()
    {
        abort_unless(auth()->user()?->role === 'helper' && auth()->user()?->is_active, 403);
        $user = Auth::user();
        $helper = $user->helper;

        $latestCheck = ReadinessCheck::where('helper_id', $helper->id)
            ->orderBy('created_at', 'desc')
            ->first();

        $readinessStatus = $helper->getReadinessStatus();
        $lastReadiness = $helper->getCurrentReadiness();

        // ?refresh=1 lets a helper who is already ready re-run the assessment.
        $forceRedo = request()->query('refresh') === '1';
        if ($forceRedo) {
            $readinessStatus = $helper->isReady() ? 'not_assessed' : $readinessStatus;
        }

        return view('helper.readiness', compact('helper', 'latestCheck', 'readinessStatus', 'lastReadiness'));
    }

    /**
     * Return the helper's current readiness state as JSON (used by the UI).
     */
    public function status()
    {
        abort_unless(auth()->user()?->role === 'helper' && auth()->user()?->is_active, 403);
        $helper = Auth::user()->helper;

        return response()->json([
            'status' => $helper->getReadinessStatus(),
            'ready' => $helper->isReady(),
            'valid_until' => $helper->getCurrentReadiness()?->valid_until?->toISOString(),
        ]);
    }

    /**
     * Process the readiness check
     */
    public function store(Request $request)
    {
        abort_unless(auth()->user()?->role === 'helper' && auth()->user()?->is_active, 403);
        $request->merge(['skills_confirmed' => $request->input('skills_confirmed', [])]);
        $validated = $request->validate([
            'skills_confirmed' => 'present|array',
            'skills_confirmed.*' => ['distinct', Rule::in(HelperReadinessService::SKILLS)],
            'emotionally_ready' => 'required|boolean',
            'willing_to_listen' => 'required|boolean',
            'stress_level' => 'required|in:low,moderate,high',
            'availability_status' => 'required|in:available,not_ready',
            'physical_condition' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'shift_start' => 'nullable|date_format:H:i',
            'shift_end' => 'nullable|date_format:H:i|after:shift_start',
            'exercise_completed' => 'required|in:completed,skipped',
        ]);

        $readiness = app(HelperReadinessService::class)->submit($request->user(), $validated);
        $helper = $request->user()->helper->fresh();
        $reasons = app(HelperEligibilityService::class)->reasons($helper);

        return redirect()->route('helper.dashboard')->with($readiness->isReady() ? 'success' : 'warning',
            $readiness->isReady() ? ($reasons ? 'Readiness saved. '.implode(' ', $reasons) : 'Readiness saved. You are available for matching.') : 'You are not ready to take sessions. Self-care, profile, schedule and training remain available.');
    }

    public function updateAvailability(Request $request)
    {
        abort_unless(auth()->user()?->role === 'helper' && auth()->user()?->is_active, 403);
        $validated = $request->validate([
            'status' => 'required|in:available,unavailable,break,offline',
            'reason' => 'nullable|string|max:255',
        ]);

        $helper = Auth::user()->helper;
        $hasCurrentReadiness = (bool) $helper->latestReadiness?->isReady();

        if ($validated['status'] === 'available' && ! $hasCurrentReadiness) {
            return redirect()->route('helper.readiness')
                ->with('warning', 'Complete a current readiness assessment before becoming available.');
        }

        if ($validated['status'] === 'available') {
            app(HelperEligibilityService::class)->requireEligible($helper, null, true);
        }
        $availability = $validated['status'] === 'offline' ? 'unavailable' : $validated['status'];
        $helper->setAvailability($availability, $validated['reason'] ?? null);
        if ($availability !== 'available') {
            foreach ($helper->activeSessions()->whereNull('helper_accepted_at')->get() as $pending) {
                app(HelperWorkflowMaintenance::class)->releaseRecommendation($pending, 'availability_changed');
            }
        }

        if ($availability === 'available') {
            app(HelperMatchingService::class)->matchWaitingRequests();
        }

        return back()->with('success', 'Availability updated successfully.');
    }

    /**
     * Show readiness history
     */
    public function history()
    {
        abort_unless(auth()->user()?->role === 'helper' && auth()->user()?->is_active, 403);
        $user = Auth::user();
        $helper = $user->helper;

        $checks = ReadinessCheck::where('helper_id', $helper->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('helper.readiness-history', compact('checks'));
    }
}
