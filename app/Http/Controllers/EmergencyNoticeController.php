<?php

namespace App\Http\Controllers;

use App\Models\EmergencyAlert;
use App\Models\Notification;
use App\Models\Referral;
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

    public function next()
    {
        $this->authorizeStaff();
        if (auth()->user()->role === 'professional') {
            return $this->professionalNotice();
        }
        $caseStates = [];
        $notice = Notification::where('user_account_id', auth()->id())->where('notification_type', 'emergency')
            ->whereNull('popup_dismissed_at')->lazyByIdDesc(100)->first(function ($notice) use (&$caseStates) {
                $matched = preg_match('#^/adviser/emergencies?/(\d+)$#', $notice->link ?? '', $match)
                    || preg_match('/^Emergency #(\d+) requires staff attention/', $notice->message ?? '', $match);
                if (! $matched) {
                    return true;
                } // Older notifications have no case reference.
                $id = (int) $match[1];

                return $caseStates[$id] ??= EmergencyAlert::whereKey($id)->whereNotIn('status', ['resolved', 'closed'])->whereNull('archived_at')->exists();
            });

        return response()->json(['notice' => $notice ? ['reference' => Crypt::encryptString((string) $notice->id), 'title' => 'Emergency requires attention', 'message' => 'Open your emergency queue to review the case and coordinate support.', 'url' => auth()->user()->role === 'adviser' && preg_match('#^/adviser/emergencies?/\d+$#', $notice->link ?? '') ? $notice->link : (auth()->user()->role === 'adviser' ? route('adviser.emergencies') : route('moderator.emergency'))] : null])->header('Cache-Control', 'private, no-store');
    }

    public function dismiss(Request $request)
    {
        $this->authorizeStaff();
        $data = $request->validate(['reference' => 'required|string|max:2000']);
        try {
            $id = Crypt::decryptString($data['reference']);
        } catch (\Throwable $e) {
            abort(404);
        } $notice = Notification::where('user_account_id', auth()->id())->where('notification_type', 'emergency')->findOrFail($id);
        $notice->forceFill(['popup_dismissed_at' => now()])->save();

        return response()->noContent();
    }

    private function professionalNotice()
    {
        $professionalId = auth()->user()->psychologyProfessional->id;
        $notice = Notification::where('user_account_id', auth()->id())->where('notification_type', 'emergency')
            ->whereNull('popup_dismissed_at')->lazyByIdDesc(100)->first(function ($notice) use ($professionalId) {
                if (! preg_match('#^/professional/referral/(\d+)$#', $notice->link ?? '', $match)) {
                    return false;
                }

                return Referral::professionalAuthorized()->whereKey((int) $match[1])->where('professional_id', $professionalId)
                    ->whereIn('status', [Referral::STATUS_PENDING_PROFESSIONAL, Referral::STATUS_ACCEPTED, Referral::STATUS_IN_PROGRESS])
                    ->whereExists(function ($query) {
                        $query->selectRaw('1')->from('emergency_alerts')->whereColumn('emergency_alerts.referral_id', 'referrals.id')
                            ->whereColumn('emergency_alerts.session_id', 'referrals.session_id')
                            ->whereNotIn('emergency_alerts.status', ['resolved', 'closed'])->whereNull('emergency_alerts.archived_at');
                    })->exists();
            });

        return response()->json(['notice' => $notice ? [
            'reference' => Crypt::encryptString((string) $notice->id), 'title' => 'Urgent emergency referral assigned',
            'message' => 'An Adviser raised an emergency referral to you. Open the assigned referral to review and respond. Personal information requires separate authorized release.',
            'url' => $notice->link,
        ] : null])->header('Cache-Control', 'private, no-store');
    }
}
