<?php

namespace App\Http\Middleware;

use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticateJwt
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $user = app(JwtService::class)->user($request->bearerToken() ?? '');
        } catch (\UnexpectedValueException|\DomainException|\InvalidArgumentException $exception) {
            $user = null;
        }
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);
        return $next($request);
    }
}
