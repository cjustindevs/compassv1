<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\LoginSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

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

        $request->session()->regenerate();

        if (! app(LoginSessionService::class)->reserve($user, $request->session()->getId(), $request)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => LoginSessionService::DEVICE_MESSAGE,
            ]);
        }

        // Role-based redirection
        $redirectTo = match ($user->role) {
            'admin' => route('admin.dashboard'),
            'adviser' => route('adviser.dashboard'),
            'helper' => route('helper.dashboard'),
            'moderator' => route('moderator.dashboard'),
            'professional' => route('professional.dashboard'),
            default => route('seeker.dashboard'),
        };

        if ($user->role === 'moderator') {
            $request->session()->forget('url.intended');

            return redirect($redirectTo);
        }

        return redirect()->intended($redirectTo);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        if ($user = $request->user()) {
            app(LoginSessionService::class)->revoke($user, $request->session()->getId());
        }
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
