<?php

namespace App\Http\Controllers\Helper;

use App\Http\Controllers\Controller;
use App\Models\Helper;
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

    /**
     * First-time helpers with no helper record are sent here to complete their
     * profile before they can access the rest of the module.
     */
    public function onboarding()
    {
        $user = Auth::user();

        return view('helper.onboarding', [
            'firstName' => $user->name ? explode(' ', $user->name)[0] : '',
            'lastName' => $user->name ? (explode(' ', $user->name, 2)[1] ?? '') : '',
            'email' => $user->email,
        ]);
    }

    /**
     * Persist the helper's profile from the onboarding form.
     */
    public function storeOnboarding(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:255|unique:helpers,email',
            'phone' => 'nullable|string|max:30',
            'specializations' => 'nullable|string|max:500',
            'preferred_language' => 'nullable|string|max:50',
            'bio' => 'nullable|string|max:1000',
        ]);

        Helper::create([
            'user_account_id' => $user->id,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'specializations' => $validated['specializations'] ?? null,
            'preferred_language' => $validated['preferred_language'] ?? 'English',
            'bio' => $validated['bio'] ?? null,
            'status' => 'available',
            'competency_level' => 1,
            'max_concurrent_sessions' => 2,
        ]);

        return redirect()->route('helper.readiness')
            ->with('success', 'Welcome aboard! Complete a quick readiness check to start taking sessions.');
    }
}