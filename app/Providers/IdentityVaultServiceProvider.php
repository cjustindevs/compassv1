<?php

namespace App\Providers;

use App\Services\IdentityVaultService;
use Illuminate\Support\ServiceProvider;

class IdentityVaultServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IdentityVaultService::class);
        $this->app->alias(IdentityVaultService::class, 'identity.vault');
    }
}
