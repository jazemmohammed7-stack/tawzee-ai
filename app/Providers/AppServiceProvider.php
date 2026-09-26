<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\EnsureCompanyReady;
use App\Http\Middleware\RequireCompany;
use App\Models\User;
use App\Modules\Access\Policies\DocumentSequencePolicy;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\DocumentSequence;
use App\Modules\Company\Policies\CompanyPolicy;
use App\Modules\Identity\Policies\UserPolicy;
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
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Company::class, CompanyPolicy::class);

        Livewire::addPersistentMiddleware([
            RequireCompany::class,
            EnsureCompanyReady::class,
            AuthenticateSession::class,
            RedirectIfAuthenticated::class,
        ]);
    }
}
