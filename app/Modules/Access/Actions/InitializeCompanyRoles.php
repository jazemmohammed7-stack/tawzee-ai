<?php

declare(strict_types=1);

namespace App\Modules\Access\Actions;

use App\Models\User;
use App\Modules\Access\Enums\DefaultRole;
use App\Modules\Access\Enums\Permission;
use App\Modules\Access\Models\Role;
use App\Modules\Company\Models\Company;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\PermissionRegistrar;

final class InitializeCompanyRoles
{
    public function handle(Company $company): void
    {
        app(CurrentCompany::class)->run($company, function () use ($company): void {
            DB::transaction(function () use ($company): void {
                $permissions = collect(Permission::cases())->mapWithKeys(
                    fn (Permission $permission): array => [
                        $permission->value => PermissionModel::findOrCreate($permission->value, 'web'),
                    ],
                );

                foreach (DefaultRole::cases() as $defaultRole) {
                    $role = Role::firstOrCreate([
                        'name' => $defaultRole->value,
                        'guard_name' => 'web',
                    ]);
                    $role->syncPermissions(array_map(
                        fn (Permission $permission) => $permissions->get($permission->value),
                        $defaultRole->permissions(),
                    ));
                }

                $founderId = $company->getRawOriginal('founder_user_id');
                if ($founderId !== null) {
                    $founder = User::query()
                        ->whereKey($founderId)
                        ->where('company_id', $company->getRawOriginal('id'))
                        ->first();
                    if (! $founder) {
                        throw new AuthorizationException('The company founder is not trusted.');
                    }
                    $founder->syncRoles(DefaultRole::Owner->value);
                }

                app(PermissionRegistrar::class)->forgetCachedPermissions();
            });
        });
    }
}
