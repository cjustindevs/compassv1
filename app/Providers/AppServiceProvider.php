<?php

namespace App\Providers;

use App\Http\ViewComposers\SidebarComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Validation\Rules\Password::defaults(fn () => \Illuminate\Validation\Rules\Password::min(8)->mixedCase()->numbers()->symbols());
        $this->registerViewComposers();
        \Illuminate\Support\Facades\RateLimiter::for('registration-actions', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(30)
                ->by($request->path().':'.$request->ip())
                ->response(fn ($request, $headers) => response()->json([
                    'message' => 'Please wait a moment before trying this action again.',
                    'retry_after' => (int) ($headers['Retry-After'] ?? 60),
                ], 429, $headers));
        });
        \Illuminate\Support\Facades\RateLimiter::for('seeker-registration', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(10)
                ->by('seeker-registration:'.$request->ip())
                ->response(function ($request, $headers) {
                    $seconds = $headers['Retry-After'] ?? 60;
                    return redirect()->route('register')
                        ->withInput($request->only(['age', 'gender', 'preferred_language']))
                        ->withErrors(['registration' => "Please wait {$seconds} seconds before trying again. Your verification and alias are still saved."]);
                });
        });

        if ($this->app->environment('local')) {
            \Illuminate\Support\Facades\DB::listen(function ($query) {
                if ($query->time > 500) {
                    \Illuminate\Support\Facades\Log::warning('Slow Query', [
                        'sql' => str_replace("\n", ' ', $query->sql),
                        'time' => $query->time . 'ms',
                        'url' => request()->fullUrl(),
                    ]);
                }
            });
        }
    }

    private function registerViewComposers(): void
    {
        View::composer([
            'layouts.partials.helper-sidebar',
            'layouts.partials.adviser-sidebar',
            'layouts.partials.moderator-sidebar',
            'layouts.partials.professional-sidebar',
            'partials.sidebar',
        ], SidebarComposer::class);
    }
}
