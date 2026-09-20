<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\User;
use App\Modules\Company\Models\Company;
use App\Support\Tenancy\CurrentCompany;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;

trait InteractsWithTenantIsolation
{
    /** @return array{userA: User, userB: User, resourceA: Model, resourceB: Model} */
    protected function tenantPair(Closure $create): array
    {
        $userA = User::factory()->for(Company::factory())->create()->refresh();
        $userB = User::factory()->for(Company::factory())->create()->refresh();
        $resourceA = $this->withinTenant($userA, fn () => $create($userA));
        $resourceB = $this->withinTenant($userB, fn () => $create($userB));
        $pair = compact('userA', 'userB', 'resourceA', 'resourceB');
        $this->assertTenantFixtures($pair);

        return $pair;
    }

    /** Bounded test identity/context; always restores the caller, including on failure. */
    protected function withinTenant(User $user, Closure $callback): mixed
    {
        $guard = Auth::guard();
        $previous = $guard->user();
        $guard->setUser($user);
        try {
            return app(CurrentCompany::class)->run($user->company()->firstOrFail(), $callback);
        } finally {
            $previous === null ? $guard->forgetUser() : $guard->setUser($previous);
        }
    }

    protected function storedTenantResource(User $user, Model $resource): ?Model
    {
        return $this->withinTenant($user, fn () => $resource->newQuery()
            ->where('company_id', $user->company_id)->find($resource->getKey()));
    }

    protected function assertTenantFixtures(array $pair): void
    {
        $this->assertNotSame($pair['userA']->company_id, $pair['userB']->company_id, 'Companies must differ.');
        $this->assertSame($pair['resourceA']::class, $pair['resourceB']::class);
        $this->assertNotSame($pair['resourceA']->getKey(), $pair['resourceB']->getKey(), 'Resources must differ.');
        foreach (['A', 'B'] as $side) {
            $resource = $pair['resource'.$side];
            $this->assertTrue($resource->exists, 'Fixture must be persisted.');
            $stored = $this->storedTenantResource($pair['user'.$side], $resource);
            $this->assertNotNull($stored, 'Fixture must exist in its own company.');
            $this->assertSame($pair['user'.$side]->company_id, $stored->company_id);
        }
    }

    /** $read(Model): ?Model; an unexpected exception is never a successful denial. */
    protected function assertTenantReadIsolation(array $pair, Closure $read): void
    {
        $this->assertTenantFixtures($pair);
        $this->withinTenant($pair['userA'], function () use ($pair, $read): void {
            $own = $read($pair['resourceA']);
            $this->assertInstanceOf(Model::class, $own, 'Own resource must be readable.');
            $this->assertTrue($own->is($pair['resourceA']));
            $this->assertNull($read($pair['resourceB']), 'Foreign resource leaked.');
        });
    }

    /** Own mutation must persist the specified changed fields before testing foreign denial. */
    protected function assertTenantUpdateIsolation(array $pair, Closure $update, array $expected): void
    {
        $this->assertTenantFixtures($pair);
        $beforeA = $this->storedTenantResource($pair['userA'], $pair['resourceA'])->getAttributes();
        $beforeB = $this->storedTenantResource($pair['userB'], $pair['resourceB'])->getAttributes();
        $this->assertNotEmpty($expected);
        $this->assertNotSame(array_intersect_key($beforeA, $expected), $expected, 'Positive update must change stored values.');
        $this->withinTenant($pair['userA'], fn () => $update($pair['resourceA']));
        $after = $this->storedTenantResource($pair['userA'], $pair['resourceA']);
        $this->assertNotNull($after);
        foreach ($expected as $key => $value) {
            $this->assertSame($value, $after->getAttribute($key), 'Own update must persist expected values.');
        }
        $this->withinTenant($pair['userA'], fn () => $this->assertTenantDenial(fn () => $update($pair['resourceB'])));
        $this->assertSame($beforeB, $this->storedTenantResource($pair['userB'], $pair['resourceB'])?->getAttributes(), 'Foreign row changed despite denial.');
    }

    protected function assertTenantDeleteIsolation(array $pair, Closure $delete): void
    {
        $this->assertTenantFixtures($pair);
        $beforeB = $this->storedTenantResource($pair['userB'], $pair['resourceB'])->getAttributes();
        $this->withinTenant($pair['userA'], fn () => $delete($pair['resourceA']));
        $this->assertNull($this->storedTenantResource($pair['userA'], $pair['resourceA']), 'Own delete must actually delete.');
        $this->withinTenant($pair['userA'], fn () => $this->assertTenantDenial(fn () => $delete($pair['resourceB'])));
        $this->assertSame($beforeB, $this->storedTenantResource($pair['userB'], $pair['resourceB'])?->getAttributes(), 'Foreign row was deleted or changed.');
    }

