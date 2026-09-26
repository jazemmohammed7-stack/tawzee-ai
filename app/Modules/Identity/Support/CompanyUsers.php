<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Models\User;
use App\Modules\Company\Models\Company;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

final class CompanyUsers
{
    public static function query(): Builder
    {
        // User remains unscoped for authentication; management always uses this explicit boundary.
        return User::query()->where('users.company_id', app(CurrentCompany::class)->id());
    }

    public static function actor(): User
    {
        $actor = self::query()->find(Auth::id());
        abort_unless($actor?->is_active, 403);

        return $actor;
    }

    public static function lockCompany(): void
    {
        // Serialize management writes, including concurrent actor role/status changes.
        Company::whereKey(app(CurrentCompany::class)->id())->lockForUpdate()->firstOrFail();
    }
}
