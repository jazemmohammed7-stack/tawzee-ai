<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Models\User;
use App\Modules\Access\Enums\DefaultRole;
use App\Modules\Company\Models\Company;

final class UserProtection
{
    public static function owner(User $user, Company $company): bool
    {
        return (int) $company->founder_user_id === (int) $user->id
            || $user->roles->contains('name', DefaultRole::Owner->value);
    }
}
