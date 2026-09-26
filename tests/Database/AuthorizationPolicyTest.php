<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\User;
use App\Modules\Access\Actions\InitializeCompanyRoles;
use App\Modules\Access\Enums\DefaultRole;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\DocumentSequence;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithTenantIsolation;
use Tests\Fixtures\AuthorizationProbe;
use Tests\Fixtures\AuthorizationProbeController;
use Tests\TestCase;

class AuthorizationPolicyTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithTenantIsolation;

    protected function setUp(): void
    {
        parent::setUp();
        Route::middleware(['web', 'auth', 'tenant'])->get(
            '/_authorization/sequence/{sequence}',
            AuthorizationProbeController::class,
        );
        Livewire::component('authorization-probe', AuthorizationProbe::class);
    }

    public function test_http_authorize_allows_permission_denies_missing_permission_and_hides_foreign_resource(): void
    {
        [$ownerA, $viewerA, $sequenceA] = $this->tenantFixtures();
        [$ownerB, $viewerB, $sequenceB] = $this->tenantFixtures();
        $this->withinTenant($ownerA, function () use ($ownerA, $sequenceA): void {
            $this->assertTrue($ownerA->fresh()->hasPermissionTo('company.settings'));
            $this->assertTrue(Gate::forUser($ownerA->fresh())->allows('view', $sequenceA->fresh()));
        });

        $this->actingAs($ownerA)
            ->getJson('/_authorization/sequence/'.$sequenceA->id)
            ->assertOk()->assertExactJson(['id' => $sequenceA->id]);
        $this->actingAs($viewerA)
            ->getJson('/_authorization/sequence/'.$sequenceA->id)
            ->assertForbidden();
        $this->actingAs($ownerA)
            ->getJson('/_authorization/sequence/'.$sequenceB->id)
            ->assertNotFound();
        $this->actingAs($ownerB)
            ->getJson('/_authorization/sequence/'.$sequenceB->id)
            ->assertOk()->assertExactJson(['id' => $sequenceB->id]);
        $this->actingAs($viewerB)
            ->getJson('/_authorization/sequence/'.$sequenceB->id)
            ->assertForbidden();
    }

    public function test_http_rejects_forged_company_selection_without_changing_authorization(): void
    {
        [$owner, , $sequence] = $this->tenantFixtures();

        $this->actingAs($owner)
            ->getJson('/_authorization/sequence/'.$sequence->id.'?company_id=999')
            ->assertForbidden();
        $this->actingAs($owner)
            ->getJson('/_authorization/sequence/'.$sequence->id)
            ->assertOk();
        $this->actingAs($owner)->withHeader('X-Company-Id', '999')
            ->getJson('/_authorization/sequence/'.$sequence->id)
            ->assertForbidden();
    }

    public function test_policy_fails_closed_without_context_and_for_foreign_resource(): void
    {
        [$ownerA, , $sequenceA] = $this->tenantFixtures();
        [, , $sequenceB] = $this->tenantFixtures();

        Auth::forgetGuards();
        $this->assertFalse(Gate::forUser($ownerA)->allows('view', $sequenceA));

        $this->withinTenant($ownerA, function () use ($ownerA, $sequenceA, $sequenceB): void {
            $this->assertTrue(Gate::forUser($ownerA)->allows('view', $sequenceA));
            $this->assertFalse(Gate::forUser($ownerA)->allows('view', $sequenceB));
        });
    }

    public function test_livewire_authorize_enforces_permission_and_tenant_scope(): void
    {
        [$ownerA, $viewerA, $sequenceA] = $this->tenantFixtures();
        [$ownerB, , $sequenceB] = $this->tenantFixtures();

        $this->actingAs($ownerA);
        Livewire::test(AuthorizationProbe::class)
            ->call('view', $sequenceA->id)
            ->assertSet('viewedId', $sequenceA->id);

        $this->actingAs($viewerA);
        Livewire::test(AuthorizationProbe::class)
            ->call('view', $sequenceA->id)
            ->assertForbidden();

        $this->actingAs($ownerA);
        try {
            Livewire::test(AuthorizationProbe::class)->call('view', $sequenceB->id);
            $this->fail('Foreign Livewire resource was visible.');
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }

        $this->actingAs($ownerB);
        Livewire::test(AuthorizationProbe::class)
            ->call('view', $sequenceB->id)
            ->assertSet('viewedId', $sequenceB->id);
    }

    public function test_exception_restores_context_and_next_tenant_uses_its_own_team(): void
    {
        [$ownerA, , $sequenceA] = $this->tenantFixtures();
        [$ownerB, , $sequenceB] = $this->tenantFixtures();
        Auth::forgetGuards();

        try {
            $this->withinTenant($ownerA, function () use ($ownerA, $sequenceA): void {
                $this->assertTrue(Gate::forUser($ownerA)->allows('view', $sequenceA));
                throw new \RuntimeException('Expected policy probe failure.');
            });
            $this->fail('Expected exception did not propagate.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Expected policy probe failure.', $exception->getMessage());
        }

        try {
            app(CurrentCompany::class)->id();
            $this->fail('Tenant context leaked after exception.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        $this->withinTenant($ownerB, function () use ($ownerB, $sequenceA, $sequenceB): void {
            $this->assertTrue(Gate::forUser($ownerB)->allows('view', $sequenceB));
            $this->assertFalse(Gate::forUser($ownerB)->allows('view', $sequenceA));
        });
    }

    /** @return array{User, User, DocumentSequence} */
    private function tenantFixtures(): array
    {
        $company = Company::factory()->create();
        $owner = User::factory()->for($company)->create()->refresh();
        $viewer = User::factory()->for($company)->create()->refresh();
        $company->founder_user_id = $owner->id;
        $company->save();
        app(InitializeCompanyRoles::class)->handle($company);

        $sequence = $this->withinTenant($owner, function () use ($viewer): DocumentSequence {
            $viewer->assignRole(DefaultRole::Viewer->value);

            return DocumentSequence::create(['type' => 'authorization-probe']);
        });

        return [$owner, $viewer, $sequence];
    }
}
