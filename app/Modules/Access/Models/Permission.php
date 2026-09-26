<?php

declare(strict_types=1);

namespace App\Modules\Access\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as UnscopedRole;
use Spatie\Permission\PermissionRegistrar;

final class Permission extends SpatiePermission
{
    /**
     * Spatie's shared permission cache must contain roles from every team.
     * Application role queries still use the company-scoped Role model.
     */
    public function roles(): BelongsToMany
    {
        $registrar = app(PermissionRegistrar::class);

        return $this->belongsToMany(
            UnscopedRole::class,
            config('permission.table_names.role_has_permissions'),
            $registrar->pivotPermission,
            $registrar->pivotRole,
        );
    }
}
