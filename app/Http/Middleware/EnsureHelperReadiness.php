<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHelperReadiness
{
    /**
     * Gate a helper who has never submitted a readiness check. They are sent
     * to the readiness screen before they can use the rest of the module.
     * Once a readiness check exists (regardless of result) the gate opens.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $helper = $request->user()?->helper;

        if ($helper && ! $helper->latestReadiness) {
            if ($request->routeIs(
                'helper.readiness',
                'helper.readiness.store',
                'helper.readiness.history',
                'helper.onboarding',
                'helper.onboarding.store'
            )) {
                return $next($request);
            }

            return redirect()->route('helper.readiness')
                ->with('info', 'Please complete a quick readiness check before taking sessions.');
        }

        return $next($request);
    }
}
