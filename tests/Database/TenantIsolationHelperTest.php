<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\User;
use App\Modules\Company\Models\DocumentSequence;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithTenantIsolation;
use Tests\Fixtures\TenantProbe;
use Tests\Fixtures\UnprotectedTenantRecord;
use Tests\TestCase;

class TenantIsolationHelperTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithTenantIsolation;

    private function pair(): array
    {
        return $this->tenantPair(fn () => DocumentSequence::create(['type' => 'probe']));
    }

    private function brokenPair(): array
    {
        return $this->tenantPair(function (User $user): Model {
            $resource = new UnprotectedTenantRecord(['type' => 'broken']);
            $resource->company_id = $user->company_id; // Explicit fixture ownership, no runtime scope removal.
            $resource->save();

            return $resource;
        });
    }

    public function test_helper_proves_real_eloquent_read_update_delete_and_restores_identity(): void
    {
        $pair = $this->pair();
        $this->assertTenantReadIsolation($pair, fn ($row) => DocumentSequence::find($row->id));
        $this->assertTenantUpdateIsolation($pair, fn ($row) => DocumentSequence::whereKey($row->id)->update(['next_number' => 4]), ['next_number' => 4]);
        $this->assertTenantDeleteIsolation($pair, fn ($row) => DocumentSequence::whereKey($row->id)->delete());
        $this->assertGuest();
    }

    public function test_helper_requires_context_rejects_injected_owner_and_cleans_exception(): void
    {
        $pair = $this->pair();
        $this->assertTenantCreationRequiresContext($pair, fn () => DocumentSequence::create(['type' => 'new']));
        $this->assertTenantInjectionRejected($pair,
            fn () => DocumentSequence::create(['type' => 'valid']),
            fn ($companyId) => (new DocumentSequence(['type' => 'injected']))->forceFill(['company_id' => $companyId])->save());
        $this->assertTenantContextClosesOnException($pair['userA']);
    }

    public function test_bounded_context_restores_existing_identity_after_exception(): void
    {
        $pair = $this->pair();
        $this->actingAs($pair['userA']);
        // No outer company run: crossing an active internal context is deliberately forbidden by the app.
        try {
            $this->withinTenant($pair['userB'], function () use ($pair): void {
                $this->assertSame($pair['userB']->company_id, app(CurrentCompany::class)->id());
                throw new \LogicException('Expected fixture exception.');
            });
        } catch (\LogicException $exception) {
            $this->assertSame('Expected fixture exception.', $exception->getMessage());
        }
        $this->assertTrue(Auth::user()->is($pair['userA']));
        $this->assertSame($pair['userA']->company_id, app(CurrentCompany::class)->id());
    }

    public function test_helper_exercises_http_and_route_binding_with_positive_identity_control(): void
    {
        $pair = $this->pair();
        Route::middleware(['web', 'auth', 'tenant'])->get('/_helper/{sequence}', fn (DocumentSequence $sequence) => ['id' => $sequence->id]);
        $this->assertTenantHttpIsolation($pair, fn ($row) => $this->getJson('/_helper/'.$row->id), fn ($response) => $response->json('id'));
    }

    public function test_helper_exercises_real_livewire_update_and_foreign_rejection(): void
    {
        $pair = $this->pair();
        Livewire::component('tenant-test-probe', TenantProbe::class);
        app('view')->addLocation(base_path('tests/Fixtures/views'));
        Route::middleware(['web', 'auth', 'tenant'])->get('/_helper-livewire', fn () => view('tenant-test-probe'));
        $snapshot = $this->withinTenant($pair['userA'], function (): string {
            $response = $this->get('/_helper-livewire')->assertOk();
            preg_match('/wire:snapshot="([^"]+)"/', $response->getContent(), $match);

            return html_entity_decode($match[1], ENT_QUOTES);
        });
        $this->assertTenantUpdateIsolation($pair, fn ($row) => $this->withHeader('X-Livewire', 'true')->postJson(app('livewire')->getUpdateUri(), [
            'components' => [['snapshot' => $snapshot, 'updates' => ['sequenceId' => $row->id],
                'calls' => [['path' => '', 'method' => 'change', 'params' => []]]]],
        ]), ['next_number' => 2]);
    }

    #[DataProvider('leaks')]
    public function test_helper_detects_real_leaks_in_unprotected_fixture(string $operation): void
    {
        $pair = $this->brokenPair();
        // This expectation covers the helper assertion, never an arbitrary runtime exception.
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage(match ($operation) {
            'read' => 'Foreign resource leaked.',
            'update' => 'Foreign row changed despite denial.',
            'delete' => 'Foreign row was deleted or changed.',
        });
        match ($operation) {
            'read' => $this->assertTenantReadIsolation($pair, fn ($row) => UnprotectedTenantRecord::find($row->id)),
            'update' => $this->assertTenantUpdateIsolation($pair, function ($row): int {
                UnprotectedTenantRecord::where('company_id', $row->company_id)->whereKey($row->id)->update(['next_number' => 8]);

                return 0; // Lying status: helper must inspect the real DB after denial.
            }, ['next_number' => 8]),
            'delete' => $this->assertTenantDeleteIsolation($pair, function ($row): int {
                UnprotectedTenantRecord::where('company_id', $row->company_id)->whereKey($row->id)->delete();

                return 0;
            }),
        };
    }

    public static function leaks(): array
    {
        return [['read'], ['update'], ['delete']];
    }

    #[DataProvider('falseControls')]
    public function test_helper_rejects_false_positive_controls(string $kind): void
    {
        $pair = $this->pair();
        if ($kind === 'missing B') {
            $this->withinTenant($pair['userB'], fn () => $pair['resourceB']->delete());
        }
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage(match ($kind) {
            'missing B' => 'Fixture must be persisted.',
            'read nothing' => 'Own resource must be readable.',
            'update nothing' => 'Own update must persist expected values.',
            'delete nothing' => 'Own delete must actually delete.',
            'always 404' => '200',
            'wrong identity' => 'Positive response must identify the own resource.',
            '404 leaks identity' => 'Denied response leaked a resource identity.',
        });
        match ($kind) {
            'missing B' => $this->assertTenantReadIsolation($pair, fn ($row) => DocumentSequence::find($row->id)),
            'read nothing' => $this->assertTenantReadIsolation($pair, fn () => null),
            'update nothing' => $this->assertTenantUpdateIsolation($pair, fn () => 0, ['next_number' => 5]),
            'delete nothing' => $this->assertTenantDeleteIsolation($pair, fn () => 0),
            'always 404' => $this->assertTenantHttpIsolation($pair, fn () => TestResponse::fromBaseResponse(response()->json([], 404)), fn ($response) => $response->json('id')),
            'wrong identity' => $this->assertTenantHttpIsolation($pair, fn () => TestResponse::fromBaseResponse(response()->json(['id' => -1])), fn ($response) => $response->json('id')),
            '404 leaks identity' => $this->assertTenantHttpIsolation($pair,
                fn ($row) => TestResponse::fromBaseResponse(response()->json(['id' => $row->id], $row->is($pair['resourceA']) ? 200 : 404)),
                fn ($response) => $response->json('id')),
        };
    }

    public static function falseControls(): array
    {
        return array_map(fn ($kind) => [$kind], ['missing B', 'read nothing', 'update nothing', 'delete nothing', 'always 404', 'wrong identity', '404 leaks identity']);
    }

    public function test_unrelated_exception_is_not_treated_as_security_denial(): void
    {
        $pair = $this->pair();
        $sentinel = new \LogicException('Unexpected transport failure.');
        try {
            $this->assertTenantUpdateIsolation($pair, function ($row) use ($pair, $sentinel) {
                if ($row->is($pair['resourceB'])) {
                    throw $sentinel;
                }

                return DocumentSequence::whereKey($row->id)->update(['next_number' => 3]);
            }, ['next_number' => 3]);
            $this->fail('Unrelated exception was swallowed.');
        } catch (\LogicException $exception) {
            $this->assertSame($sentinel, $exception);
        }
    }

    public function test_helper_detects_context_free_creation_even_when_it_returns_normally(): void
    {
        $pair = $this->brokenPair();
        $counter = 0;
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Context-free creation wrote a row.');
        $this->assertTenantCreationRequiresContext($pair, function () use ($pair, &$counter) {
            $resource = new UnprotectedTenantRecord(['type' => 'unsafe'.++$counter]);
            $resource->company_id = $pair['userA']->company_id;
            $resource->save();

            return $resource;
        });
    }

    public function test_helper_detects_injected_write_before_security_exception(): void
    {
        $pair = $this->brokenPair();
        $create = function (int $companyId, string $type): Model {
            $resource = new UnprotectedTenantRecord(['type' => $type]);
            $resource->company_id = $companyId;
            $resource->save();

            return $resource;
        };
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Injected request wrote data despite denial.');
        $this->assertTenantInjectionRejected($pair, fn () => $create($pair['userA']->company_id, 'valid'),
            function ($companyId) use ($create): void {
                $create($companyId, 'injected');
                throw new AuthorizationException('Misleading denial after write.');
            });
    }
}
