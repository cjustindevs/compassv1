<?php

namespace App\Http\Controllers;

use App\Models\Session;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public const LANGUAGES = ['English', 'Filipino', 'Cebuano', 'Ilocano', 'Hiligaynon', 'Other'];

    public const DURATIONS = ['15', '30', '45', '60'];

    public const FONT_SIZES = ['small', 'medium', 'large'];

    /**
     * Settings dashboard (tabs hub).
     */
    public function index(): View
    {
        return view('settings.index', ['user' => Auth::user()]);
    }

    /**
     * Account settings: email, alias, gender, age, password, deletion.
     */
    public function account(): View
    {
        return view('settings.account', ['user' => Auth::user()]);
    }

    /**
     * Notification & session preferences.
     */
    public function preferences(): View
    {
        return view('settings.preferences', [
            'user' => Auth::user(),
            'languages' => static::LANGUAGES,
            'durations' => static::DURATIONS,
        ]);
    }

    /**
     * Privacy settings.
     */
    public function privacy(): View
    {
        return view('settings.privacy', ['user' => Auth::user()]);
    }

    /**
     * Appearance settings.
     */
    public function appearance(): View
    {
        return view('settings.appearance', [
            'user' => Auth::user(),
            'fontSizes' => static::FONT_SIZES,
            'currentTheme' => Auth::user()->theme_preference
                ?? (Auth::user()->dark_mode ? 'dark' : 'light'),
        ]);
    }

    /**
     * Update account information (email, alias, gender, age).
     */
    public function updateAccount(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'alias' => ['nullable', 'string', 'max:60', 'alpha_dash', 'unique:help_seekers,generated_alias,' . optional($user->helpSeeker)->id],
            'gender' => ['nullable', 'string', 'in:female,male,non-binary,prefer-not-to-say'],
            'age' => ['nullable', 'integer', 'min:13', 'max:120'],
        ]);

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($helpSeeker = $user->helpSeeker) {
            $helpSeeker->update([
                'generated_alias' => $validated['alias'] ?? $helpSeeker->generated_alias,
                'gender' => $validated['gender'] ?? $helpSeeker->gender,
                'age' => $validated['age'] ?? $helpSeeker->age,
            ]);
        }

        return back()->with('success', 'Account details updated.');
    }

    /**
     * Update notification & session preferences.
     */
    public function updatePreferences(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email_notifications' => 'nullable|boolean',
            'push_notifications' => 'nullable|boolean',
            'session_reminders' => 'nullable|boolean',
            'marketing_emails' => 'nullable|boolean',
            'preferred_language' => ['required', 'string', 'max:40'],
            'preferred_communication_mode' => ['required', 'string', 'in:chat,voice'],
            'preferred_helper_gender' => ['nullable', 'string', 'max:20'],
            'session_duration_preference' => ['required', 'string', 'in:15,30,45,60'],
        ]);

        $request->user()->update([
            'email_notifications' => $request->boolean('email_notifications'),
            'push_notifications' => $request->boolean('push_notifications'),
            'session_reminders' => $request->boolean('session_reminders'),
            'marketing_emails' => $request->boolean('marketing_emails'),
            'preferred_language' => $validated['preferred_language'],
            'preferred_communication_mode' => $validated['preferred_communication_mode'],
            'preferred_helper_gender' => $validated['preferred_helper_gender'] ?? null,
            'session_duration_preference' => $validated['session_duration_preference'],
        ]);

        return back()->with('success', 'Preferences updated.');
    }

    /**
     * Update privacy settings.
     */
    public function updatePrivacy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'show_email' => 'nullable|boolean',
            'allow_data_research' => 'nullable|boolean',
        ]);

        $request->user()->update([
            'show_email' => $request->boolean('show_email'),
            'allow_data_research' => $request->boolean('allow_data_research'),
        ]);

        if ($request->boolean('clear_sessions')) {
            $this->clearSessionHistory($request->user());
        }

        return back()->with('success', 'Privacy settings updated.');
    }

    /**
     * Update appearance settings (theme preference, contrast, motion, font).
     * Accepts both a full form POST and an AJAX JSON payload.
     */
    public function updateAppearance(Request $request): RedirectResponse
    {
        $theme = $request->input('theme');
        $rules = [
            'high_contrast' => 'nullable|boolean',
            'reduced_motion' => 'nullable|boolean',
            'font_size' => ['nullable', 'string', 'in:small,medium,large'],
        ];
        if ($theme !== null) {
            $rules['theme'] = ['required', 'string', 'in:light,dark,system'];
        }
        $validated = $request->validate($rules);

        $update = [
            'high_contrast' => $request->boolean('high_contrast'),
            'reduced_motion' => $request->boolean('reduced_motion'),
            'font_size' => $validated['font_size'] ?? $request->user()->font_size ?? 'medium',
        ];

        if ($theme !== null) {
            $update['theme_preference'] = $theme;
            $update['dark_mode'] = $theme === 'dark';
        }

        $request->user()->update($update);

        if ($request->expectsJson() || $request->isJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Appearance updated.');
    }

    /**
     * AJAX-only endpoint for the quick Light / Dark / System switch.
     */
    public function updateTheme(Request $request)
    {
        $validated = $request->validate([
            'theme' => ['required', 'string', 'in:light,dark,system'],
        ]);

        $request->user()->update([
            'theme_preference' => $validated['theme'],
            'dark_mode' => $validated['theme'] === 'dark',
        ]);

        return response()->json(['success' => true, 'theme' => $validated['theme']]);
    }

    /**
     * Permanently delete all session history for the current user.
     */
    public function clearSessionHistory($user): void
    {
        $helpSeeker = $user->helpSeeker;

        if (! $helpSeeker) {
            return;
        }

        Session::where('seeker_id', $helpSeeker->id)->delete();
    }

    /**
     * GDPR-style export of all user data as JSON.
     */
    public function downloadData(Request $request)
    {
        $user = $request->user();
        $helpSeeker = $user->helpSeeker;

        $sessions = $helpSeeker
            ? Session::with(['messages', 'evaluation', 'concern'])
                ->where('seeker_id', $helpSeeker->id)
                ->get(['id', 'helper_id', 'session_type', 'session_status', 'start_time', 'end_time', 'duration', 'created_date', 'risk_level'])
            : collect();

        $data = [
            'exported_at' => now()->toDateTimeString(),
            'profile' => [
                'email' => $user->email,
                'name' => $user->name,
                'role' => $user->role,
                'member_since' => $user->created_at?->toDateTimeString(),
            ],
            'seeker_profile' => $helpSeeker ? [
                'alias' => $helpSeeker->generated_alias,
                'gender' => $helpSeeker->gender,
                'age' => $helpSeeker->age,
            ] : null,
            'preferences' => [
                'notification_preferences' => [
                    'email' => $user->email_notifications,
                    'push' => $user->push_notifications,
                    'session_reminders' => $user->session_reminders,
                    'marketing' => $user->marketing_emails,
                ],
                'session_preferences' => [
                    'language' => $user->preferred_language,
                    'mode' => $user->preferred_communication_mode,
                    'helper_gender' => $user->preferred_helper_gender,
                    'duration' => $user->session_duration_preference,
                ],
            ],
            'sessions' => $sessions->map(function (Session $session) {
                return [
                    'id' => $session->id,
                    'type' => $session->session_type,
                    'status' => $session->session_status,
                    'started_at' => $session->start_time?->toDateTimeString(),
                    'ended_at' => $session->end_time?->toDateTimeString(),
                    'duration_minutes' => $session->duration,
                    'risk_level' => $session->risk_level,
                    'created_at' => $session->created_date?->toDateTimeString(),
                    'messages' => $session->messages->pluck('message_text'),
                    'evaluation' => $session->evaluation ? [
                        'overall_rating' => $session->evaluation->overall_score,
                        'comments' => $session->evaluation->comments,
                    ] : null,
                ];
            }),
        ];

        $filename = 'compass-data-export-' . now()->format('Y-m-d') . '.json';

        return response()->streamDownload(function () use ($data) {
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }, $filename, ['Content-Type' => 'application/json']);
    }
}