<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/** Deliberately broken, test-only model to prove the helper detects missing isolation. */
class UnprotectedTenantRecord extends Model
{
    protected $table = 'document_sequences';

    protected $fillable = ['type'];
}
