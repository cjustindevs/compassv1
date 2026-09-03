<?php

namespace App\Http\Controllers\Helper;

use App\Http\Controllers\Controller;
use App\Models\ReadinessCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HelperReadinessController extends Controller
{
    private const VALIDITY_HOURS = 4;

    /**
     * Show the readiness check form
     */
    public function index()
    {
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
        $validated = $request->validate([
            'emotionally_ready' => 'required|boolean',
            'willing_to_listen' => 'required|boolean',
            'stress_level' => 'required|in:low,moderate,high',
            'availability_status' => 'required|in:available,not_ready',
            'physical_condition' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'shift_start' => 'nullable|date_format:H:i',
            'shift_end' => 'nullable|date_format:H:i|after:shift_start',
            'exercise_completed' => 'required|in:completed,skipped'
        ]);

        $user = Auth::user();
        $helper = $user->helper;

        $passed = $validated['emotionally_ready'] &&
                  $validated['willing_to_listen'] &&
                  $validated['stress_level'] !== 'high';

        $status = $passed ? 'ready' : 'not_ready';

        $readiness = ReadinessCheck::create([
            'helper_id' => $helper->id,
            'availability_status' => $validated['availability_status'],
            'assessment_result' => $status,
            'emotionally_ready' => $validated['emotionally_ready'],
            'willing_to_listen' => $validated['willing_to_listen'],
            'stress_level' => $validated['stress_level'],
            'physical_condition' => $validated['physical_condition'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'assessment_date' => now(),
            'valid_until' => now()->addHours(self::VALIDITY_HOURS),
            'shift_start' => isset($validated['shift_start']) ? now()->setTimeFromTimeString($validated['shift_start']) : null,
            'shift_end' => isset($validated['shift_end']) ? now()->setTimeFromTimeString($validated['shift_end']) : null,
            'breathing_exercise' => $validated['exercise_completed']
        ]);

        // Flip the helper's availability so the matching engine can find them.
        // A helper is matchable only when they passed the assessment AND
        // explicitly chose to be available. Otherwise they stay offline.
        $isAvailable = $passed && $validated['availability_status'] === 'available';
        $helper->update([
            'status' => $isAvailable ? 'available' : 'offline',
            'availability' => $isAvailable ? 'available' : 'unavailable',
            'available_since' => $isAvailable ? now() : null,
            'is_ready' => $passed,
            'last_readiness_at' => now(),
        ]);

        session([
            'helper_readiness' => $status,
            'helper_readiness_id' => $readiness->id
        ]);

        // Helpers who aren't ready should be steered to self-help tools so
        // they can recharge before trying again.
        if (! $passed) {
            return redirect()->route('helper.self-help')
                ->with('warning', 'You are not ready to accept sessions right now. Take a moment for self-care — these tools can help.');
        }

        return redirect()->route('helper.dashboard')
            ->with('readiness_status', $status)
            ->with('success', match (true) {
                $isAvailable => 'You are now available to accept sessions. Stay safe and take care!',
                $passed => 'You are ready, but you chose not to be available right now. You can check in again whenever you are ready to help.',
            });
    }

    public function updateAvailability(Request $request)
    {
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

        $availability = $validated['status'] === 'offline' ? 'unavailable' : $validated['status'];
        $helper->setAvailability($availability, $validated['reason'] ?? null);

        return back()->with('success', 'Availability updated successfully.');
    }

    /**
     * Show readiness history
     */
    public function history()
    {
        $user = Auth::user();
        $helper = $user->helper;

        $checks = ReadinessCheck::where('helper_id', $helper->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('helper.readiness-history', compact('checks'));
    }
}
