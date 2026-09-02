<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class ApplyTheme
{
    /**
     * Share the authenticated user's theme preference with every view so the
     * no-flash snippet (layouts.partials.pwa-meta) can apply it before paint.
     */
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check()) {
            $user = auth()->user();
            $theme = $user->theme_preference ?? ($user->dark_mode ? 'dark' : 'light');

            View::share('currentTheme', $theme);
            View::share('themePrefs', [
                'high_contrast' => (bool) $user->high_contrast,
                'reduced_motion' => (bool) ($user->reduced_motion ?? false),
                'font_size' => $user->font_size ?? 'medium',
            ]);
        }

        return $next($request);
    }
}
