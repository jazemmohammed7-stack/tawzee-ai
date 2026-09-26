<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Modules\Company\Models\DocumentSequence;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

final class AuthorizationProbeController
{
    use AuthorizesRequests;

    public function __invoke(DocumentSequence $sequence): JsonResponse
    {
        $this->authorize('view', $sequence);

        return response()->json(['id' => $sequence->getKey()]);
    }
}
