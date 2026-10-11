<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Resolve a private address to the existing nickname identity. Keep the
        // public response identical for missing accounts and delivery failures.
        try {
            $email = app(\App\Services\AccountRecoveryService::class)->signInAddress($request->string('email')->toString());
            \Illuminate\Support\Facades\DB::transaction(function () use ($email) {
                // Serialize with recovery-address changes, which revoke old links.
                \App\Models\User::where('email', $email)->lockForUpdate()->first();
                Password::sendResetLink(['email' => $email, 'is_active' => true]);
            });
        } catch (\Throwable $exception) {
            Log::warning('Password reset delivery failed', ['exception_type' => get_class($exception)]);
        }

        return back()->with('status', 'If an account matches that email, a password-reset link will be sent. Please check your inbox and spam folder.');
    }
}
