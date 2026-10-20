<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\EmergencyNoticeState;
use App\Services\NotificationPopupDelivery;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class EmergencyNoticeController extends Controller
{
    private function authorizeStaff(): void
    {
        abort_unless(auth()->user()?->is_active && in_array(auth()->user()->role, ['adviser', 'moderator', 'professional'], true), 403);
        if (auth()->user()->role === 'professional') {
            abort_unless(auth()->user()->psychologyProfessional, 403);
        }
    }

    public function next(Request $request)
    {
        $this->authorizeStaff();
        $data = $request->validate(['current' => 'nullable|string|max:2000']);
        $state = new EmergencyNoticeState;
        $delivery = app(NotificationPopupDelivery::class);
        $owned = Notification::where('user_account_id', auth()->id())->where('notification_type', 'emergency')->whereNull('popup_dismissed_at');
        $notice = null;
        if (! empty($data['current'])) {
            try {
                $id = Crypt::decryptString($data['current']);
                $current = ctype_digit($id) && strlen($id) <= 18 ? (clone $owned)->find($id) : null;
                if ($current && $state->key($current, auth()->user())) {
                    $notice = $current;
                }
            } catch (DecryptException $error) {
                // A stale/invalid token does not grant access to any other user's notice.
            }
        }
        $notice ??= (clone $owned)->whereNull('popup_presented_at')
            ->where('created_at', '>=', now()->subMinutes(NotificationPopupDelivery::FRESH_MINUTES))
            ->lazyByIdDesc(100)->first(fn ($notice) => $delivery->eligible($notice, auth()->user()));
        $professional = auth()->user()->role === 'professional';
        $url = $professional ? $notice?->link : (auth()->user()->role === 'adviser' ? route('adviser.emergencies') : route('moderator.emergency'));
        if (! $professional && auth()->user()->role === 'adviser' && $notice) {
            $caseKey = $state->key($notice, auth()->user());
            $url = str_starts_with($caseKey, 'incident:') ? route('adviser.emergency.coordinate', ['incident_reports', (int) substr($caseKey, 9)], false)
                : route('adviser.emergencies.show', (int) substr($caseKey, 6), false);
        }
        if (! $professional && auth()->user()->role === 'moderator' && $notice) {
            $caseKey = $state->key($notice, auth()->user());
            $url = str_starts_with($caseKey, 'incident:') ? route('moderator.emergency.coordinate', ['incident_reports', (int) substr($caseKey, 9)], false)
                : route('moderator.emergency.coordinate', ['emergency_alerts', (int) substr($caseKey, 6)], false);
        }

        return response()->json(['notice' => $notice ? [
            'id' => $notice->id, 'case_key' => $state->key($notice, auth()->user()),
            'popup_key' => $delivery->key($notice, auth()->user()),
            'present_url' => route('notifications.present', $notice->id, false),
            'reference' => Crypt::encryptString((string) $notice->id),
            'title' => $professional ? 'Urgent emergency referral assigned' : 'Emergency requires attention',
            'message' => $professional ? 'An Adviser raised an emergency referral to you. Open the assigned referral to review and respond. Personal information requires separate authorized release.' : 'Open your emergency queue to review the case and coordinate support.',
            'url' => $url,
        ] : null])->header('Cache-Control', 'private, no-store');
    }

    public function dismiss(Request $request)
    {
        $this->authorizeStaff();
        $data = $request->validate(['reference' => 'required|string|max:2000']);
        try {
            $id = Crypt::decryptString($data['reference']);
        } catch (\Throwable $e) {
            abort(404);
        }
        abort_unless(ctype_digit($id) && strlen($id) <= 18, 404);
        $notice = Notification::where('user_account_id', auth()->id())->where('notification_type', 'emergency')->findOrFail($id);
        $state = new EmergencyNoticeState;
        $delivery = app(NotificationPopupDelivery::class);
        $key = $delivery->key($notice, auth()->user());
        $caseKey = $state->key($notice, auth()->user());
        $notice->forceFill(['popup_dismissed_at' => now()])->save();
        if ($key) {
            // Dismiss the current case's existing duplicate notices together; future reports still notify.
            Notification::where('user_account_id', auth()->id())->where('notification_type', 'emergency')
                ->whereNull('popup_dismissed_at')->where('id', '<=', $notice->id)->eachById(function ($duplicate) use ($delivery, $key, $caseKey, $state) {
                    if ($delivery->key($duplicate, auth()->user()) === $key || ($caseKey && $state->key($duplicate, auth()->user()) === $caseKey)) {
                        $duplicate->forceFill(['popup_dismissed_at' => now()])->save();
                    }
                });
        }

        return response()->noContent();
    }
}
