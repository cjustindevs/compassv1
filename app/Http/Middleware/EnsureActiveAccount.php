<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class EnsureActiveAccount {
    public function handle(Request $request, Closure $next) {
        abort_if($request->user() && !$request->user()->is_active,403,'This account is inactive.');
        return $next($request);
    }
}
