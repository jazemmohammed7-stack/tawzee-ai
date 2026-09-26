<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Models\User;
use App\Modules\Access\Actions\RecordActivity;
use App\Modules\Access\Models\Role;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

final class UserRoleAssignment
{
    public static function apply(User $actor, User $target, int $roleId, bool $creating = false): void
    {
        $role = Role::findOrFail($roleId);
        $before = $target->roles()->pluck('roles.id')->map(fn ($id): int => (int) $id)->sort()->values()->all();
        if ($before === [$roleId]) {
            return;
        }
        if (! $creating) {
            Gate::forUser($actor)->authorize('changeRole', $target);
        }
        $target->syncRoles($role);
        $log = app(RecordActivity::class)->record('user.role_changed', $target, [
            'previous_role_ids' => $before, 'role_id' => $roleId,
        ]);
        if (! $log->exists) {
            throw new RuntimeException('Role activity persistence was cancelled.');
        }
    }
}
