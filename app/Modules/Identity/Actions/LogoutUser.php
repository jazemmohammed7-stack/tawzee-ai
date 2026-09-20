<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use Illuminate\Support\Facades\Auth;

final class LogoutUser
{
    public function handle(): void
    {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
    }
}