    /** HTTP/Binding adapter: successful status AND returned identity, not an arbitrary 200/404. */
    protected function assertTenantHttpIsolation(array $pair, Closure $request, Closure $identity): void
    {
        $this->assertTenantFixtures($pair);
        $beforeB = $this->storedTenantResource($pair['userB'], $pair['resourceB'])->getAttributes();
        $this->withinTenant($pair['userA'], function () use ($pair, $request, $identity): void {
            $response = $request($pair['resourceA']);
            $response->assertOk();
            $this->assertSame($pair['resourceA']->getKey(), $identity($response), 'Positive response must identify the own resource.');
            $foreign = $request($pair['resourceB']);
            $foreign->assertNotFound();
            $this->assertNull($identity($foreign), 'Denied response leaked a resource identity.');
        });
        $this->assertTenantFixtures($pair);
        $this->assertSame($beforeB, $this->storedTenantResource($pair['userB'], $pair['resourceB'])->getAttributes(), 'Denied read changed the foreign row.');
    }

    /** Invoke as guest. Both creation probes use the same valid factory. */
    protected function assertTenantCreationRequiresContext(array $pair, Closure $create): void
    {
        $this->assertGuest();
        $this->assertTenantFixtures($pair);
        $created = $this->withinTenant($pair['userA'], $create);
        $this->assertInstanceOf(Model::class, $created);
        $this->assertSame($pair['resourceA']::class, $created::class, 'Creation must exercise the same resource type.');
        $this->assertNotNull($this->storedTenantResource($pair['userA'], $created), 'Valid creation must persist.');
        $before = $this->tenantRows($pair);
        $denied = false;
        try {
            $create();
        } catch (AuthorizationException) {
            $denied = true;
        }
        $this->assertSame($before, $this->tenantRows($pair), 'Context-free creation wrote a row.');
        $this->assertTrue($denied, 'Creation without a company context must be rejected.');
    }

    /** Normal input must create a real resource; injected input must reject with no DB effect. */
    protected function assertTenantInjectionRejected(array $pair, Closure $validCreate, Closure $injectedCreate): void
    {
        $this->assertTenantFixtures($pair);
        $created = $this->withinTenant($pair['userA'], $validCreate);
        $this->assertInstanceOf(Model::class, $created);
        $this->assertSame($pair['resourceA']::class, $created::class, 'Creation must exercise the same resource type.');
        $this->assertNotNull($this->storedTenantResource($pair['userA'], $created));
        $before = $this->tenantRows($pair);
        $this->withinTenant($pair['userA'], fn () => $this->assertTenantDenial(fn () => $injectedCreate($pair['userB']->company_id)));
        $this->assertSame($before, $this->tenantRows($pair), 'Injected request wrote data despite denial.');
    }

    protected function assertTenantContextClosesOnException(User $user): void
    {
        $this->assertGuest();
        $sentinel = new \RuntimeException('Expected tenant callback failure.');
        try {
            $this->withinTenant($user, function () use ($user, $sentinel): void {
                $this->assertSame($user->company_id, app(CurrentCompany::class)->id());
                throw $sentinel;
            });
            $this->fail('Expected exception did not propagate.');
        } catch (\RuntimeException $caught) {
            $this->assertSame($sentinel, $caught);
        }
        $this->assertGuest();
        try {
            app(CurrentCompany::class)->id();
            $this->fail('Tenant context leaked.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }
    }

    private function tenantRows(array $pair): array
    {
        $rows = [];
        foreach (['A', 'B'] as $side) {
            $user = $pair['user'.$side];
            $model = $pair['resource'.$side];
            $rows[$side] = $this->withinTenant($user, fn () => $model->newQuery()->where('company_id', $user->company_id)
                ->orderBy($model->getKeyName())->get()->map(fn (Model $row) => $row->getAttributes())->all());
        }

        return $rows;
    }

    private function assertTenantDenial(Closure $operation): void
    {
        try {
            $result = $operation();
        } catch (AuthorizationException|ModelNotFoundException|ValidationException) {
            $this->addToAssertionCount(1);

            return;
        }
        if ($result instanceof TestResponse) {
            $this->assertContains($result->status(), [403, 404, 422], 'Expected an explicit denial response.');
        } else {
            $this->assertTrue($result === 0 || $result === false, 'Expected zero affected rows or a security exception.');
        }
    }
}
