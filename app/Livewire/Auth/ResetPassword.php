<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Modules\Identity\Actions\ResetUserPassword;
use App\Modules\Identity\Support\AuthenticationInput;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

class ResetPassword extends Component
{
    public string $email = '';

    #[Locked]
    public string $token = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        AuthenticationInput::guest();
        $this->token = $token;
        $email = request()->query('email', '');
        $this->email = is_string($email) ? $email : '';
    }

    public function resetPassword(ResetUserPassword $action): void
    {
        AuthenticationInput::guest();
        $this->resetErrorBag();
        try {
            $action->handle($this->email, $this->token, $this->password, $this->password_confirmation);
            $this->reset('token');
            session()->flash('status', __('authentication.reset_success'));
            $this->redirectRoute('login');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Password update failed.', ['exception_class' => $exception::class]);
            $this->addError('email', __('authentication.invalid_reset'));
        } finally {
            $this->reset('password', 'password_confirmation');
        }
    }

    public function render(): View
    {
        return view('livewire.auth.reset-password');
    }
}
