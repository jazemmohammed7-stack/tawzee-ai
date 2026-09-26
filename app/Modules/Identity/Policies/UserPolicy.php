<?php

declare(strict_types=1);

namespace App\Modules\Identity\Policies;

use App\Models\User;
use App\Modules\Access\Enums\Permission;
use App\Modules\Access\Policies\TenantPolicy;
use App\Modules\Company\Models\Company;
use App\Modules\Identity\Support\CompanyUsers;
use App\Modules\Identity\Support\UserProtection;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Auth\Access\AuthorizationException;

final class UserPolicy extends TenantPolicy
{
    public function viewAny(User $actor): bool
    {
        try {
            $fresh = CompanyUsers::query()->find($actor->getRawOriginal('id'));

            return $fresh?->is_active && $this->allows($fresh, $fresh, Permission::UsersManage);
        } catch (AuthorizationException) {
            return false;
        }
    }

    public function create(User $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function update(User $actor, User $target): bool
    {
        return $this->viewAny($actor) && $this->allows($actor, $target, Permission::UsersManage);
    }

    public function changeRole(User $actor, User $target): bool
    {
        return $this->update($actor, $target)
            && ($actor->is($target) || ! $this->protectedOwner($target));
    }

    public function changeStatus(User $actor, User $target, bool $active): bool
    {
        return $this->update($actor, $target)
            && ($active || $actor->is($target) || ! $this->protectedOwner($target));
    }

    private function protectedOwner(User $target): bool
    {
        // Role names express only the explicit owner protection rule, never grant permissions.
        return UserProtection::owner($target, Company::findOrFail(app(CurrentCompany::class)->id()));
    }
}
