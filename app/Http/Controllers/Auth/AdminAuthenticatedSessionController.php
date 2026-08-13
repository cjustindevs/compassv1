<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminLoginRequest;
use Illuminate\Http\RedirectResponse;
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
    public function store(AdminLoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }
}
