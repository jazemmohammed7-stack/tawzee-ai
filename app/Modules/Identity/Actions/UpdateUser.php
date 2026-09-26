<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Support\CompanyUsers;
use App\Modules\Identity\Support\UserInput;
use App\Modules\Identity\Support\UserRoleAssignment;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class UpdateUser
{
    public function handle(int $id, array $input): User
    {
        try {
            return DB::transaction(function () use ($id, $input): User {
                CompanyUsers::lockCompany();
                $actor = CompanyUsers::actor();
                Gate::forUser($actor)->authorize('viewAny', User::class);
                $user = CompanyUsers::query()->lockForUpdate()->findOrFail($id);
                Gate::forUser($actor)->authorize('update', $user);
                $data = UserInput::validate($input, (int) $user->id);
                UserRoleAssignment::apply($actor, $user, (int) $data['role_id']);
                $user->fill(collect($data)->only(['name', 'email'])->all());
                if ($user->isDirty('email')) {
                    $user->email_verified_at = null;
                }
                if (! $user->save()) {
                    throw new RuntimeException('User persistence was cancelled.');
                }

                return $user;
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (str_contains((string) ($exception->errorInfo[2] ?? ''), 'users_email_unique')) {
                throw ValidationException::withMessages(['email' => __('users.validation.unique')]);
            }
            throw $exception;
        }
    }
}
