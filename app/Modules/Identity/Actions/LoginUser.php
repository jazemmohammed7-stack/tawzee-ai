<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Support\AuthenticationInput;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class LoginUser
{
    public function handle(string $email, #[\SensitiveParameter] string $password): void
    {
        AuthenticationInput::guest();
        $email = AuthenticationInput::email($email);
        AuthenticationInput::throttle('login', $email);
        Validator::make(compact('email', 'password'), [
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:4096'],
        ], __('registration.validation'), __('registration.attributes'))->validate();

        if (! Auth::attempt(['email' => $email, 'password' => $password, 'is_active' => true])) {
            throw ValidationException::withMessages(['email' => __('authentication.failed')]);
        }
        session()->regenerate();
        // Seed immediately so even a session unused until after a reset is invalidated.
        session()->put('password_hash_'.Auth::getDefaultDriver(), Auth::user()->getAuthPassword());
    }
}
