<?php

declare(strict_types=1);

namespace App\Modules\Access\Database;

use App\Support\Tenancy\CompanyBuilder;
use LogicException;

final class ActivityLogBuilder extends CompanyBuilder
{
    public function update(array $values)
    {
        throw new LogicException('Activity logs are append-only.');
    }

    public function delete()
    {
        throw new LogicException('Activity logs are append-only.');
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        throw new LogicException('Activity logs are append-only.');
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        throw new LogicException('Activity logs are append-only.');
    }

    public function touch($column = null)
    {
        throw new LogicException('Activity logs are append-only.');
    }

    public function __call($method, $parameters)
    {
        if (in_array(strtolower($method), [
            'updateorinsert', 'truncate', 'incrementeach', 'decrementeach',
        ], true)) {
            throw new LogicException('Activity logs are append-only.');
        }

        return parent::__call($method, $parameters);
    }
}
