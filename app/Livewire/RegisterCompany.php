<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Modules\Identity\Actions\RegisterCompany as RegisterCompanyAction;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

class RegisterCompany extends Component
{
    public string $company_name = '';

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    #[Locked]
    public bool $registered = false;

    public function mount(): void
    {
        abort_unless(Auth::guest(), 403);
    }

    public function register(RegisterCompanyAction $action): void
    {
        abort_unless(Auth::guest(), 403);
        $this->resetErrorBag();
        $key = 'company-registration:'.hash('sha256', (string) request()->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('registration', __('registration.throttled'));
            $this->reset('password', 'password_confirmation');

            return;
        }
        RateLimiter::hit($key, 60);

        try {
            $action->handle($this->only(['company_name', 'name', 'email', 'password', 'password_confirmation']));
            $this->registered = true;
            $this->reset('company_name', 'name', 'email');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            // Never log SQL, input, passwords or exception messages from registration.
            Log::error('Company registration failed.', ['exception_class' => $exception::class]);
            $this->addError('registration', __('registration.failed'));
        } finally {
            $this->reset('password', 'password_confirmation');
        }
    }

    public function render(): View
    {
        return view('livewire.register-company');
    }
}
