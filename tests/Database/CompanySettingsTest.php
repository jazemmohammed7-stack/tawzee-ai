<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\User;
use App\Modules\Access\Actions\InitializeCompanyRoles;
use App\Modules\Access\Enums\Permission;
use App\Modules\Access\Models\ActivityLog;
use App\Modules\Access\Models\Role;
use App\Modules\Company\Actions\UpdateCompanySettings;
use App\Modules\Company\Livewire\Settings;
use App\Modules\Company\Models\Company;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\InteractsWithTenantIsolation;
use Tests\TestCase;

class CompanySettingsTest extends TestCase
{
    use DatabaseTransactions, InteractsWithTenantIsolation;

    private User $ownerA;

    private User $ownerB;

    private User $memberA;

    private Company $a;

    private Company $b;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->a, $this->ownerA] = $this->fixture('شركة ألف');
        [$this->b, $this->ownerB] = $this->fixture('شركة باء');
        $this->memberA = User::factory()->for($this->a)->create()->refresh();
        $this->withinTenant($this->ownerA, fn () => $this->memberA->assignRole('viewer'));
    }

    private function fixture(string $name): array
    {
        $company = Company::factory()->create(['name' => $name])->refresh();
        $owner = User::factory()->for($company)->create()->refresh();
        $company->founder_user_id = $owner->id;
        $company->save();
        app(InitializeCompanyRoles::class)->handle($company);

        return [$company, $owner];
    }

    private function update(array $input): Company
    {
        return app(UpdateCompanySettings::class)->handle($input);
    }

    private function input(array $overrides = []): array
    {
        return array_replace(['name' => 'اسم جديد', 'allow_negative_stock' => false], $overrides);
    }

    public function test_existing_schema_has_boolean_setting_default_false(): void
    {
        $this->assertTrue(Schema::hasColumn('companies', 'allow_negative_stock'));
        $this->assertFalse($this->a->allow_negative_stock);
        $this->assertFalse($this->b->allow_negative_stock);
    }

    public function test_authorized_page_displays_only_current_company_and_uses_existing_shell(): void
    {
        $this->actingAs($this->ownerA)->get('/company/settings')->assertOk()
            ->assertSee('dir="rtl"', false)->assertSee($this->a->name)->assertDontSee($this->b->name)
            ->assertSee('id="mobile-navigation"', false)->assertSee('role="switch"', false)
            ->assertSee(route('users.index'))->assertSee(route('company.settings'));
        $this->actingAs($this->ownerB)->get('/company/settings')->assertOk()
            ->assertSee($this->b->name)->assertDontSee($this->a->name);
        Auth::forgetGuards();
        $this->get('/company/settings')->assertRedirect(route('login'));
        $this->assertSame('pending_setup', $this->a->fresh()->status);
    }

    public static function unauthorizedRoles(): array
    {
        return [['sales'], ['warehouse'], ['accountant'], ['viewer']];
    }

    #[DataProvider('unauthorizedRoles')]
    public function test_unauthorized_roles_cannot_view_or_update(string $role): void
    {
        $this->withinTenant($this->ownerA, fn () => $this->memberA->syncRoles($role));
        $this->actingAs($this->memberA)->get('/company/settings')->assertForbidden();
        $this->withinTenant($this->memberA, function (): void {
            Livewire::test(Settings::class)->assertForbidden();
            try {
                $this->update($this->input());
                $this->fail('Unauthorized write accepted.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
            $this->assertSame(0, ActivityLog::count());
        });
        $this->assertSame('شركة ألف', $this->a->fresh()->name);
    }

    public function test_admin_can_save_but_permission_not_role_name_is_the_authority(): void
    {
        $this->withinTenant($this->ownerA, fn () => $this->memberA->syncRoles('admin'));
        $this->withinTenant($this->memberA, fn () => $this->update($this->input()));
        $this->assertSame('اسم جديد', $this->a->fresh()->name);
        $this->withinTenant($this->ownerA, function (): void {
            $this->memberA->syncRoles('viewer');
            $this->memberA->givePermissionTo(Permission::CompanySettings->value);
            Role::where('name', 'owner')->firstOrFail()->revokePermissionTo(Permission::CompanySettings->value);
            $this->assertFalse(Gate::forUser($this->ownerA)->allows('viewSettings', Company::class));
        });
        $this->actingAs($this->memberA)->get('/company/settings')->assertOk()->assertDontSee(route('users.index'));
        $this->withinTenant($this->memberA, fn () => $this->update($this->input(['name' => 'مخوّل بالصلاحية'])));
        $this->assertSame('مخوّل بالصلاحية', $this->a->fresh()->name);
    }

    public static function changeCases(): array
    {
        return [
            'name only' => [['name' => '  شركة معدلة  ', 'allow_negative_stock' => false], ['name' => ['before' => 'شركة ألف', 'after' => 'شركة معدلة']]],
            'setting only' => [['name' => 'شركة ألف', 'allow_negative_stock' => true], ['allow_negative_stock' => ['before' => false, 'after' => true]]],
            'both' => [['name' => 'شركة معدلة', 'allow_negative_stock' => true], ['name' => ['before' => 'شركة ألف', 'after' => 'شركة معدلة'], 'allow_negative_stock' => ['before' => false, 'after' => true]]],
        ];
    }

    #[DataProvider('changeCases')]
    public function test_actual_changes_and_minimal_audit_are_atomic_and_tenant_owned(array $input, array $changes): void
    {
        $foreign = $this->b->getAttributes();
        $this->withinTenant($this->ownerA, function () use ($input, $changes): void {
            $saved = $this->update($input)->fresh();
            $this->assertSame(trim($input['name']), $saved->name);
            $this->assertSame($input['allow_negative_stock'], $saved->allow_negative_stock);
            $log = ActivityLog::sole();
            $this->assertSame('company.settings.updated', $log->action);
            $this->assertSame($this->a->id, $log->company_id);
            $this->assertSame($this->ownerA->id, $log->user_id);
            $this->assertSame($this->a->getMorphClass(), $log->subject_type);
            $this->assertSame($this->a->id, $log->subject_id);
            $this->assertSame(['changes' => $changes], $log->properties);
            $this->assertStringNotContainsString($this->ownerA->email, $log->toJson());
        });
        $this->assertSame($foreign, $this->b->fresh()->getAttributes());
        $this->withinTenant($this->ownerB, function (): void {
            $this->assertSame(0, ActivityLog::count());
            $this->update(['name' => 'شركة باء معدلة', 'allow_negative_stock' => true]);
            $this->assertSame($this->b->id, ActivityLog::sole()->subject_id);
        });
        $this->assertSame(trim($input['name']), $this->a->fresh()->name);
    }

    public function test_disable_setting_and_noop_or_repeated_save_do_not_create_false_activity(): void
    {
        $this->withinTenant($this->ownerA, function (): void {
            $before = $this->a->fresh()->updated_at;
            $this->travel(5)->minutes();
            $unchanged = $this->update(['name' => '  شركة ألف  ', 'allow_negative_stock' => false]);
            $this->assertFalse($unchanged->wasChanged());
            $this->assertEquals($before, $unchanged->updated_at);
            $this->assertSame(0, ActivityLog::count());
            $this->update(['name' => 'شركة ألف', 'allow_negative_stock' => true]);
            $this->update(['name' => 'شركة ألف', 'allow_negative_stock' => false]);
            $this->assertFalse($this->a->fresh()->allow_negative_stock);
            $this->assertSame(['changes' => ['allow_negative_stock' => ['before' => true, 'after' => false]]], ActivityLog::latest('id')->first()->properties);
            $this->update(['name' => 'شركة ألف', 'allow_negative_stock' => false]);
            $this->assertSame(2, ActivityLog::count());
        });
    }

    public static function invalidInputs(): array
    {
        return [
            'empty' => [['name' => '   '], 'name'],
            'null name' => [['name' => null], 'name'],
            'array name' => [['name' => ['forged']], 'name'],
            'long name' => [['name' => str_repeat('ش', 256)], 'name'],
            'false string' => [['allow_negative_stock' => 'false'], 'allow_negative_stock'],
            'array toggle' => [['allow_negative_stock' => [true]], 'allow_negative_stock'],
            'integer two' => [['allow_negative_stock' => 2], 'allow_negative_stock'],
            'null toggle' => [['allow_negative_stock' => null], 'allow_negative_stock'],
            'company id' => [['company_id' => 123], 'settings'],
            'id' => [['id' => 123], 'settings'],
            'status' => [['status' => 'active'], 'settings'],
            'other setting' => [['currency_code' => 'USD'], 'settings'],
            'secret payload' => [['token' => 'private-probe'], 'settings'],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_or_extra_fields_cannot_persist_or_be_audited(array $overrides, string $field): void
    {
        $this->withinTenant($this->ownerA, function () use ($overrides, $field): void {
            try {
                $this->update($this->input($overrides));
                $this->fail('Invalid input accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($field, $exception->errors());
            }
            $this->assertSame(0, ActivityLog::count());
            $this->assertSame('شركة ألف', $this->a->fresh()->name);
            $this->assertFalse($this->a->fresh()->allow_negative_stock);
        });
    }

    public function test_missing_context_and_cross_company_or_dirty_model_fail_closed(): void
    {
        Auth::forgetGuards();
        $this->assertFalse(Gate::forUser($this->ownerA)->allows('viewSettings', Company::class));
        try {
            $this->update($this->input());
            $this->fail('Missing context accepted.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }
        $this->withinTenant($this->ownerA, function (): void {
            $this->assertTrue(Gate::allows('updateSettings', $this->a));
            $this->assertFalse(Gate::allows('updateSettings', $this->b));
            $this->b->id = $this->a->id;
            $this->assertFalse(Gate::allows('updateSettings', $this->b));
        });
    }

    public static function persistenceFailures(): array
    {
        return [['company-cancel'], ['audit-cancel'], ['audit-throw'], ['after-audit-save']];
    }

    #[DataProvider('persistenceFailures')]
    public function test_failure_rolls_back_company_and_audit_together(string $failure): void
    {
        $this->withinTenant($this->ownerA, function () use ($failure): void {
            $before = $this->a->fresh()->getAttributes();
            $event = match ($failure) {
                'company-cancel' => 'eloquent.updating: '.Company::class,
                'after-audit-save' => 'eloquent.created: '.ActivityLog::class,
                default => 'eloquent.creating: '.ActivityLog::class,
            };
            Event::listen($event, fn () => str_ends_with($failure, 'cancel') ? false : throw new RuntimeException('Expected settings audit failure.'));
            try {
                $this->update($this->input(['allow_negative_stock' => true]));
                $this->fail('Persistence failure swallowed.');
            } catch (RuntimeException $exception) {
                $this->assertSame(match ($failure) {
                    'company-cancel' => 'Company settings persistence was cancelled.',
                    'audit-cancel' => 'Company settings activity persistence was cancelled.',
                    default => 'Expected settings audit failure.',
                }, $exception->getMessage());
            }
            $this->assertSame($before, $this->a->fresh()->getAttributes());
            $this->assertSame(0, ActivityLog::count());
        });
    }

    public function test_livewire_validates_retains_input_and_dispatches_committed_name_to_shell(): void
    {
        $this->withinTenant($this->ownerA, function (): void {
            $component = Livewire::test(Settings::class)->assertSet('name', $this->a->name)->assertSet('allow_negative_stock', false)
                ->set('name', '   ')->set('allow_negative_stock', true)->call('save')
                ->assertHasErrors(['name'])->assertSet('allow_negative_stock', true)->assertSee('حقل اسم الشركة مطلوب.');
            $component->set('name', '  اسم الشركة المحدث  ')->call('save')->assertHasNoErrors()
                ->assertSet('savedName', 'اسم الشركة المحدث')->assertSet('savedNegativeStock', true)
                ->assertDispatched('company-name-updated', name: 'اسم الشركة المحدث')
                ->assertDispatched('toast', type: 'success', message: __('company_settings.saved'));
            $component->call('save')->assertDispatched('toast', type: 'success', message: __('company_settings.unchanged'));
            $this->assertSame(1, ActivityLog::count());
        });
        $this->actingAs($this->ownerA)->get('/company/settings')->assertOk()->assertSee('اسم الشركة المحدث')->assertDontSee('شركة ألف');
        $this->get('/users')->assertOk()->assertSee('اسم الشركة المحدث');
    }

    public function test_livewire_rejects_forged_boolean_and_hides_unexpected_exception_text(): void
    {
        $this->withinTenant($this->ownerA, function (): void {
            $component = Livewire::test(Settings::class)->set('allow_negative_stock', 'false')->call('save')->assertHasErrors(['allow_negative_stock']);
            Event::listen('eloquent.creating: '.ActivityLog::class, fn () => throw new RuntimeException('secret-settings-probe'));
            $component->set('allow_negative_stock', true)->set('name', 'مدخل محفوظ')->call('save')
                ->assertDispatched('toast', type: 'error', message: __('company_settings.failed'))
                ->assertNotDispatched('company-name-updated')->assertSet('name', 'مدخل محفوظ')->assertDontSee('secret-settings-probe');
            $this->assertSame('شركة ألف', $this->a->fresh()->name);
        });
    }

    private function snapshot(User $user): string
    {
        $response = $this->actingAs($user)->get('/company/settings')->assertOk();
        preg_match('/wire:snapshot="([^"]+)"/', $response->getContent(), $match);

        return html_entity_decode($match[1], ENT_QUOTES);
    }

    private function postSnapshot(string $snapshot, array $updates = []): TestResponse
    {
        return $this->withHeader('X-Livewire', 'true')->postJson(app('livewire')->getUpdateUri(), [
            'components' => [['snapshot' => $snapshot, 'updates' => $updates,
                'calls' => [['path' => '', 'method' => 'save', 'params' => []]]]],
        ]);
    }

    public function test_http_company_injection_in_query_body_header_cookie_and_path_cannot_switch_tenant(): void
    {
        $snapshot = $this->snapshot($this->ownerA);
        $this->get('/company/settings?company_id='.$this->b->id)->assertForbidden();
        $this->withHeader('X-Company-Id', (string) $this->b->id)->get('/company/settings')->assertForbidden();
        $this->flushHeaders();
        $this->withCookie('company_id', (string) $this->b->id)->get('/company/settings')->assertForbidden();
        $this->defaultCookies = [];
        $this->get('/company/settings/'.$this->b->id)->assertNotFound();
        $this->postSnapshot($snapshot, ['company_id' => $this->b->id, 'name' => 'forged'])->assertForbidden();
        $this->postSnapshot($snapshot, ['name' => 'شركة ألف فقط'])->assertOk();
        $this->assertSame('شركة ألف فقط', $this->a->fresh()->name);
        $this->assertSame('شركة باء', $this->b->fresh()->name);
    }

    public function test_stale_snapshot_cannot_be_reused_by_another_identity_or_company(): void
    {
        $snapshot = $this->snapshot($this->ownerA);
        $this->actingAs($this->ownerB);
        $this->postSnapshot($snapshot, ['name' => 'forged'])->assertForbidden();
        $fresh = $this->snapshot($this->ownerB);
        $this->postSnapshot($fresh, ['name' => 'اسم باء فقط'])->assertOk();
        $this->assertSame('شركة ألف', $this->a->fresh()->name);
        $this->assertSame('اسم باء فقط', $this->b->fresh()->name);
        $this->withinTenant($this->ownerA, fn () => $this->memberA->syncRoles('admin'));
        $this->actingAs($this->memberA);
        $this->postSnapshot($snapshot)->assertForbidden();
    }

    public function test_same_identity_moved_to_another_company_cannot_reuse_snapshot(): void
    {
        $this->withinTenant($this->ownerA, fn () => $this->memberA->syncRoles('admin'));
        $snapshot = $this->snapshot($this->memberA);
        User::where('company_id', $this->a->id)->whereKey($this->memberA->id)->update(['company_id' => $this->b->id]);
        $moved = $this->memberA->fresh();
        $this->withinTenant($moved, fn () => $moved->syncRoles('admin'));
        $this->actingAs($moved);
        $this->postSnapshot($snapshot)->assertForbidden();
        $this->postSnapshot($this->snapshot($moved))->assertOk();
    }

    public function test_permission_revocation_and_disabled_actor_are_rechecked(): void
    {
        $snapshot = $this->snapshot($this->ownerA);
        $this->withinTenant($this->ownerA, function (): void {
            $this->assertTrue($this->ownerA->hasPermissionTo(Permission::CompanySettings->value));
            $this->ownerA->fresh()->syncRoles('viewer');
            try {
                $this->update($this->input());
                $this->fail('Stale permission accepted.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        });
        $this->postSnapshot($snapshot, ['name' => 'forged'])->assertForbidden();
        $this->withinTenant($this->ownerA, fn () => $this->ownerA->fresh()->syncRoles('owner'));
        User::where('company_id', $this->a->id)->whereKey($this->ownerA->id)->update(['is_active' => false]);
        $this->postSnapshot($snapshot)->assertUnauthorized();
        $this->withinTenant($this->ownerA, function (): void {
            $this->expectException(AuthorizationException::class);
            $this->update($this->input());
        });
    }

    public function test_locked_context_and_baseline_cannot_be_changed_by_client(): void
    {
        $snapshot = $this->snapshot($this->ownerA);
        foreach (['contextKey' => hash('sha256', $this->ownerB->id.':'.$this->b->id), 'savedName' => 'forged', 'savedNegativeStock' => true] as $property => $value) {
            $this->withoutExceptionHandling();
            try {
                $this->postSnapshot($snapshot, [$property => $value]);
                $this->fail('Locked property changed.');
            } catch (CannotUpdateLockedPropertyException $exception) {
                $this->assertStringContainsString($property, $exception->getMessage());
            } finally {
                $this->withExceptionHandling();
            }
        }
    }

    public function test_http_validation_and_escaped_company_name_remain_safe(): void
    {
        $snapshot = $this->snapshot($this->ownerA);
        $response = $this->postSnapshot($snapshot, ['name' => ''])->assertOk();
        $this->assertStringContainsString('حقل اسم الشركة مطلوب.', $response->json('components.0.effects.html'));
        $this->assertStringContainsString('aria-invalid="true"', $response->json('components.0.effects.html'));
        $this->withinTenant($this->ownerA, fn () => $this->update($this->input(['name' => '<script>alert(1)</script>'])));
        $this->get('/company/settings')->assertOk()->assertDontSeeHtml('<script>alert(1)</script>')->assertSee('&lt;script&gt;', false);
    }
}
