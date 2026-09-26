<?php

declare(strict_types=1);

namespace App\Modules\Company\Policies;

use App\Models\User;
use App\Modules\Access\Enums\Permission;
use App\Modules\Access\Policies\TenantPolicy;
use App\Modules\Company\Models\Company;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Auth\Access\AuthorizationException;

final class CompanyPolicy extends TenantPolicy
{
    public function viewSettings(User $user): bool
    {
        try {
            $companyId = app(CurrentCompany::class)->id();
        } catch (AuthorizationException) {
            return false;
        }
        // Authentication identities are unscoped; re-read within the trusted company boundary.
        $actor = User::where('company_id', $companyId)->find($user->getRawOriginal('id'));

        return $actor?->is_active && $this->allows($actor, $actor, Permission::CompanySettings);
    }

    public function updateSettings(User $user, Company $company): bool
    {
        // Company is the tenant root, so its primary key is the ownership boundary.
        return $this->viewSettings($user)
            && $company->exists && ! $company->isDirty($company->getKeyName())
            && (int) $company->getRawOriginal('id') === app(CurrentCompany::class)->id();
    }
}
