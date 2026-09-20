<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Modules\Company\Models\Company;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    public function newEloquentBuilder($query): CompanyBuilder
    {
        return new CompanyBuilder($query);
    }

    // Laravel uses this for refresh/fresh and queued model restoration as well.
    public function newQueryWithoutScopes()
    {
        return parent::newQueryWithoutScopes()->withGlobalScope(CompanyScope::class, new CompanyScope);
    }

    public function save(array $options = [])
    {
        $this->assertCompanyOwnership();
        if (! $this->exists) {
            $this->setAttribute('company_id', app(CurrentCompany::class)->id());
        }

        return parent::save($options);
    }

    public function delete()
    {
        $this->assertCompanyOwnership();

        return parent::delete();
    }

    protected function setKeysForSaveQuery($query)
    {
        $this->assertCompanyOwnership();

        return parent::setKeysForSaveQuery($query)
            ->where($this->qualifyColumn('company_id'), app(CurrentCompany::class)->id());
    }

    public function company(): BelongsTo
    {
        $this->assertCompanyOwnership();

        return $this->belongsTo(Company::class)->whereKey(app(CurrentCompany::class)->id());
    }

    protected function assertCompanyOwnership(): void
    {
        $id = app(CurrentCompany::class)->id();
        $assigned = $this->getAttribute('company_id');
        if (($assigned !== null && (string) $assigned !== (string) $id)
            || ($this->exists && ((string) $this->getRawOriginal('company_id') !== (string) $id
                || (string) $assigned !== (string) $this->getRawOriginal('company_id')))) {
            throw new AuthorizationException('Cross-company writes and reassignment are forbidden.');
        }
    }
}
