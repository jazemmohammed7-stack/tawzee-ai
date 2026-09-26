<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Access\Actions\InitializeCompanyRoles;
use App\Modules\Access\Actions\RecordActivity;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\DocumentSequence;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class RegisterCompany
{
    public function __construct(
        private readonly RecordActivity $activity,
        private readonly InitializeCompanyRoles $roles,
    ) {}

    public function handle(array $input): Company
    {
        if (! Auth::guest()) {
            throw new AuthorizationException;
        }

        $allowed = ['company_name', 'name', 'email', 'password', 'password_confirmation'];
        if (array_diff(array_keys($input), $allowed)) {
            throw ValidationException::withMessages(['registration' => __('registration.invalid_fields')]);
        }
        foreach (['company_name', 'name', 'email'] as $field) {
            if (isset($input[$field]) && is_string($input[$field])) {
                $input[$field] = trim($input[$field]);
            }
        }
        if (isset($input['email']) && is_string($input['email'])) {
            $input['email'] = mb_strtolower($input['email']);
        }

        $data = Validator::make($input, [
            'company_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'confirmed', function ($attribute, $value, $fail): void {
                // Bcrypt must not silently truncate a multibyte password.
                if (is_string($value) && (strlen($value) > 72 || str_contains($value, "\0"))) {
                    $fail(__('registration.password_length'));
                }
            }],
            'password_confirmation' => ['required', 'string', 'max:72'],
        ], __('registration.validation'), __('registration.attributes'))->validate();

        try {
            return DB::transaction(function () use ($data): Company {
                $company = new Company(['name' => $data['company_name']]);
                $company->status = 'pending_setup';
                if (! $company->save()) {
                    throw new RuntimeException('Registration persistence was cancelled.');
                }

                // Auth/bootstrap exception only: the client never selects this company.
                $user = $company->users()->create([
                    'name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'],
                ]);
                if (! $user->exists) {
                    throw new RuntimeException('Registration persistence was cancelled.');
                }
                $company->founder_user_id = $user->id;
                if (! $company->save()) {
                    throw new RuntimeException('Registration persistence was cancelled.');
                }

                app(CurrentCompany::class)->run($company, function () use ($company): void {
                    foreach (DocumentSequence::INITIAL_TYPES as $type) {
                        if (! $company->documentSequences()->create(['type' => $type])->exists) {
                            throw new RuntimeException('Registration persistence was cancelled.');
                        }
                    }
                    $this->roles->handle($company);
                    $this->activity->record('company.registered', $company, [
                        'status' => $company->status,
                    ]);
                });

                return $company;
            });
        } catch (UniqueConstraintViolationException $exception) {
            // Translate only the known global email constraint, after transaction rollback.
            if (str_contains((string) ($exception->errorInfo[2] ?? ''), 'users_email_unique')) {
                throw ValidationException::withMessages(['email' => __('registration.validation.unique')]);
            }
            throw $exception;
        }
    }
}
