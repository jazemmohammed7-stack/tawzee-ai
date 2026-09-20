<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Foundation extends Component
{
    #[Locked]
    public bool $checked = false;

    // Public, stateless demonstration: no tenant data or privileged operation.
    public function checkConnection(): void
    {
        $this->checked = true;
    }

    public function render(): View
    {
        return view('livewire.foundation');
    }
}
