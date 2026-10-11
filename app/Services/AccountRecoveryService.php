<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AccountRecoveryService
{
    public function normalize(string $email): string
    {
        return strtolower(trim($email));
    }

    public function emailHash(string $email): string
    {
        return hash_hmac('sha256', 'compass-recovery-email:'.$this->normalize($email), app('encrypter')->getKey());
    }

    public function ensureAvailable(string $email, ?int $exceptUserId = null): void
    {
        $used = User::where(function ($query) use ($email) {
            $query->where('recovery_email_hash', $this->emailHash($email))
                ->orWhereRaw('LOWER(email) = ?', [$this->normalize($email)]);
        })->when($exceptUserId, fn ($query) => $query->where('id', '!=', $exceptUserId))->exists();
        if ($used) {
            throw ValidationException::withMessages(['email' => 'This verified email already belongs to an account. Use its password-reset option or verify another address.']);
        }
    }

    public function bind(User $user, string $email, string $passwordFingerprint): void
    {
        try {
            DB::transaction(function () use ($user, $email, $passwordFingerprint) {
                $current = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                abort_unless($current->is_active && $current->role === 'seeker', 403);
                if (! hash_equals($passwordFingerprint, hash('sha256', $current->password))) {
                    throw ValidationException::withMessages(['otp' => 'Your password changed. Start email verification again.']);
                }
                $this->ensureAvailable($email, $current->id);
                $current->forceFill([
                    'recovery_email' => $this->normalize($email),
                    'recovery_email_hash' => $this->emailHash($email),
                ])->save();
                Password::deleteToken($current);
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['email' => 'This email was linked to another account. Verify another address.']);
        }
    }

    public function signInAddress(string $email): string
    {
        $email = $this->normalize($email);
        return User::where('role', 'seeker')->where('recovery_email_hash', $this->emailHash($email))->value('email')
            ?? User::whereRaw('LOWER(email) = ?', [$email])->value('email')
            ?? $email;
    }
}
