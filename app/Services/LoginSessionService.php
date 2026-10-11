<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoginSessionService
{
    public const DEVICE_MESSAGE = 'This account is already signed in on another device. Sign out there first, or try again in a few minutes.';

    /** Reserve a login before the response, using the existing five-minute idle rule. */
    public function reserve(User $user, string $sessionId, ?Request $request = null): bool
    {
        return DB::transaction(function () use ($user, $sessionId, $request) {
            $current = User::whereKey($user->id)->lockForUpdate()->first();
            if (! $current?->is_active || $current->password !== $user->password) {
                return false;
            }

            DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $sessionId)
                ->where('last_activity', '<', now()->subMinutes(5)->timestamp)->delete();
            if (DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $sessionId)->exists()) {
                return false;
            }

            DB::table('sessions')->updateOrInsert(['id' => $sessionId], [
                'user_id' => $user->id, 'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(), 'payload' => '', 'last_activity' => now()->timestamp,
            ]);

            return true;
        }, 3);
    }

    public function revoke(User $user, ?string $webSessionId = null): void
    {
        DB::transaction(function () use ($user, $webSessionId) {
            User::whereKey($user->id)->lockForUpdate()->first();
            DB::table('sessions')->where('user_id', $user->id)->where(function ($query) use ($webSessionId) {
                $query->where('id', 'like', 'api-%');
                if ($webSessionId !== null) {
                    $query->orWhere('id', $webSessionId);
                }
            })->delete();
        }, 3);
    }
}
