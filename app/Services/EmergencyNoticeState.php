<?php

namespace App\Services;

use App\Models\EmergencyAlert;
use App\Models\Notification;
use App\Models\Referral;
use App\Models\User;

/** Per-request status checks shared by urgent dialogs and notification toasts. */
class EmergencyNoticeState
{
    private array $states = [];

    private ?array $activeIds = null;

    public function key(Notification $notice, User $user): ?string
    {
        if (! $user->is_active || $notice->notification_type !== 'emergency') {
            return null;
        }
        $id = $notice->emergency_alert_id;
        if ($user->role === 'professional') {
            if (! $user->psychologyProfessional) {
                return null;
            }
            if (! preg_match('#^/professional/referral/(\d+)$#', $notice->link ?? '', $match)) {
                return null;
            }
            $referralId = (int) $match[1];
            $cache = 'professional:'.$user->id.':'.$referralId.':'.$id;
            if (! array_key_exists($cache, $this->states)) {
                $referral = Referral::professionalAuthorized()->whereKey($referralId)
                    ->where('professional_id', $user->psychologyProfessional?->id)
                    ->whereIn('status', [Referral::STATUS_PENDING_PROFESSIONAL, ...Referral::ACTIVE_STATUSES])->first();
                $alert = $referral ? EmergencyAlert::where('referral_id', $referralId)->where('session_id', $referral->session_id)
                    ->when($id, fn ($q) => $q->whereKey($id))->latest('id')->first() : null;
                $this->states[$cache] = $alert && $this->active($alert->id) ? 'alert:'.$alert->id : null;
            }

            return $this->states[$cache];
        }
        if (! in_array($user->role, ['adviser', 'moderator'], true)) {
            return null;
        }
        if (! $id && preg_match('#^/(?:adviser|moderator)/emergency/cases/incident_reports/(\d+)$#', $notice->link ?? '', $legacy)) {
            $legacyId = (int) $legacy[1];
            $active = app(ModeratorEmergencyCases::class)->active()->where('source', 'incident_reports')->where('id', $legacyId)->exists();
            return $active ? 'incident:'.$legacyId : null;
        }
        if (! $id) {
            $matched = preg_match('#^/adviser/emergencies?/(\d+)$#', $notice->link ?? '', $match)
                || preg_match('/^Emergency #(\d+) requires staff attention/', $notice->message ?? '', $match);
            if (! $matched) {
                // Ambiguous legacy notices are retained as history, never guessed to be active.
                return null;
            }
            $id = (int) $match[1];
        }

        return $this->active((int) $id) ? 'alert:'.$id : null;
    }

    private function active(int $id): bool
    {
        // A long notification history must not produce a database lookup for every old alert.
        $this->activeIds ??= array_fill_keys(app(ModeratorEmergencyCases::class)->active()
            ->where('source', 'emergency_alerts')->pluck('id')->all(), true);

        return isset($this->activeIds[$id]);
    }
}
