<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Contracts\PermissionsTeamResolver;

final class CurrentCompanyPermissionTeamResolver implements PermissionsTeamResolver
{
    public function getPermissionsTeamId(): int
    {
        return app(CurrentCompany::class)->id();
    }

    public function setPermissionsTeamId($id): void
    {
        $requested = $id instanceof Model ? $id->getKey() : $id;
        $trusted = $this->getPermissionsTeamId();

        if ((string) $requested !== (string) $trusted) {
            throw new AuthorizationException('Permission team must match the trusted company context.');
        }
    }
}
