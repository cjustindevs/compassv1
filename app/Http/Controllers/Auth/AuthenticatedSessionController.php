<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\RoleDashboard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
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
    public function store(LoginRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $administrator = User::query()
            ->where('role', 'admin')
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim((string) $request->input('email')))])
            ->first();

        try {
            $request->authenticate();
        } catch (ValidationException $exception) {
            if ($administrator) {
                $auditLogger->record(
                    null,
                    AuditLogger::ADMIN_LOGIN_FAILED,
                    'authentication',
                    'Account: '.$administrator->email,
                    $request
                );
            }

            throw $exception;
        }

        $request->session()->regenerate();

        $user = $request->user();

        if ($user->role === 'admin') {
            $auditLogger->record(
                $user,
                AuditLogger::ADMIN_LOGIN_SUCCEEDED,
                'authentication',
                'Shared COMPASS login',
                $request
            );
        }

        return redirect()->route(RoleDashboard::routeNameFor($user));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
