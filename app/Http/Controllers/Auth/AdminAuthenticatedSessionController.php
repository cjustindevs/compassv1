<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminLoginRequest;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminAuthenticatedSessionController extends Controller
{
    /**
     * Display the dedicated system-administrator login page.
     */
    public function create(): View
    {
        return view('auth.admin-login');
    }

    /**
     * Authenticate a system administrator and start their session.
     */
    public function store(AdminLoginRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        try {
            $request->authenticate();
        } catch (ValidationException $exception) {
            $identifier = trim((string) $request->input('username'));

            $auditLogger->record(
                null,
                AuditLogger::ADMIN_LOGIN_FAILED,
                'authentication',
                $identifier === '' ? 'Administrator portal' : 'Account: '.$identifier,
                $request
            );

            throw $exception;
        }

        $request->session()->regenerate();

        $auditLogger->record(
            $request->user(),
            AuditLogger::ADMIN_LOGIN_SUCCEEDED,
            'authentication',
            'Administrator portal',
            $request
        );

        return redirect()->route('admin.dashboard');
    }
}
