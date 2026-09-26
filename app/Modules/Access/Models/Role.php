<?php

declare(strict_types=1);

namespace App\Modules\Access\Models;

use App\Support\Tenancy\BelongsToCompany;
use Spatie\Permission\Models\Role as SpatieRole;

final class Role extends SpatieRole
{
    use BelongsToCompany;
}
