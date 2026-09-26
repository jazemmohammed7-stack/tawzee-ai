<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\User;
use App\Modules\Access\Models\Role;
use App\Modules\Company\Models\Company;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithTenantIsolation;
use Tests\TestCase;

class PermissionTeamsTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithTenantIsolation;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_roles_are_scoped_and_assignments_are_isolated_by_company(): void
    {
        $userA = User::factory()->for(Company::factory())->create()->refresh();
        $userB = User::factory()->for(Company::factory())->create()->refresh();
        $roleA = $this->withinTenant($userA, fn () => Role::create(['name' => 'isolation-probe']));
        $roleB = $this->withinTenant($userB, fn () => Role::create(['name' => 'isolation-probe']));

        $this->withinTenant($userA, function () use ($userA, $roleA, $roleB): void {
            $this->assertTrue(Role::findOrFail($roleA->id)->is($roleA));
            $this->assertNull(Role::find($roleB->id));

            $userA->assignRole($roleA);
            $this->assertTrue($userA->fresh()->hasRole('isolation-probe'));

            $this->expectException(AuthorizationException::class);
            $userA->assignRole($roleB);
        });

        $this->withinTenant($userB, function () use ($userB, $roleA): void {
            $this->assertFalse($userB->fresh()->hasRole('isolation-probe'));
            $this->expectException(AuthorizationException::class);
            $userB->assignRole($roleA);
        });
    }

    public function test_permission_team_id_cannot_override_current_company(): void
    {
        $userA = User::factory()->for(Company::factory())->create()->refresh();
        $userB = User::factory()->for(Company::factory())->create()->refresh();

        $this->withinTenant($userA, function () use ($userA, $userB): void {
            $this->assertSame($userA->company_id, getPermissionsTeamId());
            setPermissionsTeamId($userA->company_id);
            $this->assertSame($userA->company_id, getPermissionsTeamId());

            $this->expectException(AuthorizationException::class);
            setPermissionsTeamId($userB->company_id);
        });
    }

    public function test_company_context_is_restored_after_nested_permission_work_fails(): void
    {
        $userA = User::factory()->for(Company::factory())->create()->refresh();
        $userB = User::factory()->for(Company::factory())->create()->refresh();

        $this->withinTenant($userA, function () use ($userA, $userB): void {
            try {
                app(CurrentCompany::class)->run(
                    $userB->company()->withoutGlobalScopes()->firstOrFail(),
                    fn () => Role::create(['name' => 'must-not-persist']),
                );
                $this->fail('Conflicting authenticated and company contexts must fail.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }

            $this->assertSame($userA->company_id, getPermissionsTeamId());
            $this->assertSame(0, Role::where('name', 'must-not-persist')->count());
        });
    }
}
