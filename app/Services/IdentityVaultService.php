<?php

namespace App\Services;

use App\Models\Referral;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** The only application gateway to identity data. Never serialize a vault row. */
class IdentityVaultService
{
    public const FIELDS = ['real_name', 'phone_number', 'email', 'address', 'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relationship', 'student_id', 'department', 'year_level'];

    public function isAvailable(): bool
    {
        try {
            $this->cipher();
            $db = DB::connection('identity_vault');
            $db->getPdo();
            foreach (['idv_identities', 'idv_access_logs', 'idv_release_records'] as $table) {
                if (! $db->getSchemaBuilder()->hasTable($table)) return false;
            }
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function verifyStorage(): void
    {
        $pseudo = 'PS-HEALTH-' . bin2hex(random_bytes(8));
        $this->perform($pseudo, 'storage_self_test', app()->runningInConsole() && ! Auth::check(), function () use ($pseudo) {
            $db = DB::connection('identity_vault');
            $plaintext = 'Synthetic vault health check';
            $encrypted = $this->cipher()->encryptString($plaintext);
            $id = $db->table('idv_identities')->insertGetId([
                'pseudo_id' => $pseudo, 'seeker_alias' => 'HealthCheck', 'real_name' => $encrypted,
                'data_expires_at' => now()->addMinute(), 'created_at' => now(), 'updated_at' => now(),
            ], 'identity_id');
            $stored = $db->table('idv_identities')->where('identity_id', $id)->value('real_name');
            if ($stored === $plaintext || ! hash_equals($plaintext, $this->cipher()->decryptString($stored))) {
                throw new \RuntimeException('Storage verification failed.');
            }
            $db->table('idv_identities')->where('identity_id', $id)->delete();
        });
    }

    private function cipher(): Encrypter
    {
        $key = config('identity_vault.key');
        $key = is_string($key) && str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
        if (! is_string($key) || strlen($key) !== 32) {
            throw new HttpException(503, 'Identity storage is not configured. Please contact support.');
        }
        return new Encrypter($key, 'AES-256-CBC');
    }

    private function audit(string $pseudo, string $action, string $status, ?string $reason = null): void
    {
        $cipher = $this->cipher();
        DB::connection('identity_vault')->table('idv_access_logs')->insert([
            'pseudo_id' => $pseudo, 'accessed_by_user_id' => Auth::id(),
            'accessed_by_role' => Auth::user()?->role, 'action' => $action,
            'access_status' => $status, 'access_result' => $status,
            'access_reason' => $reason === null ? null : $cipher->encryptString($reason),
            'accessed_by_ip' => $cipher->encryptString((string) request()->ip()),
            'accessed_by_user_agent' => $cipher->encryptString(substr((string) request()->userAgent(), 0, 1000)),
            'accessed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function perform(string $pseudo, string $action, bool $allowed, callable $operation, ?string $reason = null): mixed
    {
        $allowed = $allowed && (! Auth::check() ? app()->runningInConsole() : (bool) Auth::user()->is_active);
        try {
            // Persist attempts separately so transaction rollback cannot erase them.
            $this->audit($pseudo, $action, $allowed ? 'attempted' : 'denied', $reason);
            abort_unless($allowed, 403, 'Identity access is not authorized.');
            return DB::connection('identity_vault')->transaction(function () use ($pseudo, $action, $operation, $reason) {
                $result = $operation();
                $this->audit($pseudo, $action, 'success', $reason);
                return $result;
            });
        } catch (\Throwable $exception) {
            if ($exception instanceof HttpException && $exception->getStatusCode() === 403) {
                try { if ($allowed) $this->audit($pseudo, $action, 'denied'); } catch (\Throwable) {
                    throw new HttpException(503, 'Identity information is unavailable.');
                }
                throw $exception;
            }
            try { $this->audit($pseudo, $action, 'failed'); } catch (\Throwable) { /* Fail closed below. */ }
            // Do not attach the original exception: database bindings can contain identity data.
            throw new HttpException(503, 'Identity information is unavailable. Please try again or contact support.');
        }
    }

    private function approved(Referral $referral): bool
    {
        return (bool) ($referral->approved_at && $referral->help_seeker_consent
            && ! in_array($referral->status, ['closed', 'declined', 'completed'], true));
    }

    public function storeForReferral(Referral $referral, array $data): void
    {
        $referral->refresh();
        $seeker = $referral->session->seeker;
        $allowed = Auth::user()?->role === 'seeker' && Auth::id() === $seeker->user_account_id && $this->approved($referral);
        $this->perform($seeker->pseudo_id, 'store', $allowed, function () use ($referral, $seeker, $data) {
            $db = DB::connection('identity_vault');
            $existing = $db->table('idv_identities')->where('pseudo_id', $seeker->pseudo_id)->lockForUpdate()->first();
            // Each submission gets a new version; previous releases cannot authorize the replacement.
            $values = [];
            foreach (self::FIELDS as $field) {
                $values[$field] = isset($data[$field]) && $data[$field] !== '' ? $this->cipher()->encryptString($data[$field]) : null;
            }
            $db->table('idv_identities')->updateOrInsert(['pseudo_id' => $seeker->pseudo_id], $values + [
                'seeker_alias' => $seeker->generated_alias, 'referral_id' => $referral->id,
                'identity_version' => ($existing?->identity_version ?? 0) + 1,
                'referral_consent_obtained' => true, 'consent_obtained_at' => $referral->consent_obtained_at,
                'is_active' => true, 'data_expires_at' => now()->addDays(config('identity_vault.retention_days')),
                'data_deleted_at' => null, 'identity_released' => false,
                'created_by' => Auth::id(), 'updated_by' => Auth::id(),
                'created_at' => $existing?->created_at ?? now(), 'updated_at' => now(),
            ]);
        });
        $referral->update(['identity_disclosed' => false]);
    }

    private function identity(string $pseudo): object
    {
        $row = DB::connection('identity_vault')->table('idv_identities')->where('pseudo_id', $pseudo)
            ->where('is_active', true)->where('data_expires_at', '>', now())->lockForUpdate()->first();
        abort_unless($row, 404, 'No current identity record.');
        return $row;
    }

    public function releaseForReferral(Referral $referral): void
    {
        $referral->refresh();
        $pseudo = $referral->session->seeker->pseudo_id;
        $allowed = Auth::user()?->role === 'adviser' && Auth::user()?->adviser?->id === $referral->adviser_id
            && $referral->adviser_id !== null && $this->approved($referral) && $referral->professional_id !== null;
        $this->perform($pseudo, 'release', $allowed, function () use ($pseudo, $referral) {
            $identity = $this->identity($pseudo);
            $db = DB::connection('identity_vault');
            $recipient = $referral->professional->user_account_id;
            if (! $db->table('idv_release_records')->where('referral_id', $referral->id)->where('identity_version', $identity->identity_version)->where('released_to_user_id', $recipient)->exists()) {
                $db->table('idv_release_records')->insert([
                    'pseudo_id' => $pseudo, 'identity_id' => $identity->identity_id, 'referral_id' => $referral->id,
                    'identity_version' => $identity->identity_version,
                    'session_id' => $referral->session_id, 'released_to_user_id' => $recipient, 'released_to_role' => 'professional',
                    'authorized_by_user_id' => Auth::id(), 'authorized_by_role' => 'adviser', 'authorized_at' => now(),
                    'release_reason' => 'approved_referral', 'information_released' => json_encode(self::FIELDS),
                    'consent_obtained' => true, 'consent_obtained_at' => $referral->consent_obtained_at,
                    'consent_method' => 'authenticated_seeker', 'released_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $db->table('idv_identities')->where('identity_id', $identity->identity_id)->update([
                'identity_released' => true, 'identity_released_at' => now(), 'identity_released_by' => Auth::id(),
                'identity_released_reason' => 'approved_referral', 'updated_at' => now(),
            ]);
        });
        $referral->update(['identity_disclosed' => true]);
    }

    public function readForReferral(Referral $referral): array
    {
        $referral->refresh();
        $pseudo = $referral->session->seeker->pseudo_id;
        $allowed = Auth::user()?->role === 'professional' && $referral->professional_id !== null
            && Auth::user()?->psychologyProfessional?->id === $referral->professional_id && $this->approved($referral);
        return $this->perform($pseudo, 'read', $allowed, function () use ($pseudo, $referral) {
            $identity = $this->identity($pseudo);
            $release = DB::connection('identity_vault')->table('idv_release_records')->where('referral_id', $referral->id)
                ->where('identity_version', $identity->identity_version)
                ->where('released_to_user_id', Auth::id())->where('release_reason', 'approved_referral')->first();
            abort_unless($release, 403, 'Identity has not been released to you.');
            $result = [];
            foreach (self::FIELDS as $field) {
                $result[$field] = $identity->$field === null ? null : $this->cipher()->decryptString($identity->$field);
            }
            return $result;
        });
    }

    public function purgeExpired(): int
    {
        $count = 0;
        DB::connection('identity_vault')->table('idv_identities')->where('is_active', true)
            ->where('data_expires_at', '<=', now())->orderBy('identity_id')->chunkById(100, function ($rows) use (&$count) {
                foreach ($rows as $row) {
                    $this->perform($row->pseudo_id, 'retention_delete', app()->runningInConsole() && ! Auth::check(), function () use ($row) {
                        DB::connection('identity_vault')->table('idv_identities')->where('identity_id', $row->identity_id)->update(
                            array_fill_keys(self::FIELDS, null) + ['is_active' => false, 'data_deleted_at' => now(), 'emergency_override_reason' => null, 'updated_at' => now()]
                        );
                        DB::connection('identity_vault')->table('idv_access_logs')->where('pseudo_id', $row->pseudo_id)->update([
                            'accessed_by_ip' => null, 'accessed_by_user_agent' => null, 'access_reason' => null, 'access_notes' => null,
                        ]);
                        DB::connection('identity_vault')->table('idv_release_records')->where('pseudo_id', $row->pseudo_id)->update([
                            'release_notes' => null, 'review_notes' => null, 'recipient_acknowledgement_notes' => null,
                        ]);
                    });
                    $count++;
                }
            }, 'identity_id');
        return $count;
    }

    public function emergencyRead(\App\Models\Session $session, string $reason): array
    {
        $session->refresh();
        $pseudo = $session->seeker->pseudo_id;
        $actor = Auth::user();
        // Only a dedicated responder or explicitly designated professional can override consent.
        $responder = $actor?->role === 'emergency_responder' || ($actor?->role === 'professional'
            && in_array($actor->id, config('identity_vault.emergency_responder_ids'), true));
        $activeAlert = $session->emergencyAlerts()->whereIn('status', ['pending', 'notified', 'referred', 'acknowledged', 'in_progress'])->exists();
        $allowed = $responder && $session->risk_level === 'emergency' && $session->requires_immediate_action
            && $activeAlert && mb_strlen(trim($reason)) >= 20;
        return $this->perform($pseudo, 'emergency_read', $allowed, function () use ($session, $pseudo, $reason) {
            $identity = $this->identity($pseudo);
            $fields = ['real_name', 'phone_number', 'address', 'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relationship'];
            $db = DB::connection('identity_vault');
            $db->table('idv_release_records')->insert([
                'pseudo_id' => $pseudo, 'identity_id' => $identity->identity_id, 'session_id' => $session->id,
                'identity_version' => $identity->identity_version,
                'released_to_user_id' => Auth::id(), 'released_to_role' => Auth::user()->role,
                'authorized_by_user_id' => Auth::id(), 'authorized_by_role' => Auth::user()->role,
                'authorized_at' => now(), 'release_reason' => 'life_threatening_emergency',
                'release_notes' => $this->cipher()->encryptString($reason), 'information_released' => json_encode($fields),
                'consent_obtained' => false, 'released_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $db->table('idv_identities')->where('identity_id', $identity->identity_id)->update([
                'emergency_override' => true, 'emergency_override_at' => now(), 'emergency_override_by' => Auth::id(),
                'emergency_override_reason' => $this->cipher()->encryptString($reason), 'updated_at' => now(),
            ]);
            $result = [];
            foreach ($fields as $field) {
                $result[$field] = $identity->$field === null ? null : $this->cipher()->decryptString($identity->$field);
            }
            return $result;
        }, $reason);
    }

    public function acknowledge(Referral $referral): void
    {
        $this->readForReferral($referral);
        $pseudo = $referral->session->seeker->pseudo_id;
        $this->perform($pseudo, 'acknowledge', true, function () use ($referral) {
            DB::connection('identity_vault')->table('idv_release_records')->where('referral_id', $referral->id)
                ->where('released_to_user_id', Auth::id())->update(['recipient_acknowledged' => true,
                    'recipient_acknowledged_at' => now(), 'updated_at' => now()]);
        });
    }

    public function reviewEmergency(\App\Models\Session $session, string $notes): void
    {
        $pseudo = $session->seeker->pseudo_id;
        $allowed = Auth::user()?->role === 'adviser' && Auth::user()?->adviser?->id !== null
            && $session->emergencyAlerts()->where('adviser_id', Auth::user()->adviser->id)->exists();
        $this->perform($pseudo, 'emergency_review', $allowed, function () use ($session, $notes) {
            DB::connection('identity_vault')->table('idv_release_records')->where('session_id', $session->id)
                ->where('release_reason', 'life_threatening_emergency')->whereNull('reviewed_at')->update([
                    'reviewed_by' => Auth::id(), 'reviewed_at' => now(), 'review_notes' => $this->cipher()->encryptString($notes), 'updated_at' => now(),
                ]);
        });
    }

    /** Verified import for the explicit CLI migration; never used by web requests. */
    public function importLegacy(\App\Models\HelpSeeker $seeker, array $data): void
    {
        $this->perform($seeker->pseudo_id, 'legacy_import', app()->runningInConsole() && ! Auth::check(), function () use ($seeker, $data) {
            $db = DB::connection('identity_vault');
            $existing = $db->table('idv_identities')->where('pseudo_id', $seeker->pseudo_id)->lockForUpdate()->first();
            $values = [];
            foreach (self::FIELDS as $field) {
                if (isset($data[$field]) && $data[$field] !== '') {
                    // A newer identity must never be overwritten by legacy data.
                    if ($existing && $existing->$field !== null) {
                        abort_unless(hash_equals((string) $data[$field], $this->cipher()->decryptString($existing->$field)), 409);
                    } else {
                        $values[$field] = $this->cipher()->encryptString((string) $data[$field]);
                    }
                }
            }
            if ($existing) {
                abort_unless($existing->is_active && \Illuminate\Support\Carbon::parse($existing->data_expires_at)->isFuture(), 409);
                if ($values) $db->table('idv_identities')->where('identity_id', $existing->identity_id)->update($values + ['updated_at' => now()]);
            } else {
                $db->table('idv_identities')->insert($values + [
                    'pseudo_id' => $seeker->pseudo_id, 'seeker_alias' => $seeker->generated_alias,
                    'data_expires_at' => now()->addDays(config('identity_vault.retention_days')),
                    'created_by' => 'legacy_migration', 'updated_by' => 'legacy_migration', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $stored = $db->table('idv_identities')->where('pseudo_id', $seeker->pseudo_id)->first();
            foreach (self::FIELDS as $field) {
                if (isset($data[$field]) && $data[$field] !== '') {
                    abort_unless(hash_equals((string) $data[$field], $this->cipher()->decryptString($stored->$field)), 409);
                }
            }
        });
    }
}
