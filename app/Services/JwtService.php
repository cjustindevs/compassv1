<?php

namespace App\Services;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str;

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

    public function issue(User $user): string
    {
        return JWT::encode([
            'iss' => config('app.url'), 'aud' => 'compass-api', 'sub' => (string) $user->id,
            'iat' => time(), 'nbf' => time(), 'exp' => time() + 3600, 'jti' => (string) Str::uuid(),
        ], $this->key(), 'HS256');
    }

    public function user(string $token): ?User
    {
        $claims = JWT::decode($token, new Key($this->key(), 'HS256'));
        if (($claims->iss ?? null) !== config('app.url') || ($claims->aud ?? null) !== 'compass-api') {
            return null;
        }
        return User::where('is_active', true)->find($claims->sub ?? null);
    }
}
