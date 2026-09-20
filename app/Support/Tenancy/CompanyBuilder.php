<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

/** Bulk operations skip model events, so ownership must also be protected here. */
class CompanyBuilder extends Builder
{
    public function withoutGlobalScope($scope)
    {
        if ($scope === CompanyScope::class || $scope instanceof CompanyScope) {
            throw new AuthorizationException('The company scope cannot be removed.');
        }

        return parent::withoutGlobalScope($scope);
    }

    public function update(array $values)
    {
        $this->protectOwnership(array_keys($values));

        return parent::update($values);
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        $this->protectOwnership([$column, ...array_keys($extra)]);

        return parent::increment($column, $amount, $extra);
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        $this->protectOwnership([$column, ...array_keys($extra)]);

        return parent::decrement($column, $amount, $extra);
    }

    public function touch($column = null)
    {
        $this->protectOwnership((array) ($column ?? $this->model->getUpdatedAtColumn()));

        return parent::touch($column);
    }

    public function forceDelete()
    {
        // Eloquent's default forceDelete bypasses scopes.
        return $this->delete();
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        throw new AuthorizationException('Use scoped model saves; upsert can target another company through a unique key.');
    }

    public function __call($method, $parameters)
    {
        $name = strtolower($method);
        if (in_array($name, ['updateorinsert', 'insertusing', 'insertorignoreusing', 'truncate', 'incrementeach', 'decrementeach'], true)) {
            throw new AuthorizationException('This raw bulk operation is not available on tenant models.');
        }
        if (in_array($name, ['insert', 'insertgetid', 'insertorignore'], true)) {
            $id = app(CurrentCompany::class)->id();
            $values = $parameters[0];
            $multiple = isset($values[0]) && is_array($values[0]);
            $rows = $multiple ? $values : [$values];
            foreach ($rows as &$row) {
                if (isset($row['company_id']) && (string) $row['company_id'] !== (string) $id) {
                    throw new AuthorizationException('Cross-company insert is forbidden.');
                }
                $row['company_id'] = $id;
            }
            unset($row);
            $parameters[0] = $multiple ? $rows : $rows[0];
        }

        return parent::__call($method, $parameters);
    }

    private function protectOwnership(array $columns): void
    {
        foreach ($columns as $column) {
            if (! is_string($column) || last(explode('.', $column)) === 'company_id') {
                throw new AuthorizationException('Company ownership cannot be changed.');
            }
        }
    }
}
