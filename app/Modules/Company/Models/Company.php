<?php

declare(strict_types=1);

namespace App\Modules\Company\Models;

use App\Models\User;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    protected $fillable = ['name'];

    protected function casts(): array
    {
        return ['allow_negative_stock' => 'boolean'];
    }

    protected static function newFactory(): CompanyFactory
    {
        return CompanyFactory::new();
    }

    public function founder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'founder_user_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function documentSequences(): HasMany
    {
        return $this->hasMany(DocumentSequence::class);
    }
}
