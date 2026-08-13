<?php

namespace App\Http\Controllers\Helper;

use App\Http\Controllers\Controller;
use App\Models\ReadinessCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HelperReadinessController extends Controller
{
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

        return view('helper.readiness', compact('helper', 'latestCheck'));
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
            'assessment_date' => now(),
            'breathing_exercise' => $validated['exercise_completed']
        ]);

        session([
            'helper_readiness' => $status,
            'helper_readiness_id' => $readiness->readiness_id
        ]);

        return redirect()->route('helper.dashboard')
            ->with('readiness_status', $status)
            ->with('success', $passed 
                ? 'You are ready to accept sessions. Stay safe and take care!' 
                : 'You are not ready to accept sessions. Please take time to rest and recharge.');
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