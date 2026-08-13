<?php

namespace App\Http\Controllers\Helper;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class HelperSettingsController extends Controller
{
    /**
     * Show the helper's settings — all values come from the users table.
     */
    public function index()
    {
        $user = Auth::user();

        return view('helper.settings', [
            'preferences' => $user->only([
                'dark_mode',
                'high_contrast',
                'font_size',
                'show_email',
                'allow_data_research',
                'email_notifications',
                'push_notifications',
                'session_reminders',
                'preferred_language',
                'preferred_communication_mode',
            ]),
        ]);
    }

    /**
     * Update the helper's settings and persist them in the database.
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'dark_mode' => 'nullable|boolean',
            'high_contrast' => 'nullable|boolean',
            'font_size' => ['nullable', Rule::in(['small', 'medium', 'large'])],
            'show_email' => 'nullable|boolean',
            'allow_data_research' => 'nullable|boolean',
            'email_notifications' => 'nullable|boolean',
            'push_notifications' => 'nullable|boolean',
            'session_reminders' => 'nullable|boolean',
            'preferred_language' => 'nullable|string|max:50',
            'preferred_communication_mode' => ['nullable', Rule::in(['chat', 'voice', 'both'])],
        ]);

        $user->update([
            'dark_mode' => $request->boolean('dark_mode'),
            'high_contrast' => $request->boolean('high_contrast'),
            'font_size' => $validated['font_size'] ?? 'medium',
            'show_email' => $request->boolean('show_email'),
            'allow_data_research' => $request->boolean('allow_data_research'),
            'email_notifications' => $request->boolean('email_notifications'),
            'push_notifications' => $request->boolean('push_notifications'),
            'session_reminders' => $request->boolean('session_reminders'),
            'preferred_language' => $validated['preferred_language'] ?? 'English',
            'preferred_communication_mode' => $validated['preferred_communication_mode'] ?? 'chat',
        ]);

        return back()->with('success', 'Settings updated successfully.');
    }
}