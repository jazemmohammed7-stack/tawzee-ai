<?php

declare(strict_types=1);

namespace App\Modules\Access\Policies;

use App\Models\User;
use App\Modules\Access\Enums\Permission;
use App\Modules\Company\Models\DocumentSequence;

final class DocumentSequencePolicy extends TenantPolicy
{
    public function view(User $user, DocumentSequence $sequence): bool
    {
        return $this->allows($user, $sequence, Permission::CompanySettings);
    }
}
