<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\EnsureCompanyReady;
use App\Http\Middleware\RequireCompany;
use App\Modules\Access\Policies\DocumentSequencePolicy;
use App\Modules\Company\Models\DocumentSequence;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Gate;
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
        // Manual because the policy and model live in separate bounded modules.
        Gate::policy(DocumentSequence::class, DocumentSequencePolicy::class);

        Livewire::addPersistentMiddleware([
            RequireCompany::class,
            EnsureCompanyReady::class,
            AuthenticateSession::class,
            RedirectIfAuthenticated::class,
        ]);
    }
}
