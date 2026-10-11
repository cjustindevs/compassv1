<?php

namespace App\Services;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JwtService
{
    private function key(): string
    {
        $key = (string) config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7), true) ?: '';
        }
        if (strlen($key) < 32) {
            throw new \RuntimeException('A valid application key is required.');
        }
        return hash_hmac('sha256', 'compass-api-jwt', $key, true);
    }

    public function issue(User $user, ?Request $request = null): string
    {
        $id = (string) Str::uuid();
        // Build the token before reserving a session, so configuration errors cannot leave a login behind.
        $token = JWT::encode([
            'iss' => config('app.url'), 'aud' => 'compass-api', 'sub' => (string) $user->id,
            'iat' => time(), 'nbf' => time(), 'exp' => time() + 3600, 'jti' => $id,
            'credentials' => $this->credentialFingerprint($user),
        ], $this->key(), 'HS256');
        abort_unless(app(LoginSessionService::class)->reserve($user, 'api-'.$id, $request), 409, LoginSessionService::DEVICE_MESSAGE);

        return $token;
    }

    public function user(string $token): ?User
    {
        $claims = JWT::decode($token, new Key($this->key(), 'HS256'));
        if (($claims->iss ?? null) !== config('app.url') || ($claims->aud ?? null) !== 'compass-api') {
            return null;
        }
        if (! is_string($claims->jti ?? null) || ! Str::isUuid($claims->jti) || ! is_string($claims->credentials ?? null)) {
            return null;
        }

        return DB::transaction(function () use ($claims) {
            $user = User::where('is_active', true)->whereKey($claims->sub ?? null)->lockForUpdate()->first();
            if (! $user || ! hash_equals($this->credentialFingerprint($user), $claims->credentials)) {
                return null;
            }
            $session = DB::table('sessions')->where('user_id', $user->id)->where('id', 'api-'.$claims->jti);
            if (! $session->exists()) {
                return null;
            }
            $session->update(['last_activity' => now()->timestamp]);

            return $user;
        }, 3);
    }

    private function credentialFingerprint(User $user): string
    {
        return hash_hmac('sha256', $user->password, $this->key());
    }
}
