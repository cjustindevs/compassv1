<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\EmergencyNoticeState;
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
        $notice ??= (clone $owned)->lazyByIdDesc(100)->first(fn ($notice) => $state->key($notice, auth()->user()) !== null);
        $professional = auth()->user()->role === 'professional';
        $url = $professional ? $notice?->link : (auth()->user()->role === 'adviser' ? route('adviser.emergencies') : route('moderator.emergency'));
        if (! $professional && auth()->user()->role === 'adviser' && $notice) {
            $url = route('adviser.emergencies.show', (int) substr($state->key($notice, auth()->user()), 6), false);
        }

        return response()->json(['notice' => $notice ? [
            'id' => $notice->id, 'case_key' => $state->key($notice, auth()->user()),
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
        $key = $state->key($notice, auth()->user());
        $notice->forceFill(['popup_dismissed_at' => now()])->save();
        if ($key) {
            // Dismiss the current case's existing duplicate notices together; future reports still notify.
            Notification::where('user_account_id', auth()->id())->where('notification_type', 'emergency')
                ->whereNull('popup_dismissed_at')->eachById(function ($duplicate) use ($state, $key) {
                    if ($state->key($duplicate, auth()->user()) === $key) {
                        $duplicate->forceFill(['popup_dismissed_at' => now()])->save();
                    }
                });
        }

        return response()->noContent();
    }
}
