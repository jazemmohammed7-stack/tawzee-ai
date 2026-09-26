<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\User;
use App\Modules\Access\Actions\InitializeCompanyRoles;
use App\Modules\Access\Enums\DefaultRole;
use App\Modules\Access\Enums\Permission;
use App\Modules\Access\Models\Role;
use App\Modules\Company\Models\Company;
use App\Modules\Identity\Actions\RegisterCompany;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Spatie\Permission\Models\Permission as PermissionModel;
use Tests\Concerns\InteractsWithTenantIsolation;
use Tests\TestCase;

class CompanyRolesTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithTenantIsolation;

    private const MATRIX = [
        'owner' => ['users.manage', 'company.settings', 'customers.view', 'customers.manage', 'products.view', 'products.manage', 'warehouses.manage', 'stock.view', 'stock.operate', 'invoices.view', 'invoices.create', 'invoices.confirm', 'invoices.cancel', 'invoices.override_credit_limit', 'collections.view', 'collections.record', 'collections.void', 'reports.view', 'reports.export'],
        'admin' => ['users.manage', 'company.settings', 'customers.view', 'customers.manage', 'products.view', 'products.manage', 'warehouses.manage', 'stock.view', 'stock.operate', 'invoices.view', 'invoices.create', 'invoices.confirm', 'invoices.cancel', 'invoices.override_credit_limit', 'collections.view', 'collections.record', 'collections.void', 'reports.view', 'reports.export'],
        'sales' => ['customers.view', 'customers.manage', 'products.view', 'stock.view', 'invoices.view', 'invoices.create', 'invoices.confirm', 'collections.view', 'collections.record'],
        'warehouse' => ['products.view', 'warehouses.manage', 'stock.view', 'stock.operate'],
        'accountant' => ['customers.view', 'products.view', 'stock.view', 'invoices.view', 'invoices.confirm', 'invoices.cancel', 'collections.view', 'collections.record', 'collections.void', 'reports.view', 'reports.export'],
        'viewer' => ['customers.view', 'products.view', 'stock.view', 'invoices.view', 'collections.view', 'reports.view'],
    ];

    public function test_permission_enum_and_role_matrix_match_project_exactly(): void
    {
        [$company, $founder] = $this->companyWithFounder();
        app(InitializeCompanyRoles::class)->handle($company);

        $this->assertSame($this->sorted(array_merge(...array_values(self::MATRIX))), $this->sorted(Permission::values()));
        $this->assertSame($this->sorted(array_keys(self::MATRIX)), $this->sorted(DefaultRole::values()));
        $this->assertSame($this->sorted(Permission::values()), $this->sorted(PermissionModel::pluck('name')->all()));

        $this->withinTenant($founder, function () use ($founder): void {
            $roles = Role::with('permissions')->orderBy('name')->get();
            $this->assertCount(6, $roles);
            $this->assertSame($this->sorted(array_keys(self::MATRIX)), $this->sorted($roles->pluck('name')->all()));
            foreach ($roles as $role) {
                $this->assertSame($this->sorted(self::MATRIX[$role->name]), $this->sorted($role->permissions->pluck('name')->all()));
            }
            $this->assertTrue($founder->fresh()->hasRole(DefaultRole::Owner->value));
        });
    }

    public function test_initializer_is_idempotent_and_isolated_between_existing_companies(): void
    {
        [$companyA, $founderA] = $this->companyWithFounder();
        [$companyB, $founderB] = $this->companyWithFounder();
        $initializer = app(InitializeCompanyRoles::class);

        $initializer->handle($companyA);
        $initializer->handle($companyA);
        $initializer->handle($companyB);
        $initializer->handle($companyB);

        foreach ([[$companyA, $founderA], [$companyB, $founderB]] as [$company, $founder]) {
            $this->withinTenant($founder, function () use ($company, $founder): void {
                $this->assertSame($company->id, app(CurrentCompany::class)->id());
                $this->assertSame($this->sorted(array_keys(self::MATRIX)), $this->sorted(Role::pluck('name')->all()));
                $this->assertSame([$company->id], Role::pluck('company_id')->unique()->values()->all());
                $this->assertTrue($founder->fresh()->hasRole(DefaultRole::Owner->value));
            });
        }

        $this->assertSame(12, DB::table('roles')->count());
        $this->assertSame(19, DB::table('permissions')->count());
        $this->assertSame(2, DB::table('model_has_roles')->count());
        $this->assertSame(array_sum(array_map('count', self::MATRIX)) * 2, DB::table('role_has_permissions')->count());
    }

    public function test_existing_company_without_founder_is_initialized_without_guessing_owner(): void
    {
        $company = Company::factory()->create();

        app(InitializeCompanyRoles::class)->handle($company);
        app(InitializeCompanyRoles::class)->handle($company);

        app(CurrentCompany::class)->run($company, function (): void {
            $this->assertSame($this->sorted(DefaultRole::values()), $this->sorted(Role::pluck('name')->all()));
        });
        $this->assertSame(0, DB::table('model_has_roles')->count());
    }

    public function test_registration_assigns_owner_and_role_failure_rolls_back_everything(): void
    {
        $company = app(RegisterCompany::class)->handle($this->registrationInput())->refresh();
        $founder = $company->founder;

        $this->assertSame('pending_setup', $company->status);
        $this->withinTenant($founder, function () use ($founder): void {
            $this->assertTrue($founder->fresh()->hasRole(DefaultRole::Owner->value));
            $this->assertCount(6, Role::all());
        });

        $before = $this->registrationCounts();
        Event::listen('eloquent.creating: '.Role::class, fn () => throw new RuntimeException('Injected role initialization failure.'));
        try {
            app(RegisterCompany::class)->handle(array_replace($this->registrationInput(), [
                'company_name' => 'Rollback Company',
                'email' => 'roles-rollback@example.test',
            ]));
            $this->fail('Role initialization failure did not roll back registration.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Injected role initialization failure.', $exception->getMessage());
        }
        $this->assertSame($before, $this->registrationCounts());
    }

    /** @return array{Company, User} */
    private function companyWithFounder(): array
    {
        $company = Company::factory()->create();
        $founder = User::factory()->for($company)->create()->refresh();
        $company->founder_user_id = $founder->id;
        $company->save();

        return [$company->refresh(), $founder];
    }

    /** @return list<string> */
    private function sorted(array $values): array
    {
        $values = array_values(array_unique($values));
        sort($values);

        return $values;
    }

    private function registrationInput(): array
    {
        return [
            'company_name' => 'Roles Company',
            'name' => 'Roles Founder',
            'email' => 'roles-registration@example.test',
            'password' => 'Test-only-password-123',
            'password_confirmation' => 'Test-only-password-123',
        ];
    }

    private function registrationCounts(): array
    {
        return array_map(
            fn (string $table): int => DB::table($table)->count(),
            ['companies', 'users', 'document_sequences', 'permissions', 'roles',
                'role_has_permissions', 'model_has_roles', 'activity_logs'],
        );
    }
}
