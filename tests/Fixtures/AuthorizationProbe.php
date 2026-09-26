<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Modules\Company\Models\DocumentSequence;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

final class AuthorizationProbe extends Component
{
    use AuthorizesRequests;

    public ?int $viewedId = null;

    public function view(int $sequenceId): void
    {
        $sequence = DocumentSequence::findOrFail($sequenceId);
        $this->authorize('view', $sequence);
        $this->viewedId = $sequence->getKey();
    }

    public function render(): string
    {
        return '<div></div>';
    }
}
