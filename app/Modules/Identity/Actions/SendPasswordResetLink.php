<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Mail\PasswordResetLink;
use App\Modules\Identity\Support\AuthenticationInput;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;

final class SendPasswordResetLink
{
    public function handle(string $email): void
    {
        AuthenticationInput::guest();
        $email = AuthenticationInput::email($email);
        AuthenticationInput::throttle('password-email', $email);
        Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:255']],
            __('registration.validation'), __('registration.attributes'))->validate();

        // Delivery is opt-in. Never put reset tokens in the default log mailer.
        if (! config('auth.password_reset_mail_enabled')
            || config('mail.default') !== 'smtp'
            || config('mail.mailers.smtp.transport') !== 'smtp') {
            return;
        }

        Password::broker()->sendResetLink(['email' => $email, 'is_active' => true], function (User $user, string $token): void {
            // Trusted APP_URL, not the client-controlled Host header.
            $url = rtrim(config('app.url'), '/').route('password.reset', ['token' => $token, 'email' => $user->email], false);
            Mail::to($user->email)->send(new PasswordResetLink($url));
        });
        // Deliberately do not expose INVALID_USER, RESET_THROTTLED or RESET_LINK_SENT.
    }
}
