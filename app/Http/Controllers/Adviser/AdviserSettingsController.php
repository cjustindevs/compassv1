<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AdviserSettingsController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $adviser = $user->adviser;

        return view('adviser.settings', compact('user', 'adviser'));
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $adviser = $user->adviser;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        $adviser->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
        ]);

        return back()->with('success', 'Profile updated successfully.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = auth()->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'The current password is incorrect.'])->withInput();
        }

        $user->update([
            'password' => Hash::make($validated['new_password']),
        ]);

        return back()->with('success', 'Password updated successfully.');
    }

    public function updateAppearance(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'dark_mode' => 'nullable|boolean',
            'font_size' => 'nullable|in:small,medium,large',
            'high_contrast' => 'nullable|boolean',
        ]);

        auth()->user()->update([
            'dark_mode' => $request->boolean('dark_mode'),
            'font_size' => $validated['font_size'] ?? 'medium',
            'high_contrast' => $request->boolean('high_contrast'),
        ]);

        return back()->with('success', 'Appearance settings saved.');
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email_notifications' => 'nullable|boolean',
            'push_notifications' => 'nullable|boolean',
            'session_reminders' => 'nullable|boolean',
            'marketing_emails' => 'nullable|boolean',
        ]);

        auth()->user()->update([
            'email_notifications' => $request->boolean('email_notifications'),
            'push_notifications' => $request->boolean('push_notifications'),
            'session_reminders' => $request->boolean('session_reminders'),
            'marketing_emails' => $request->boolean('marketing_emails'),
        ]);

        return back()->with('success', 'Notification preferences saved.');
    }
}
