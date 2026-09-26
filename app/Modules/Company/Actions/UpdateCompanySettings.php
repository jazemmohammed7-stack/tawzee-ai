<?php

declare(strict_types=1);

namespace App\Modules\Company\Actions;

use App\Modules\Access\Actions\RecordActivity;
use App\Modules\Company\Models\Company;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class UpdateCompanySettings
{
    public function handle(array $input): Company
    {
        return DB::transaction(function () use ($input): Company {
            $company = Company::whereKey(app(CurrentCompany::class)->id())->lockForUpdate()->firstOrFail();
            Gate::authorize('updateSettings', $company);
            if (array_diff(array_keys($input), ['name', 'allow_negative_stock'])) {
                throw ValidationException::withMessages(['settings' => __('company_settings.invalid_fields')]);
            }
            if (is_string($input['name'] ?? null)) {
                $input['name'] = trim($input['name']);
            }
            $data = Validator::make($input, [
                'name' => ['required', 'string', 'max:255'],
                'allow_negative_stock' => ['required', 'boolean'],
            ], __('company_settings.validation'), __('company_settings.attributes'))->validate();
            $data['allow_negative_stock'] = (bool) $data['allow_negative_stock'];
            $changes = [];
            foreach ($data as $field => $value) {
                if ($company->getAttribute($field) !== $value) {
                    $changes[$field] = ['before' => $company->getAttribute($field), 'after' => $value];
                    $company->setAttribute($field, $value);
                }
            }
            if ($changes === []) {
                return $company;
            }
            if (! $company->save()) {
                throw new RuntimeException('Company settings persistence was cancelled.');
            }
            $log = app(RecordActivity::class)->record('company.settings.updated', $company, ['changes' => $changes]);
            if (! $log->exists) {
                throw new RuntimeException('Company settings activity persistence was cancelled.');
            }

            return $company;
        });
    }
}
