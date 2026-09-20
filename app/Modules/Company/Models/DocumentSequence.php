<?php

declare(strict_types=1);

namespace App\Modules\Company\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class DocumentSequence extends Model
{
    use BelongsToCompany;

    public const INITIAL_TYPES = ['invoice', 'opening_balance', 'receipt'];

    protected $fillable = ['type'];

    protected function casts(): array
    {
        return ['next_number' => 'integer'];
    }
}
