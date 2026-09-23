<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || !$user->is_active || ! in_array($user->role, $roles, true)) {
            abort(403, 'You do not have permission to access this page.');
        }

        if ($user->role === 'adviser') {
            app(\App\Services\AdviserScope::class)->actor($user);
        }

        $response = $next($request);
        if ($user->role === 'adviser') {
            $response->headers->set('Cache-Control', 'private, no-store');
        }
        return $response;
    }
}