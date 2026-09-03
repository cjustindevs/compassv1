<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHelperReadiness
{
    /**
     * Gate a helper whose readiness is missing or expired. Readiness is valid
     * only for the current duty period, represented by the latest check's
     * valid_until timestamp (or a 4-hour fallback for older records).
     *
     * Readiness check / availability / onboarding / self-help routes live in a
     * separate, ungated group and are intentionally NOT redirected here so a
     * helper who isn't ready can still reach self-help tools.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $helper = $request->user()?->helper;

        if ($helper && ! $helper->latestReadiness?->isReady()) {
            return redirect()->route('helper.readiness')
                ->with('info', 'Please complete a current readiness check before taking sessions.');
        }

        return $next($request);
    }
}
