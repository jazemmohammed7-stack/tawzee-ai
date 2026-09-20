<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Support\AuthenticationInput;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class ResetUserPassword
{
    public function handle(string $email, #[\SensitiveParameter] string $token, #[\SensitiveParameter] string $password, #[\SensitiveParameter] string $confirmation): void
    {
        AuthenticationInput::guest();
        $email = AuthenticationInput::email($email);
        AuthenticationInput::throttle('password-reset', $email);
        $data = Validator::make([
            'email' => $email, 'token' => $token, 'password' => $password, 'password_confirmation' => $confirmation,
        ], [
            'email' => ['required', 'email', 'max:255'],
            'token' => ['required', 'string', 'max:255'],
            'password' => AuthenticationInput::newPasswordRules(),
            'password_confirmation' => ['required', 'string', 'max:72'],
        ], __('registration.validation'), __('authentication.attributes'))->validate();

        $status = DB::transaction(function () use ($data): string {
            // Auth exception: global normalized email. Serialize consumption of this user's token.
            User::where('email', $data['email'])->lockForUpdate()->first();

            return Password::broker()->reset($data + ['is_active' => true], function (User $user, string $password): void {
                $user->password = $password;
                $user->setRememberToken(Str::random(60));
                if (! $user->save()) {
                    throw new RuntimeException('Password update was cancelled.');
                }
                DB::afterCommit(fn () => event(new PasswordReset($user)));
            });
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __('authentication.invalid_reset')]);
        }
        session()->invalidate();
        session()->regenerateToken();
    }
}
