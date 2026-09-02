<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHelperProfile
{
    /**
     * Every helper user must own a row in the `helpers` table before they can
     * use the module. Profiles are not created during registration, so a user
     * with role "helper" but no helper record would crash every controller
     * that dereferences Auth::user()->helper->id.
     *
     * Instead of silently creating one, we send the user to an onboarding form
     * where they fill in their real details. The onboarding routes are exempt
     * so the profile can actually be created.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->helper) {
            if ($request->routeIs('helper.onboarding', 'helper.onboarding.store')) {
                return $next($request);
            }

            return redirect()->route('helper.onboarding');
        }

        return $next($request);
    }
}
