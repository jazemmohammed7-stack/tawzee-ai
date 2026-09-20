<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Company\Models\Company;
use App\Support\Tenancy\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureCompanyReady
{
    public function handle(Request $request, Closure $next): Response
    {
        // Readiness is necessary, never a replacement for resource authorization in P2.
        abort_unless(Company::whereKey(app(CurrentCompany::class)->id())->value('status') === 'active', 403);

        return $next($request);
    }
}
