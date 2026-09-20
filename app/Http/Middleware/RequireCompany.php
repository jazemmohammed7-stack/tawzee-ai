<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Tenancy\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user(), 401);
        app(CurrentCompany::class)->id();
        // Also examines Livewire updates/call arguments, without trusting snapshot data as a company source.
        foreach ([$request->all(), $request->cookies->all(), $request->headers->all(), $request->route()?->parameters() ?? []] as $input) {
            abort_if($this->containsCompanySelection($input), 403);
        }

        return $next($request);
    }

    private function containsCompanySelection(array $input): bool
    {
        foreach ($input as $key => $value) {
            $key = str_replace('-', '_', strtolower((string) $key));
            $segments = explode('.', $key);
            if (in_array('company_id', $segments, true) || $key === 'x_company_id') {
                return true;
            }
            if (is_array($value) && $this->containsCompanySelection($value)) {
                return true;
            }
        }

        return false;
    }
}
