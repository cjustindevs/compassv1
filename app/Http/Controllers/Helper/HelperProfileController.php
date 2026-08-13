<?php

namespace App\Http\Controllers\Helper;

use App\Http\Controllers\Controller;
use App\Models\HelperCompetencyHistory;
use App\Models\Session;
use App\Models\SessionReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HelperProfileController extends Controller
{
    /**
     * Show the helper profile — all data from the database.
     */
    public function index()
    {
        $user = Auth::user();
        $helper = $user->helper;

        $totalSessions = Session::where('helper_id', $helper->id)->count();
        $completedSessions = Session::where('helper_id', $helper->id)
            ->where('session_status', 'completed')
            ->count();
        $reportsCount = SessionReport::whereIn('session_id', Session::where('helper_id', $helper->id)->pluck('id'))
            ->count();

        $latestCompetency = HelperCompetencyHistory::where('helper_id', $helper->id)
            ->latest('evaluation_date')
            ->first();
        $competencyScore = $latestCompetency ? (int) round((float) $latestCompetency->overall_score) : 0;

        $recentSessions = Session::with(['seeker', 'concern'])
            ->where('helper_id', $helper->id)
            ->orderByDesc('created_date')
            ->limit(5)
            ->get();

        $readiness = $helper->latestReadiness;

        return view('helper.profile', compact(
            'user',
            'helper',
            'totalSessions',
            'completedSessions',
            'reportsCount',
            'competencyScore',
            'recentSessions',
            'readiness'
        ));
    }

    /**
     * Update the helper's profile information.
     */
    public function update(Request $request)
    {
        $user = Auth::user();
        $helper = $user->helper;

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'bio' => 'nullable|string|max:1000',
            'phone' => 'nullable|string|max:30',
            'specializations' => 'nullable|string|max:500',
            'preferred_language' => 'nullable|string|max:50',
            'name' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255|unique:users,email,' . $user->id,
        ]);

        $helper->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'bio' => $validated['bio'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'specializations' => $validated['specializations'] ?? null,
            'preferred_language' => $validated['preferred_language'] ?? 'English',
        ]);

        if (! empty($validated['name']) || ! empty($validated['email'])) {
            $user->update([
                'name' => $validated['name'] ?? $user->name,
                'email' => $validated['email'] ?? $user->email,
            ]);
        }

        return back()->with('success', 'Profile updated successfully.');
    }
}