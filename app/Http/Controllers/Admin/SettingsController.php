<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\ActiveSessionCatalog;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class SettingsController extends Controller
{
    private const ACCOUNT_BOOLEAN_PREFERENCES = [
        'email_notifications',
        'show_email',
        'allow_data_research',
    ];

    /**
     * Display the current administrator's personal workspace settings.
     */
    public function index(Request $request, ActiveSessionCatalog $sessionCatalog): View
    {
        $sessionLoadFailed = false;

        try {
            $activeSessions = $sessionCatalog->sessions($request, $request->user());
        } catch (Throwable $exception) {
            report($exception);
            $sessionLoadFailed = true;
            $activeSessions = collect([$sessionCatalog->currentSession($request)]);
        }

        $passwordChangedAt = AuditLog::query()
            ->where('user_account_id', $request->user()->getKey())
            ->where('action', AuditLogger::PASSWORD_CHANGED)
            ->latest('created_at')
            ->value('created_at');

        return view('admin.settings.index', [
            'admin' => $request->user(),
            'activeSessions' => $activeSessions,
            'sessionLoadFailed' => $sessionLoadFailed,
            'passwordChangedLabel' => $passwordChangedAt
                ? 'Last changed '.CarbonImmutable::parse($passwordChangedAt)->diffForHumans()
                : 'Last change date unavailable',
            'twoFactorAvailable' => false,
            'emergencySmsAvailable' => false,
            'interfaceLanguage' => 'English',
        ]);
    }

    /**
     * Persist one supported account-level boolean preference immediately.
     */
    public function updatePreference(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'setting' => ['required', 'string', Rule::in(self::ACCOUNT_BOOLEAN_PREFERENCES)],
            'enabled' => ['required', 'boolean'],
        ]);

        $request->user()->forceFill([
            $validated['setting'] => $validated['enabled'],
        ])->save();

        return response()->json([
            'saved' => true,
            'setting' => $validated['setting'],
            'enabled' => (bool) $validated['enabled'],
        ]);
    }
}
