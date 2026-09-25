<?php

declare(strict_types=1);

namespace App\Modules\Access\Models;

use App\Models\User;
use App\Modules\Access\Database\ActivityLogBuilder;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

final class ActivityLog extends Model
{
    use BelongsToCompany {
        save as private saveForCompany;
    }

    protected $guarded = ['id', 'company_id', 'user_id'];

    protected function casts(): array
    {
        return ['properties' => 'array'];
    }

    public function newEloquentBuilder($query): ActivityLogBuilder
    {
        return new ActivityLogBuilder($query);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function save(array $options = [])
    {
        if ($this->exists) {
            throw new LogicException('Activity logs are append-only.');
        }

        return $this->saveForCompany($options);
    }

    public function update(array $attributes = [], array $options = [])
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
}
