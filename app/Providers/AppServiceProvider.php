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
