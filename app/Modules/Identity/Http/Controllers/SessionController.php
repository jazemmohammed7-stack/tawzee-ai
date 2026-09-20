<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Actions\LogoutUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SessionController
{
    public function pending(Request $request): View
    {
        return view('auth.pending', ['pending' => $request->user()->company->status === 'pending_setup']);
    }

    public function destroy(LogoutUser $logout): RedirectResponse
    {
        $logout->handle();

        return redirect()->route('home');
    }
}
