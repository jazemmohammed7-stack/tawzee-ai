<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Modules\Identity\Actions\LoginUser;
use App\Modules\Identity\Support\AuthenticationInput;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public function mount(): void
    {
        AuthenticationInput::guest();
    }

    public function login(LoginUser $action): void
    {
        AuthenticationInput::guest();
        $this->resetErrorBag();
        try {
            $action->handle($this->email, $this->password);
            $this->redirectRoute('setup.pending');
        } finally {
            $this->reset('password');
        }
    }

    public function render(): View
    {
        return view('livewire.auth.login');
    }
}
