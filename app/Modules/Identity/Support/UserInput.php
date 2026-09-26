<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Modules\Access\Enums\DefaultRole;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class UserInput
{
    public static function validate(array $input, ?int $id = null): array
    {
        $allowed = ['name', 'email', 'role_id'];
        if ($id === null) {
            $allowed = [...$allowed, 'password', 'password_confirmation'];
        }
        if (array_diff(array_keys($input), $allowed)) {
            throw ValidationException::withMessages(['form' => __('users.invalid_fields')]);
        }
        if (is_string($input['email'] ?? null)) {
            $input['email'] = AuthenticationInput::email($input['email']);
        }
        if (is_string($input['name'] ?? null)) {
            $input['name'] = trim($input['name']);
        }
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            // The schema deliberately defines global email uniqueness for authentication.
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')
                ->where('company_id', app(CurrentCompany::class)->id())
                ->where('guard_name', 'web')->whereIn('name', DefaultRole::values())],
        ];
        if ($id === null) {
            $rules['password'] = AuthenticationInput::newPasswordRules();
            $rules['password_confirmation'] = ['required', 'string', 'max:72'];
        }

        return Validator::make($input, $rules, __('users.validation'), __('users.attributes'))->validate();
    }
}
