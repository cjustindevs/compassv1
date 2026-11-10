<?php

use App\Http\Middleware\EnsureUserRole;
use App\Http\Middleware\EnsureHelperProfile;
use App\Http\Middleware\EnsureHelperReadiness;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [\App\Http\Middleware\EnsureActiveAccount::class]);
        $middleware->prependToPriorityList(\Illuminate\Routing\Middleware\SubstituteBindings::class, \App\Http\Middleware\EnsureUserRole::class);
        $middleware->alias([
            'role' => EnsureUserRole::class,
            'ensure.helper.profile' => EnsureHelperProfile::class,
            'ensure.helper.readiness' => EnsureHelperReadiness::class,
        ]);

        // Render terminates TLS at its edge and forwards requests over HTTP,
        // so the X-Forwarded-* headers must be trusted to generate https URLs,
        // enforce secure cookies and resolve the client's real IP. This is safe
        // on Render's platform proxy; do not enable it on an untrusted network.
        $middleware->trustProxies(
            at: '*',
            headers: \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR
                   | \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO
                   | \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST
                   | \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(\App\Services\IdentityVaultService::FIELDS);
        // A Seeker who opens a request step before accepting consent is sent to the step that asks
        // for it, instead of an error page. API and form submissions still receive the 409.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() === 409 && $request->isMethod('GET') && ! $request->expectsJson()
                && str_contains($e->getMessage(), 'accept the current consent documents')) {
                return redirect()->route('request.screening')->with('status', 'Please review and accept the Terms and Privacy first to continue your request.');
            }
        });
    })->create();
