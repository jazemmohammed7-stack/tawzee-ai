<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Support\CompanyUsers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

final class ChangeUserStatus
{
    public function handle(int $id, bool $active): User
    {
        return DB::transaction(function () use ($id, $active): User {
            CompanyUsers::lockCompany();
            $actor = CompanyUsers::actor();
            Gate::forUser($actor)->authorize('viewAny', User::class);
            $user = CompanyUsers::query()->lockForUpdate()->findOrFail($id);
            Gate::forUser($actor)->authorize('changeStatus', [$user, $active]);
            $user->is_active = $active;
            if (! $user->save()) {
                throw new RuntimeException('User persistence was cancelled.');
            }

            return $user;
        });
    }
}
