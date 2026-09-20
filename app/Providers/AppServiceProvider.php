<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\EnsureCompanyReady;
use App\Http\Middleware\RequireCompany;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentCompany::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Livewire::addPersistentMiddleware([
            RequireCompany::class,
            EnsureCompanyReady::class,
            AuthenticateSession::class,
            RedirectIfAuthenticated::class,
        ]);
    }
}
