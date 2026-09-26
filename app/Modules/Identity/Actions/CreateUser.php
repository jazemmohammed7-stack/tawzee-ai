<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Support\CompanyUsers;
use App\Modules\Identity\Support\UserInput;
use App\Modules\Identity\Support\UserRoleAssignment;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class CreateUser
{
    public function handle(array $input): User
    {
        try {
            return DB::transaction(function () use ($input): User {
                CompanyUsers::lockCompany();
                $actor = CompanyUsers::actor();
                Gate::forUser($actor)->authorize('create', User::class);
                $data = UserInput::validate($input);
                $user = new User(collect($data)->only(['name', 'email', 'password'])->all());
                $user->company_id = app(CurrentCompany::class)->id();
                $user->is_active = true;
                if (! $user->save()) {
                    throw new RuntimeException('User persistence was cancelled.');
                }
                UserRoleAssignment::apply($actor, $user, (int) $data['role_id'], creating: true);

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
