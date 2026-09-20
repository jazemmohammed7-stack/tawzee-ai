<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Modules\Company\Models\Company;
use App\Support\Tenancy\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class SetCurrentCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        return app(CurrentCompany::class)->forRequest(function () use ($request, $next): Response {
            $identity = Auth::user();
            if ($identity === null) {
                return $next($request);
            }
            // Authentication bootstrap exception: re-read the identity, never use client fields or cached relations.
            $user = $identity instanceof User && $identity->exists
                ? User::find($identity->getRawOriginal('id')) : null;
            $company = $user?->company_id ? Company::find($user->company_id) : null;
            if (! $user || ! $user->is_active || ! $company || (int) $company->id !== (int) $user->company_id) {
                // An invalid/stale identity must never be saved while rotating a remember token.
                Auth::guard()->logoutCurrentDevice();
                session()->invalidate();
                session()->regenerateToken();
                if ($request->expectsJson()) {
                    abort(401);
                }

                return redirect()->route('login')->with('status', __('authentication.session_ended'));
            }
            Auth::guard()->setUser($user);
            $user->setRelation('company', $company);

            return app(CurrentCompany::class)->run($company, fn () => $next($request));
        });
    }
}
