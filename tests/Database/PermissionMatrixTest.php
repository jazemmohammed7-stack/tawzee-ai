<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Livewire\Auth\Login;
use App\Models\User;
use App\Modules\Access\Actions\InitializeCompanyRoles;
use App\Modules\Access\Enums\DefaultRole;
use App\Modules\Access\Enums\Permission;
use App\Modules\Access\Models\Role;
use App\Modules\Company\Livewire\Settings;
use App\Modules\Company\Models\Company;
use App\Modules\Identity\Livewire\Users;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionMethod;
use Tests\Concerns\InteractsWithTenantIsolation;
use Tests\Support\PermissionSurface;
use Tests\TestCase;

class PermissionMatrixTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithTenantIsolation;

    private User $actor;

    private User $foreignActor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = $this->tenant();
        $this->foreignActor = $this->tenant();
    }

    private function tenant(): User
    {
        $company = Company::factory()->create();
        $founder = User::factory()->for($company)->create()->refresh();
        $company->founder_user_id = $founder->id;
        $company->save();
        app(InitializeCompanyRoles::class)->handle($company);

        // Non-founder so that the matrix cannot accidentally rely on founder privileges.
        return User::factory()->for($company)->create(['password' => 'Matrix-auth-only-password-123'])->refresh();
    }

    private function assign(User $actor, DefaultRole $role): void
    {
        $this->withinTenant($actor, fn () => $actor->fresh()->syncRoles($role->value));
        $actor->unsetRelation('roles')->unsetRelation('permissions');
    }

    private function asSession(User $actor): static
    {
        // Switching test identities must model separate logins, not retain A's session
        // password hash for B and accidentally test AuthenticateSession instead of permissions.
        $this->flushSession();

        return $this->actingAs($actor);
    }

    public static function pages(): iterable
    {
        foreach (PermissionSurface::PAGES as $route => $page) {
            foreach (DefaultRole::cases() as $role) {
                yield $route.' / '.$role->value => [$route, $role];
            }
        }
    }

    #[DataProvider('pages')]
    public function test_http_role_by_route_with_positive_control(string $route, DefaultRole $role): void
    {
        $page = PermissionSurface::PAGES[$route];
        $this->assign($this->actor, PermissionSurface::controlRole($page['permission']));
        $this->asSession($this->actor)->get(route($route))->assertOk();
        $this->assign($this->actor, $role);
        $response = $this->asSession($this->actor)->get(route($route));
        if (PermissionSurface::allowed($role, $page['permission'])) {
            $response->assertOk()->assertSee($this->actor->company->name)
                ->assertDontSee($this->foreignActor->company->name);
        } else {
            $response->assertForbidden();
        }
    }

    public static function actionMatrix(): iterable
    {
        foreach (PermissionSurface::actions() as $label => $surface) {
            foreach (DefaultRole::cases() as $role) {
                foreach (['http', 'component'] as $transport) {
                    yield $label.' / '.$role->value.' / '.$transport => [$surface, $role, $transport];
                }
            }
        }
    }

    #[DataProvider('actionMatrix')]
    public function test_every_livewire_surface_for_every_role(array $surface, DefaultRole $role, string $transport): void
    {
        $permission = PermissionSurface::PAGES[$surface[0]]['permission'];
        // A valid, successful invocation of this exact branch precedes every candidate, including denials.
        $this->exercise($surface, PermissionSurface::controlRole($permission), $transport, true);
        $this->exercise($surface, $role, $transport, PermissionSurface::allowed($role, $permission));
    }

    private function exercise(array $surface, DefaultRole $role, string $transport, bool $allowed): void
    {
        [$route, $method, $scenario] = $surface;
        $page = PermissionSurface::PAGES[$route];
        $this->assign($this->actor, PermissionSurface::controlRole($page['permission']));
        $target = User::factory()->for($this->actor->company)->create(['is_active' => ! str_ends_with($scenario, ':activate')])->refresh();
        $this->assign($target, DefaultRole::Viewer);
        $state = $this->mountSurface($route, $transport);
        $roleId = $this->withinTenant($this->actor, fn () => Role::where('name', DefaultRole::Sales->value)->value('id'));
        $params = match ($method) {
            'openEdit' => [$target->id],
            'confirmStatus' => [$target->id, str_ends_with($scenario, ':activate')],
            'setPage', 'gotoPage' => [2],
            default => [],
        };
        $updates = [];
        if ($method === 'save' && $route === 'users.index') {
            $opening = $scenario === 'save:create' ? 'openCreate' : 'openEdit';
            $state = $this->nextState($this->invoke($state, $opening, $opening === 'openEdit' ? [$target->id] : []));
            $updates = ['name' => 'Matrix '.Str::uuid(), 'email' => $scenario === 'save:create' ? Str::uuid().'@example.test' : $target->email,
                'role_id' => (string) ($scenario === 'save:update'
                    ? $this->withinTenant($target, fn () => $target->fresh()->roles->sole()->id) : $roleId)];
            if ($scenario === 'save:create') {
                $updates += ['password' => 'Matrix-test-only-password-123', 'password_confirmation' => 'Matrix-test-only-password-123'];
            }
        } elseif ($method === 'changeStatus') {
            $state = $this->nextState($this->invoke($state, 'confirmStatus', [$target->id, str_ends_with($scenario, ':activate')]));
        } elseif ($scenario === 'settings') {
            $updates = ['name' => 'Matrix '.Str::uuid(), 'allow_negative_stock' => ! $this->actor->company->fresh()->allow_negative_stock];
        } elseif ($scenario === '$refresh:filters') {
            $updates = ['search' => $target->name, 'statusFilter' => 'active', 'roleFilter' => ''];
        } elseif ($scenario === 'clearFilters') {
            $updates = ['search' => 'probe', 'statusFilter' => 'active'];
        }
        if ($method === 'closeForm') {
            $state = $this->nextState($this->invoke($state, 'openCreate'));
        } elseif ($method === 'cancelStatus') {
            $state = $this->nextState($this->invoke($state, 'confirmStatus', [$target->id, false]));
        } elseif (in_array($method, ['previousPage', 'resetPage', 'getPage', 'queryStringHandlesPagination'], true)) {
            $state = $this->nextState($this->invoke($state, 'setPage', [2]));
        }

        // Same authenticated identity and valid signed state; only its stored role changes.
        // Component transport omits production route middleware and tests the component's own guard.
        $this->assign($this->actor, $role);
        $before = $this->businessState();
        $response = $this->invoke($state, $method, $params, $updates);
        if (! $allowed) {
            $response->assertForbidden();
            $this->assertSame($before, $this->businessState(), 'Denied operation changed business data.');

            return;
        }
        $response->assertOk();
        $data = $this->responseData($response);
        if ($method === 'save' && $route === 'users.index') {
            $stored = User::where('company_id', $this->actor->company_id)->where('email', $updates['email'])->sole();
            $this->assertSame($updates['name'], $stored->name);
            $this->withinTenant($stored, fn () => $this->assertSame((int) $updates['role_id'], $stored->roles->sole()->id));
            $this->assertFalse($data['formOpen']);
        } elseif ($scenario === 'settings') {
            $company = $this->actor->company->fresh();
            $this->assertSame($updates['name'], $company->name);
            $this->assertSame($updates['allow_negative_stock'], $company->allow_negative_stock);
        } elseif ($method === 'changeStatus') {
            $this->assertSame(str_ends_with($scenario, ':activate'), $target->fresh()->is_active);
        } elseif ($method === 'openEdit') {
            $this->assertSame($target->id, $data['editingId']);
        } elseif ($method === 'openCreate') {
            $this->assertTrue($data['formOpen']);
        } elseif ($method === 'confirmStatus') {
            $this->assertSame($target->id, $data['statusId']);
        } elseif ($method === 'closeForm') {
            $this->assertFalse($data['formOpen']);
        } elseif ($method === 'cancelStatus') {
            $this->assertNull($data['statusId']);
        } elseif ($method === 'clearFilters') {
            $this->assertSame('', $data['search']);
        } elseif ($scenario === '$refresh:filters') {
            $this->assertSame($target->name, $data['search']);
        } elseif (in_array($method, ['setPage', 'gotoPage', 'nextPage', 'getPage', 'queryStringHandlesPagination', 'previousPage', 'resetPage'], true)) {
            $paginators = $response instanceof Testable ? $data['paginators'] : $data['paginators'][0];
            $this->assertSame(in_array($method, ['previousPage', 'resetPage'], true) ? 1 : 2, $paginators['page']);
        }
    }

    private function mountSurface(string $route, string $transport = 'http'): Testable|string
    {
        if ($transport === 'component') {
            return $this->withinTenant($this->actor, fn () => Livewire::test(PermissionSurface::PAGES[$route]['component']));
        }
        Livewire::flushState();
        $response = $this->asSession($this->actor)->get(route($route))->assertOk();
        $this->assertSame(1, preg_match('/wire:snapshot="([^"]+)"/', $response->getContent(), $match));

        return html_entity_decode($match[1], ENT_QUOTES);
    }

    private function invoke(Testable|string $state, string $method, array $params = [], array $updates = []): Testable|TestResponse
    {
        if ($state instanceof Testable) {
            return $this->withinTenant($this->actor, function () use ($state, $method, $params, $updates) {
                // One request, as in a deferred wire:model form; no intermediate update request can mask the action.
                return $state->update([['method' => $method, 'params' => $params, 'path' => '']], $updates);
            });
        }

        // PHPUnit reuses the app across HTTP requests. Mirror Livewire's InitialRender/
        // SubsequentRender boundary so middleware from a previous request is never cached here.
        Livewire::flushState();

        return $this->withHeader('X-Livewire', 'true')->postJson(app('livewire')->getUpdateUri(), [
            'components' => [['snapshot' => $state, 'updates' => $updates,
                'calls' => [['path' => '', 'method' => $method, 'params' => $params]]]],
        ]);
    }

    private function nextState(Testable|TestResponse $response): Testable|string
    {
        $response->assertOk();

        return $response instanceof Testable ? $response : $response->json('components.0.snapshot');
    }

    private function responseData(Testable|TestResponse $response): array
    {
        if ($response instanceof Testable) {
            $response->assertHasNoErrors();

            return $response->instance()->all();
        }
        $snapshot = json_decode($response->json('components.0.snapshot'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertEmpty($snapshot['memo']['errors'] ?? []);

        return $snapshot['data'];
    }

    private function businessState(): array
    {
        $ids = [$this->actor->company_id, $this->foreignActor->company_id];

        // Test-only raw reads include both companies explicitly, independent of current auth context.
        return [
            'companies' => DB::table('companies')->whereIn('id', $ids)->orderBy('id')->get()->toJson(),
            'users' => DB::table('users')->whereIn('company_id', $ids)->orderBy('id')->get()->toJson(),
            'roles' => DB::table('model_has_roles')->whereIn('company_id', $ids)->orderBy('model_id')->orderBy('role_id')->get()->toJson(),
            'audit' => DB::table('activity_logs')->whereIn('company_id', $ids)->orderBy('id')->get()->toJson(),
        ];
    }

    public function test_route_inventory_detects_new_or_weakened_application_routes(): void
    {
        Artisan::call('route:list', ['--except-vendor' => true, '--json' => true]);
        $actual = collect(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR));
        $expected = array_merge(array_keys(PermissionSurface::OTHER_ROUTES), array_keys(PermissionSurface::PAGES));
        $this->assertEqualsCanonicalizing($expected, $actual->pluck('name')->all(), 'Classify and matrix-test every new application route.');
        foreach ($actual as $item) {
            $route = Route::getRoutes()->getByName($item['name']);
            $middleware = $route->gatherMiddleware();
            if (isset(PermissionSurface::PAGES[$item['name']])) {
                $page = PermissionSurface::PAGES[$item['name']];
                $this->assertSame('GET|HEAD', $item['method']);
                $this->assertSame($page['uri'], $item['uri']);
                foreach (['auth', 'tenant', $page['ability']] as $required) {
                    $this->assertContains($required, $middleware);
                }
            } else {
                [$method, $uri, $type] = PermissionSurface::OTHER_ROUTES[$item['name']];
                $this->assertSame([$method, $uri], [$item['method'], $item['uri']]);
                $this->assertEmpty(array_filter($middleware, fn ($value) => str_starts_with($value, 'can:')));
                if ($type === 'guest') {
                    $this->assertContains('guest', $middleware);
                }
                if (in_array($type, ['auth', 'tenant'], true)) {
                    $this->assertContains('auth', $middleware);
                }
                if ($type === 'tenant') {
                    $this->assertContains('tenant', $middleware);
                }
            }
            $this->assertNotContains('company.ready', $middleware, 'Readiness is not required by any current production route.');
        }
    }

    public function test_component_inventory_detects_new_components_and_unmapped_public_methods(): void
    {
        $found = [];
        foreach (File::allFiles(app_path()) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $class = 'App\\'.str_replace(['/', '\\'], '\\', substr($file->getRelativePathname(), 0, -4));
            if (class_exists($class) && is_subclass_of($class, Component::class)) {
                $found[] = $class;
            }
        }
        $expected = array_merge(array_keys(PermissionSurface::NON_PERMISSION_COMPONENTS), [Users::class, Settings::class]);
        $this->assertEqualsCanonicalizing($expected, $found, 'Classify every new production component.');
        foreach ($found as $class) {
            // Framework authorization helpers are not application actions. Pagination methods are
            // intentionally retained: they are callable UI operations and must pass the same matrix.
            $authorizationTrait = (new ReflectionClass(AuthorizesRequests::class))->getFileName();
            $methods = array_map(fn ($method) => $method->name, array_filter(
                (new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC),
                fn ($method) => $method->getDeclaringClass()->name === $class && $method->getFileName() !== $authorizationTrait,
            ));
            if (isset(PermissionSurface::NON_PERMISSION_COMPONENTS[$class])) {
                $expectedMethods = PermissionSurface::NON_PERMISSION_COMPONENTS[$class];
            } else {
                $route = $class === Users::class ? 'users.index' : 'company.settings';
                $mapped = array_column(array_filter(PermissionSurface::actions(), fn ($surface) => $surface[0] === $route), 1);
                $expectedMethods = array_unique(array_merge(array_filter($mapped, fn ($method) => ! str_starts_with($method, '$')),
                    $class === Users::class ? ['mount', 'hydrate', 'updated', 'render'] : ['mount', 'hydrate', 'render']));
            }
            $this->assertEqualsCanonicalizing($expectedMethods, $methods, $class.' has an unreviewed public method.');
        }
    }

    public function test_matrix_matches_project_and_persisted_roles_without_a_second_manual_matrix(): void
    {
        $this->assertCount(19, Permission::cases());
        $this->assertCount(6, DefaultRole::cases());
        $matrixRows = [];
        foreach (file(base_path('PROJECT.md')) as $line) {
            if (preg_match('/^\| `([a-z_]+\.[a-z_]+)`[^|]*\|(.*)\|\s*$/u', $line, $match)) {
                $matrixRows[$match[1]] = array_map('trim', explode('|', $match[2]));
            }
        }
        $this->assertEqualsCanonicalizing(Permission::values(), array_keys($matrixRows));
        // Read role column order from the actual document rather than assuming enum order.
        preg_match('/^\| الصلاحية \|(.*)\|\s*$/mu', file_get_contents(base_path('PROJECT.md')), $header);
        $roles = array_map('trim', explode('|', $header[1]));
        $this->assertEqualsCanonicalizing(DefaultRole::values(), $roles);
        foreach (DefaultRole::cases() as $role) {
            $column = array_search($role->value, $roles, true);
            foreach (Permission::cases() as $permission) {
                $this->assertSame($matrixRows[$permission->value][$column] === '✓', PermissionSurface::allowed($role, $permission), $role->value.' / '.$permission->value);
            }
        }
        $this->assertEqualsCanonicalizing(Permission::values(), DB::table('permissions')->pluck('name')->all());
        foreach ([$this->actor, $this->foreignActor] as $actor) {
            $this->withinTenant($actor, function (): void {
                $roles = Role::with('permissions')->get();
                $this->assertCount(6, $roles);
                $this->assertEqualsCanonicalizing(DefaultRole::values(), $roles->pluck('name')->all());
                foreach ($roles as $role) {
                    $this->assertEqualsCanonicalizing(array_column(DefaultRole::from($role->name)->permissions(), 'value'), $role->permissions->pluck('name')->all());
                }
            });
        }
    }

    public static function roles(): iterable
    {
        foreach (DefaultRole::cases() as $role) {
            yield $role->value => [$role];
        }
    }

    #[DataProvider('roles')]
    public function test_public_authentication_and_pending_routes_do_not_require_business_permissions(DefaultRole $role): void
    {
        $this->assign($this->actor, $role);
        // Guest positive controls: these endpoints exist and render before redirect/denial assertions.
        foreach (['/', '/register', '/login', '/forgot-password', '/reset-password/test-token'] as $url) {
            $this->get($url)->assertOk();
        }
        Livewire::test(Login::class)->set('email', $this->actor->email)->set('password', 'Matrix-auth-only-password-123')
            ->call('login')->assertHasNoErrors()->assertRedirect(route('setup.pending'));
        $this->assertAuthenticatedAs($this->actor);
        $this->get('/')->assertOk();
        $this->get('/pending-setup')->assertOk();
        $this->assertSame('pending_setup', $this->actor->company->fresh()->status);
        foreach (['/login', '/forgot-password', '/reset-password/test-token'] as $url) {
            $this->get($url)->assertRedirect(route('setup.pending'));
        }
        $this->get('/register')->assertForbidden();
        $this->post('/logout')->assertRedirect(route('home'));
        $this->assertGuest();
        foreach (['/users', '/company/settings', '/pending-setup'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
        $this->post('/logout')->assertRedirect(route('login'));
    }

    public static function authorizedPages(): iterable
    {
        foreach (self::pages() as $label => [$route, $role]) {
            if (PermissionSurface::allowed($role, PermissionSurface::PAGES[$route]['permission'])) {
                yield $label => [$route, $role];
            }
        }
    }

    #[DataProvider('authorizedPages')]
    public function test_authorized_tenants_are_independent_and_foreign_snapshots_and_selection_fail(string $route, DefaultRole $role): void
    {
        $this->assign($this->actor, $role);
        $this->assign($this->foreignActor, $role);
        $snapshotA = $this->mountSurface($route);
        $method = $route === 'users.index' ? 'openCreate' : 'save';
        $updates = $route === 'users.index' ? [] : ['name' => 'Own A '.Str::uuid()];
        $this->invoke($snapshotA, $method, [], $updates)->assertOk();
        $before = $this->businessState();
        $url = route($route);
        $this->get($url.'?company_id='.$this->foreignActor->company_id)->assertForbidden();
        $this->withHeader('X-Company-Id', (string) $this->foreignActor->company_id)->get($url)->assertForbidden();
        $this->flushHeaders();
        $this->withCookie('company_id', (string) $this->foreignActor->company_id)->get($url)->assertForbidden();
        $this->defaultCookies = [];
        $this->invoke($snapshotA, $method, [], ['company_id' => $this->foreignActor->company_id] + $updates)->assertForbidden();
        $this->assertSame($before, $this->businessState());

        // Same role in B succeeds for B, but its real session cannot replay A's signed state.
        $actorA = $this->actor;
        $this->actor = $this->foreignActor;
        $snapshotB = $this->mountSurface($route);
        $updatesB = $route === 'users.index' ? [] : ['name' => 'Own B '.Str::uuid()];
        $this->invoke($snapshotB, $method, [], $updatesB)->assertOk();
        $this->actor = $actorA;
        $before = $this->businessState();
        $this->invoke($snapshotA, $method, [], $updates)->assertForbidden();
        $this->assertSame($before, $this->businessState());
        if ($route === 'company.settings') {
            $this->assertSame($updates['name'], $actorA->company->fresh()->name);
            $this->assertSame($updatesB['name'], $this->foreignActor->company->fresh()->name);
        }
    }

    public static function authorizedUserRoles(): iterable
    {
        foreach (DefaultRole::cases() as $role) {
            if (PermissionSurface::allowed($role, Permission::UsersManage)) {
                yield $role->value => [$role];
            }
        }
    }

    #[DataProvider('authorizedUserRoles')]
    public function test_foreign_user_ids_are_404_with_own_and_foreign_positive_controls(DefaultRole $role): void
    {
        $this->assign($this->actor, $role);
        $this->assign($this->foreignActor, $role);
        $own = User::factory()->for($this->actor->company)->create()->refresh();
        $foreign = User::factory()->for($this->foreignActor->company)->create()->refresh();
        $this->assign($own, DefaultRole::Viewer);
        $this->assign($foreign, DefaultRole::Viewer);
        $snapshot = $this->mountSurface('users.index');
        foreach (['openEdit', 'confirmStatus'] as $method) {
            $ownParams = $method === 'openEdit' ? [$own->id] : [$own->id, false];
            $foreignParams = $method === 'openEdit' ? [$foreign->id] : [$foreign->id, false];
            $this->invoke($snapshot, $method, $ownParams)->assertOk();
            $before = $this->businessState();
            $this->invoke($snapshot, $method, $foreignParams)->assertNotFound();
            $this->assertSame($before, $this->businessState());
        }
        $this->actor = $this->foreignActor;
        $snapshotB = $this->mountSurface('users.index');
        $this->invoke($snapshotB, 'openEdit', [$foreign->id])->assertOk();
        $this->invoke($snapshotB, 'confirmStatus', [$foreign->id, false])->assertOk();
    }

    public function test_both_page_policies_fail_closed_without_context_after_positive_controls(): void
    {
        foreach (PermissionSurface::PAGES as $page) {
            $this->assign($this->actor, PermissionSurface::controlRole($page['permission']));
            [$ability, $model] = explode(',', substr($page['ability'], 4));
            $this->withinTenant($this->actor, fn () => $this->assertTrue(Gate::forUser($this->actor)->allows($ability, $model)));
            Auth::forgetGuards();
            $this->assertFalse(Gate::forUser($this->actor)->allows($ability, $model));
        }
    }

    #[DataProvider('authorizedPages')]
    public function test_permission_revocation_is_team_local_and_role_name_cannot_bypass_it(string $route, DefaultRole $role): void
    {
        $permission = PermissionSurface::PAGES[$route]['permission'];
        $this->assign($this->actor, $role);
        $this->assign($this->foreignActor, $role);
        $snapshot = $this->mountSurface($route);
        $method = $route === 'users.index' ? 'openCreate' : 'save';
        $this->invoke($snapshot, $method)->assertOk();
        $this->asSession($this->foreignActor)->get(route($route))->assertOk();
        $this->withinTenant($this->actor, fn () => Role::where('name', $role->value)->sole()->revokePermissionTo($permission->value));
        $this->withinTenant($this->actor, fn () => $this->assertSame([$role->value], $this->actor->fresh()->getRoleNames()->all()));
        $this->asSession($this->actor)->get(route($route))->assertForbidden();
        $before = $this->businessState();
        $this->invoke($snapshot, $method)->assertForbidden();
        $this->assertSame($before, $this->businessState());
        $this->asSession($this->foreignActor)->get(route($route))->assertOk();
        // A direct permission restores access even though the role's grant remains revoked.
        $this->withinTenant($this->actor, fn () => $this->actor->fresh()->givePermissionTo($permission->value));
        $this->asSession($this->actor)->get(route($route))->assertOk();
        $this->invoke($snapshot, $method)->assertOk();
        Auth::forgetGuards();
        $before = $this->businessState();
        $this->invoke($snapshot, $method)->assertUnauthorized();
        $this->assertSame($before, $this->businessState());
    }
}
