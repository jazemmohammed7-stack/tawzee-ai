<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Modules\Company\Models\DocumentSequence;
use App\Support\Tenancy\CurrentCompany;
use Livewire\Component;

class TenantProbe extends Component
{
    // Deliberately unsafe test property: middleware must reject client updates before any action.
    public int $company_id = 0;

    public int $sequenceId = 0;

    public function change(): void
    {
        DocumentSequence::findOrFail($this->sequenceId)->increment('next_number');
    }

    public function crash(): void
    {
        throw new \RuntimeException('Tenant probe failure.');
    }

    public function render(): string
    {
        $id = app(CurrentCompany::class)->id();

        return '<div>Tenant probe '.$id.'</div>';
    }
}
