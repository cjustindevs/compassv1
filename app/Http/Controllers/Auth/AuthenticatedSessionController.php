<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = $request->user();

        $staleBefore = now()->subMinutes(5)->getTimestamp();

        $currentSessionId = $request->session()->getId();

        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentSessionId)
            ->where('last_activity', '<', $staleBefore)
            ->delete();

        $liveOtherSession = DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentSessionId)
            ->exists();

        if ($liveOtherSession) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'This account is already signed in on another device. Sign out there first, or try again in a few minutes.',
            ]);
        }

        $request->session()->regenerate();

        // Role-based redirection
        $redirectTo = match ($user->role) {
            'admin' => route('admin.dashboard'),
            'adviser' => route('adviser.dashboard'),
            'helper' => route('helper.dashboard'),
            'moderator' => route('moderator.dashboard'),
            'professional' => route('professional.dashboard'),
            default => route('seeker.dashboard'),
        };

        return redirect()->intended($redirectTo);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}