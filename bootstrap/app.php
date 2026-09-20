<?php

use App\Http\Middleware\EnsureCompanyReady;
use App\Http\Middleware\RequireCompany;
use App\Http\Middleware\SetCurrentCompany;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetCurrentCompany::class,
            AuthenticateSession::class,
        ]);
        $middleware->alias([
            'tenant' => RequireCompany::class,
            'company.ready' => EnsureCompanyReady::class,
        ]);
        $middleware->appendToPriorityList(StartSession::class, SetCurrentCompany::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, RequireCompany::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureCompanyReady::class);
        $middleware->redirectUsersTo(fn () => route('setup.pending'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
