<?php

declare(strict_types=1);

namespace App\Modules\Access\Policies;

use App\Models\User;
use App\Modules\Access\Enums\Permission;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

abstract class TenantPolicy
{
    protected function allows(User $user, Model $resource, Permission $permission): bool
    {
        try {
            $companyId = app(CurrentCompany::class)->id();
        } catch (AuthorizationException) {
            return false;
        }

        if (! $user->exists
            || (int) $user->getRawOriginal('company_id') !== $companyId
            || ! $resource->exists
            || $resource->isDirty($resource->getKeyName())
            || $resource->isDirty('company_id')
            || (int) $resource->getRawOriginal('company_id') !== $companyId) {
            return false;
        }

        try {
            return $user->hasPermissionTo($permission->value);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }
}
