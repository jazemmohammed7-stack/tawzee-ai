<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final class AuthenticationInput
{
    public static function guest(): void
    {
        abort_if(Auth::check(), 403);
    }

    public static function email(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public static function throttle(string $operation, string $email): void
    {
        $ip = (string) request()->ip();
        $keys = [$operation.':ip:'.hash('sha256', $ip) => 20,
            $operation.':identity:'.hash('sha256', $email.'|'.$ip) => 5];
        foreach ($keys as $key => $maximum) {
            if (RateLimiter::tooManyAttempts($key, $maximum)) {
                throw ValidationException::withMessages(['email' => __('authentication.throttled')]);
            }
        }
        foreach ($keys as $key => $maximum) {
            RateLimiter::hit($key, 60);
        }
    }

    public static function newPasswordRules(): array
    {
        return ['required', 'string', 'min:12', 'confirmed', function ($attribute, $value, $fail): void {
            if (is_string($value) && (strlen($value) > 72 || str_contains($value, "\0"))) {
                $fail(__('registration.password_length'));
            }
        }];
    }
}
