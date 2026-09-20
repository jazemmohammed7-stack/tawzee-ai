<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Modules\Identity\Actions\SendPasswordResetLink;
use App\Modules\Identity\Support\AuthenticationInput;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

class ForgotPassword extends Component
{
    public string $email = '';

    #[Locked]
    public bool $sent = false;

    public function mount(): void
    {
        AuthenticationInput::guest();
    }

    public function sendLink(SendPasswordResetLink $action): void
    {
        AuthenticationInput::guest();
        $this->resetErrorBag();
        try {
            $action->handle($this->email);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Password reset delivery failed.', ['exception_class' => $exception::class]);
        }
        $this->sent = true;
    }

    public function render(): View
    {
        return view('livewire.auth.forgot-password');
    }
}
