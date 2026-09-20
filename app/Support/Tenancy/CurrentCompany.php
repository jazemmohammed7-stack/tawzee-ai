<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Models\User;
use App\Modules\Company\Models\Company;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Factory;

final class CurrentCompany
{
    private ?int $internalId = null;

    private bool $httpManaged = false;

    public function __construct(private readonly Factory $auth) {}

    public function id(): int
    {
        if ($this->httpManaged && $this->internalId === null) {
            throw new AuthorizationException('No company context is active for this HTTP lifecycle.');
        }
        $authenticatedId = $this->authenticatedId();
        if ($this->internalId !== null && $authenticatedId !== null && $this->internalId !== $authenticatedId) {
            throw new AuthorizationException('Company context conflicts with the authenticated user.');
        }

        return $this->internalId ?? $authenticatedId
            ?? throw new AuthorizationException('A trusted company context is required.');
    }

    /** Outer real HTTP request only, not Livewire's replayed middleware pipeline. */
    public function forRequest(Closure $callback): mixed
    {
        $this->httpManaged = true;
        $this->internalId = null;
        try {
            return $callback();
        } finally {
            $this->internalId = null;
        }
    }

    /** Internal, synchronous job/CLI/bootstrap work only; never pass request-selected companies. */
    public function run(Company $company, Closure $callback): mixed
    {
        if (! $company->exists || ! $company->getRawOriginal('id') || $company->isDirty('id')) {
            throw new AuthorizationException('An existing trusted company is required.');
        }

        $previous = $this->internalId;
        $this->internalId = (int) $company->getRawOriginal('id');
        try {
            $this->id();

            return $callback();
        } finally {
            $this->internalId = $previous;
        }
    }

    private function authenticatedId(): ?int
    {
        $user = $this->auth->guard()->user();
        if ($user === null) {
            return null;
        }
        if (! $user instanceof User || ! $user->exists || ! $user->getRawOriginal('company_id')) {
            throw new AuthorizationException('The authenticated identity has no trusted company.');
        }

        // Ignore unsaved changes to company_id and never cache an authenticated identity.
        return (int) $user->getRawOriginal('company_id');
    }
}
