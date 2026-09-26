<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\User;
use App\Modules\Access\Actions\InitializeCompanyRoles;
use App\Modules\Access\Models\ActivityLog;
use App\Modules\Access\Models\Role;
use App\Modules\Company\Models\Company;
use App\Modules\Identity\Actions\ChangeUserStatus;
use App\Modules\Identity\Actions\CreateUser;
use App\Modules\Identity\Actions\LoginUser;
use App\Modules\Identity\Actions\UpdateUser;
use App\Modules\Identity\Livewire\Users;
use App\Modules\Identity\Support\CompanyUsers;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\InteractsWithTenantIsolation;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use DatabaseTransactions, InteractsWithTenantIsolation;

    private User $owner;

    private User $member;

    private User $foreignOwner;

    private User $foreignMember;

    private array $roles;

    private array $foreignRoles;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->owner, $this->member, $this->roles] = $this->fixtures('ألف');
        [$this->foreignOwner, $this->foreignMember, $this->foreignRoles] = $this->fixtures('باء');
    }

    private function fixtures(string $name): array
    {
        $company = Company::factory()->create(['name' => 'شركة '.$name]);
        $owner = User::factory()->for($company)->create(['name' => 'مؤسس '.$name])->refresh();
        $member = User::factory()->for($company)->create(['name' => 'عضو '.$name])->refresh();
        $company->founder_user_id = $owner->id;
        $company->save();
        app(InitializeCompanyRoles::class)->handle($company);
        $roles = $this->withinTenant($owner, function () use ($member): array {
            $member->assignRole('viewer');

            return Role::pluck('id', 'name')->all();
        });

        return [$owner, $member, $roles];
    }

    private function input(array $overrides = []): array
    {
        return array_replace(['name' => 'مستخدم جديد', 'email' => 'new-user@example.test',
            'password' => 'Test-only-password-123', 'password_confirmation' => 'Test-only-password-123',
            'role_id' => $this->roles['sales']], $overrides);
    }

    private function editInput(array $overrides = []): array
    {
        return array_replace(['name' => 'اسم معدل', 'email' => $this->member->email, 'role_id' => $this->roles['accountant']], $overrides);
    }

    private function asOwner(\Closure $callback): mixed
    {
        return $this->withinTenant($this->owner, $callback);
    }

    public function test_authorized_http_list_is_arabic_and_isolated_with_positive_controls(): void
    {
        $this->actingAs($this->owner)->get('/users')->assertOk()
            ->assertSee('dir="rtl"', false)->assertSee($this->member->email)
            ->assertDontSee($this->foreignMember->email)->assertSee(__('users.title'));
        $this->actingAs($this->foreignOwner)->get('/users')->assertOk()
            ->assertSee($this->foreignMember->email)->assertDontSee($this->member->email);
        $this->actingAs($this->member)->get('/users')->assertForbidden();
        Auth::forgetGuards();
        $this->get('/users')->assertRedirect(route('login'));
    }

    public function test_admin_can_manage_and_pending_setup_is_not_commercial_activation(): void
    {
        $this->asOwner(fn () => $this->member->syncRoles('admin'));
        $this->actingAs($this->member)->get('/users')->assertOk();
        $this->withinTenant($this->member, fn () => app(CreateUser::class)->handle($this->input()));
        $this->assertSame('pending_setup', $this->owner->company->fresh()->status);
    }

    public static function deniedRoles(): array
    {
        return array_map(fn ($role) => [$role], ['sales', 'warehouse', 'accountant', 'viewer']);
    }

    #[DataProvider('deniedRoles')]
    public function test_each_unauthorized_role_is_denied_list_and_all_actions(string $role): void
    {
        $this->asOwner(fn () => $this->member->syncRoles($role));
        $this->actingAs($this->member)->get('/users')->assertForbidden();
        $before = $this->member->fresh()->getAttributes();
        $this->withinTenant($this->member, function (): void {
            foreach ([fn () => app(CreateUser::class)->handle($this->input()),
                fn () => app(UpdateUser::class)->handle($this->member->id, $this->editInput()),
                fn () => app(ChangeUserStatus::class)->handle($this->member->id, false)] as $operation) {
                try {
                    $operation();
                    $this->fail('Expected authorization rejection.');
                } catch (AuthorizationException) {
                    $this->addToAssertionCount(1);
                }
            }
        });
        $this->assertSame($before, $this->member->fresh()->getAttributes());
        $this->assertDatabaseMissing('users', ['email' => 'new-user@example.test']);
    }

    public function test_create_normalizes_hashes_assigns_exact_role_and_audits_without_secrets(): void
    {
        $this->asOwner(function (): void {
            $created = app(CreateUser::class)->handle($this->input(['email' => ' NEW-USER@EXAMPLE.TEST ']));
            $this->assertSame($this->owner->company_id, $created->company_id);
            $this->assertSame('new-user@example.test', $created->email);
            $this->assertTrue(Hash::check('Test-only-password-123', $created->password));
            $this->assertTrue($created->is_active);
            $this->assertSame(['sales'], $created->roles->pluck('name')->all());
            $log = ActivityLog::where('subject_id', $created->id)->sole();
            $this->assertSame('user.role_changed', $log->action);
            $this->assertSame(['previous_role_ids' => [], 'role_id' => $this->roles['sales']], $log->properties);
            $this->assertSame($this->owner->id, $log->user_id);
            $this->assertStringNotContainsString('password', $log->toJson());
            $this->assertStringNotContainsString($created->email, $log->toJson());
        });
    }

    public static function invalidInputs(): array
    {
        return [
            'blank name' => [['name' => '  '], 'name'],
            'long name' => [['name' => str_repeat('a', 256)], 'name'],
            'invalid email' => [['email' => 'not-email'], 'email'],
            'short password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
            'mismatch' => [['password_confirmation' => 'mismatch'], 'password'],
            'bcrypt limit' => [['password' => str_repeat('a', 73), 'password_confirmation' => str_repeat('a', 73)], 'password'],
            'null byte' => [['password' => "long-password\0value", 'password_confirmation' => "long-password\0value"], 'password'],
            'invalid role' => [['role_id' => -1], 'role_id'],
            'company injection' => [['company_id' => 1], 'form'],
            'status injection' => [['is_active' => false], 'form'],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_create_validation_rejects_invalid_data_without_writes(array $data, string $field): void
    {
        $this->asOwner(function () use ($data, $field): void {
            $count = CompanyUsers::query()->count();
            try {
                app(CreateUser::class)->handle($this->input($data));
                $this->fail('Expected validation error.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($field, $exception->errors());
            }
            $this->assertSame($count, CompanyUsers::query()->count());
        });
    }

    public function test_global_email_uniqueness_does_not_expose_foreign_identity(): void
    {
        $this->asOwner(function (): void {
            foreach ([fn () => app(CreateUser::class)->handle($this->input(['email' => strtoupper($this->foreignMember->email)])),
                fn () => app(UpdateUser::class)->handle($this->member->id, $this->editInput(['email' => $this->foreignMember->email]))] as $operation) {
                try {
                    $operation();
                    $this->fail('Duplicate email accepted.');
                } catch (ValidationException $exception) {
                    $this->assertSame([__('users.validation.unique')], $exception->errors()['email']);
                }
            }
        });
    }

    public function test_update_changes_profile_and_role_preserves_password_and_records_role_change(): void
    {
        $this->asOwner(function (): void {
            $password = $this->member->password;
            $updated = app(UpdateUser::class)->handle($this->member->id, $this->editInput(['email' => ' UPDATED@EXAMPLE.TEST ']));
            $this->assertSame('اسم معدل', $updated->name);
            $this->assertSame('updated@example.test', $updated->email);
            $this->assertNull($updated->email_verified_at);
            $this->assertSame($password, $updated->password);
            $this->assertSame(['accountant'], $updated->roles->pluck('name')->all());
            $this->assertSame(1, ActivityLog::where('action', 'user.role_changed')->count());
            app(UpdateUser::class)->handle($updated->id, $this->editInput(['email' => $updated->email]));
            $this->assertSame(1, ActivityLog::where('action', 'user.role_changed')->count());
        });
    }

    public function test_foreign_targets_and_roles_are_rejected_and_positive_operations_work(): void
    {
        $before = $this->foreignMember->getAttributes();
        $this->asOwner(function (): void {
            app(UpdateUser::class)->handle($this->member->id, $this->editInput());
            app(ChangeUserStatus::class)->handle($this->member->id, false);
            foreach ([fn () => app(UpdateUser::class)->handle($this->foreignMember->id, $this->editInput()),
                fn () => app(ChangeUserStatus::class)->handle($this->foreignMember->id, false)] as $operation) {
                try {
                    $operation();
                    $this->fail('Foreign target allowed.');
                } catch (ModelNotFoundException) {
                    $this->addToAssertionCount(1);
                }
            }
            foreach ([fn () => app(CreateUser::class)->handle($this->input(['role_id' => $this->foreignRoles['sales']])),
                fn () => app(UpdateUser::class)->handle($this->member->id, $this->editInput(['role_id' => $this->foreignRoles['admin']]))] as $operation) {
                try {
                    $operation();
                    $this->fail('Foreign role allowed.');
                } catch (ValidationException $exception) {
                    $this->assertArrayHasKey('role_id', $exception->errors());
                }
            }
            $this->assertSame(['accountant'], $this->member->fresh()->roles->pluck('name')->all());
        });
        $this->assertSame($before, $this->foreignMember->fresh()->getAttributes());
        $this->withinTenant($this->foreignOwner, fn () => $this->assertTrue(app(ChangeUserStatus::class)->handle($this->foreignMember->id, true)->is_active));
    }

    public function test_status_disable_reactivate_and_existing_authentication_protection(): void
    {
        $this->asOwner(fn () => app(ChangeUserStatus::class)->handle($this->member->id, false));
        $this->assertFalse($this->member->fresh()->is_active);
        $this->actingAs($this->member)->get('/users')->assertRedirect(route('login'));
        $this->assertGuest();
        try {
            app(LoginUser::class)->handle($this->member->email, 'password');
            $this->fail('Disabled login allowed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
        }
        $this->asOwner(fn () => app(ChangeUserStatus::class)->handle($this->member->id, true));
        $this->assertTrue($this->member->fresh()->is_active);
        app(LoginUser::class)->handle($this->member->email, 'password');
        $this->assertAuthenticatedAs($this->member);
    }

    public function test_founder_and_nonfounder_owner_cannot_be_demoted_or_disabled_by_others(): void
    {
        $this->asOwner(function (): void {
            $admin = User::factory()->for($this->owner->company)->create()->refresh();
            $admin->assignRole('admin');
            $otherOwner = User::factory()->for($this->owner->company)->create()->refresh();
            $otherOwner->assignRole('owner');
            foreach ([$admin, $otherOwner] as $actor) {
                $this->withinTenant($actor, function (): void {
                    foreach ([fn () => app(ChangeUserStatus::class)->handle($this->owner->id, false),
                        fn () => app(UpdateUser::class)->handle($this->owner->id, ['name' => 'forbidden', 'email' => $this->owner->email, 'role_id' => $this->roles['viewer']])] as $operation) {
                        try {
                            $operation();
                            $this->fail('Founder protection bypassed.');
                        } catch (AuthorizationException) {
                            $this->addToAssertionCount(1);
                        }
                    }
                    app(UpdateUser::class)->handle($this->owner->id, ['name' => 'مؤسس محدث', 'email' => $this->owner->email, 'role_id' => $this->roles['owner']]);
                });
            }
            try {
                app(ChangeUserStatus::class)->handle($otherOwner->id, false);
                $this->fail('Other owner was disabled.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
            try {
                app(UpdateUser::class)->handle($otherOwner->id, ['name' => $otherOwner->name, 'email' => $otherOwner->email, 'role_id' => $this->roles['admin']]);
                $this->fail('Other owner demoted.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
            $this->assertTrue($this->owner->fresh()->is_active);
            $this->assertTrue($otherOwner->fresh()->hasRole('owner'));
        });
    }

    public function test_founder_reference_is_protected_without_granting_permission(): void
    {
        $this->asOwner(function (): void {
            $this->owner->syncRoles('viewer');
            $this->member->syncRoles('admin');
        });
        $this->actingAs($this->owner)->get('/users')->assertForbidden();
        $this->withinTenant($this->member, function (): void {
            try {
                app(ChangeUserStatus::class)->handle($this->owner->id, false);
                $this->fail('Founder disabled.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        });
    }

    public function test_literal_self_changes_are_allowed_and_reactivation_by_admin_is_allowed(): void
    {
        $this->asOwner(fn () => $this->member->syncRoles('admin'));
        $this->asOwner(fn () => app(ChangeUserStatus::class)->handle($this->owner->id, false));
        $this->withinTenant($this->member, fn () => app(ChangeUserStatus::class)->handle($this->owner->id, true));
        $this->asOwner(function (): void {
            app(UpdateUser::class)->handle($this->owner->id, ['name' => $this->owner->name, 'email' => $this->owner->email, 'role_id' => $this->roles['viewer']]);
            $this->assertTrue($this->owner->fresh()->hasRole('viewer'));
            $this->assertFalse(Gate::forUser($this->owner)->allows('viewAny', User::class));
        });
    }

    public function test_no_company_context_fails_closed(): void
    {
        Auth::forgetGuards();
        $this->assertFalse(Gate::forUser($this->owner)->allows('viewAny', User::class));
        $this->expectException(AuthorizationException::class);
        app(CreateUser::class)->handle($this->input());
    }

    public function test_activity_failure_rolls_back_create_and_update(): void
    {
        $this->asOwner(function (): void {
            Event::listen('eloquent.creating: '.ActivityLog::class, fn () => false);
            foreach ([fn () => app(CreateUser::class)->handle($this->input()),
                fn () => app(UpdateUser::class)->handle($this->member->id, $this->editInput())] as $operation) {
                try {
                    $operation();
                    $this->fail('Expected persistence cancellation.');
                } catch (RuntimeException $exception) {
                    $this->assertSame('Role activity persistence was cancelled.', $exception->getMessage());
                }
            }
            $this->assertSame(2, CompanyUsers::query()->count());
            $this->assertSame(['viewer'], $this->member->fresh()->roles->pluck('name')->all());
            $this->assertSame($this->member->name, $this->member->fresh()->name);
        });
    }

    public function test_cancelled_user_save_rolls_back_assigned_role_and_audit(): void
    {
        $this->asOwner(function (): void {
            Event::listen('eloquent.updating: '.User::class, fn () => false);
            try {
                app(UpdateUser::class)->handle($this->member->id, $this->editInput());
                $this->fail('Cancelled save succeeded.');
            } catch (RuntimeException $exception) {
                $this->assertSame('User persistence was cancelled.', $exception->getMessage());
            }
            $this->assertSame(['viewer'], $this->member->fresh()->roles->pluck('name')->all());
            $this->assertSame(0, ActivityLog::count());
        });
    }

    public function test_livewire_create_edit_status_validation_and_password_is_not_editable(): void
    {
        $this->asOwner(function (): void {
            $component = Livewire::test(Users::class)->call('openCreate')->set('name', 'اسم محفوظ')
                ->call('save')->assertHasErrors(['email', 'password', 'role_id'])->assertSet('name', 'اسم محفوظ')->assertSet('formOpen', true);
            foreach ($this->input() as $key => $value) {
                $component->set($key, (string) $value);
            }
            $component->call('save')->assertHasNoErrors()->assertSet('formOpen', false)->assertSet('password', '')->assertDispatched('toast');
            $created = CompanyUsers::query()->where('email', 'new-user@example.test')->sole();
            $component->call('openEdit', $created->id)->assertDontSeeHtml('id="user-password"')
                ->set('name', 'مستخدم معدل')->set('role_id', (string) $this->roles['warehouse'])
                ->call('save')->assertHasNoErrors();
            $this->assertSame('مستخدم معدل', $created->fresh()->name);
            $component->call('confirmStatus', $created->id, false)->assertSet('statusId', $created->id)
                ->call('changeStatus')->assertSet('statusId', null);
            $this->assertFalse($created->fresh()->is_active);
            $component->call('confirmStatus', $created->id, true)->call('changeStatus');
            $this->assertTrue($created->fresh()->is_active);
        });
    }

    public function test_search_filters_pagination_do_not_disclose_other_company(): void
    {
        $this->asOwner(function (): void {
            User::factory()->count(13)->for($this->owner->company)->create(['name' => 'مستخدم إضافي']);
            $component = Livewire::test(Users::class)->assertViewHas('users', fn ($users) => $users->total() === 15 && $users->count() === 10)
                ->assertDontSee($this->foreignMember->email)->call('nextPage')->assertSet('paginators.page', 2)
                ->assertViewHas('users', fn ($users) => $users->count() === 5);
            $component->set('search', $this->member->name)->assertSet('paginators.page', 1)->assertSee($this->member->email);
            $component->set('search', $this->foreignMember->email)->assertSee(__('users.no_results'))->assertDontSee($this->foreignMember->name);
            $component->call('clearFilters')->set('roleFilter', (string) $this->roles['viewer'])->assertSee($this->member->email)->assertDontSee($this->owner->email);
            $component->set('roleFilter', (string) $this->foreignRoles['viewer'])->assertViewHas('users', fn ($users) => $users->total() === 0);
            app(ChangeUserStatus::class)->handle($this->member->id, false);
            $component->call('clearFilters')->set('statusFilter', 'inactive')->assertSee($this->member->email)->assertDontSee($this->owner->email);
            $component->set('statusFilter', 'active')->assertDontSee($this->member->email)->assertSee($this->owner->email);
        });
    }

    private function snapshot(User $actor): string
    {
        $response = $this->actingAs($actor)->get('/users')->assertOk();
        preg_match('/wire:snapshot="([^"]+)"/', $response->getContent(), $match);

        return html_entity_decode($match[1], ENT_QUOTES);
    }

    private function requestUpdate(string $snapshot, string $method, array $params = [], array $updates = [])
    {
        return $this->withHeader('X-Livewire', 'true')->postJson(app('livewire')->getUpdateUri(), [
            'components' => [['snapshot' => $snapshot, 'updates' => $updates,
                'calls' => [['path' => '', 'method' => $method, 'params' => $params]]]],
        ]);
    }

    public function test_real_http_livewire_rejects_foreign_target_and_stale_identity_snapshot(): void
    {
        $snapshot = $this->snapshot($this->owner);
        $this->requestUpdate($snapshot, 'openEdit', [$this->member->id])->assertOk();
        $this->requestUpdate($snapshot, 'openEdit', [$this->foreignMember->id])->assertNotFound();
        $this->requestUpdate($snapshot, 'confirmStatus', [$this->foreignMember->id, false])->assertNotFound();
        $this->actingAs($this->foreignOwner);
        $this->requestUpdate($snapshot, 'openCreate')->assertForbidden();
        $ownSnapshot = $this->snapshot($this->foreignOwner);
        $this->requestUpdate($ownSnapshot, 'openEdit', [$this->foreignMember->id])->assertOk();
        $this->assertSame($this->foreignMember->name, $this->foreignMember->fresh()->name);
    }

    public function test_forged_company_selection_in_query_header_and_livewire_body_is_rejected(): void
    {
        $snapshot = $this->snapshot($this->owner);
        $this->get('/users?company_id='.$this->foreignOwner->company_id)->assertForbidden();
        $this->withHeader('X-Company-Id', (string) $this->foreignOwner->company_id)->get('/users')->assertForbidden();
        $this->flushHeaders();
        $this->requestUpdate($snapshot, 'openCreate', [], ['company_id' => $this->foreignOwner->company_id])->assertForbidden();
        $this->requestUpdate($snapshot, 'openCreate')->assertOk();
    }

    public static function staleActions(): array
    {
        return [['openCreate'], ['save'], ['openEdit'], ['confirmStatus'], ['changeStatus']];
    }

    #[DataProvider('staleActions')]
    public function test_stale_ui_does_not_authorize_after_permission_revocation(string $method): void
    {
        $snapshot = $this->snapshot($this->owner);
        $this->asOwner(fn () => $this->owner->fresh()->syncRoles('viewer'));
        $params = match ($method) {
            'openEdit' => [$this->member->id], 'confirmStatus' => [$this->member->id, false], default => []
        };
        $this->requestUpdate($snapshot, $method, $params)->assertForbidden();
        $this->assertTrue($this->member->fresh()->is_active);
    }

    public function test_target_removed_from_company_after_form_open_is_not_mutated(): void
    {
        $snapshot = $this->snapshot($this->owner);
        $response = $this->requestUpdate($snapshot, 'openEdit', [$this->member->id])->assertOk();
        $editSnapshot = $response->json('components.0.snapshot');
        // Simulate a trusted external reassignment; the stale payload must never follow the target.
        User::where('company_id', $this->owner->company_id)->whereKey($this->member->id)->update(['company_id' => $this->foreignOwner->company_id]);
        $this->requestUpdate($editSnapshot, 'save', [], ['name' => 'forged'])->assertNotFound();
        $this->assertSame($this->member->name, $this->member->fresh()->name);
    }

    public function test_locked_identity_and_target_cannot_be_changed_by_livewire_updates(): void
    {
        $snapshot = $this->snapshot($this->owner);
        foreach (['actorId' => $this->foreignOwner->id, 'editingId' => $this->foreignMember->id, 'statusId' => $this->foreignMember->id] as $property => $id) {
            $this->withoutExceptionHandling();
            try {
                $this->requestUpdate($snapshot, 'save', [], [$property => $id]);
                $this->fail('Locked field modified.');
            } catch (CannotUpdateLockedPropertyException $exception) {
                $this->assertStringContainsString($property, $exception->getMessage());
            } finally {
                $this->withExceptionHandling();
            }
        }
    }

    public function test_http_validation_preserves_form_and_does_not_return_password_in_html(): void
    {
        $snapshot = $this->snapshot($this->owner);
        $opened = $this->requestUpdate($snapshot, 'openCreate')->assertOk()->json('components.0.snapshot');
        $response = $this->requestUpdate($opened, 'save', [], ['name' => 'اسم محفوظ', 'password' => 'secret-short'])->assertOk();
        $state = json_decode($response->json('components.0.snapshot'), true);
        $this->assertSame('اسم محفوظ', $state['data']['name']);
        $this->assertTrue($state['data']['formOpen']);
        $this->assertStringContainsString('حقل البريد الإلكتروني مطلوب.', $response->json('components.0.effects.html'));
        $this->assertStringContainsString('aria-invalid="true"', $response->json('components.0.effects.html'));
        $this->assertStringNotContainsString('secret-short', $response->json('components.0.effects.html'));
    }

    public function test_list_queries_remain_bounded_as_rows_increase_and_html_escapes_input(): void
    {
        $this->asOwner(function (): void {
            $this->member->update(['name' => '<script>alert(1)</script>']);
            DB::enableQueryLog();
            Livewire::test(Users::class)->assertSee('&lt;script&gt;', false)->assertDontSeeHtml('<script>alert(1)</script>');
            $small = count(DB::getQueryLog());
            DB::disableQueryLog();
            User::factory()->count(8)->for($this->owner->company)->create();
            DB::flushQueryLog();
            DB::enableQueryLog();
            Livewire::test(Users::class);
            $large = count(DB::getQueryLog());
            DB::disableQueryLog();
            $this->assertLessThanOrEqual($small + 2, $large);
        });
    }

    public function test_same_identity_moved_to_another_company_cannot_replay_old_snapshot(): void
    {
        $this->asOwner(fn () => $this->member->syncRoles('admin'));
        $snapshot = $this->snapshot($this->member);
        User::where('company_id', $this->owner->company_id)->whereKey($this->member->id)->update(['company_id' => $this->foreignOwner->company_id]);
        $moved = $this->member->fresh();
        $this->withinTenant($moved, fn () => $moved->syncRoles('admin'));
        $this->actingAs($moved);
        $this->requestUpdate($snapshot, 'save')->assertForbidden();
        $freshSnapshot = $this->snapshot($moved);
        $this->requestUpdate($freshSnapshot, 'openCreate')->assertOk();
    }

    public function test_self_demotion_and_deactivation_redirect_without_rendering_privileged_state(): void
    {
        $this->asOwner(function (): void {
            Livewire::test(Users::class)->call('openEdit', $this->owner->id)
                ->set('role_id', (string) $this->roles['viewer'])->call('save')
                ->assertRedirect(route('setup.pending'));
            $this->owner->fresh()->syncRoles('owner');
            Livewire::test(Users::class)->call('confirmStatus', $this->owner->id, false)
                ->call('changeStatus')->assertRedirect(route('login'));
        });
        $this->assertFalse($this->owner->fresh()->is_active);
    }

    public function test_owner_protection_is_rechecked_when_target_role_changes_after_confirmation(): void
    {
        $snapshot = $this->snapshot($this->owner);
        $opened = $this->requestUpdate($snapshot, 'confirmStatus', [$this->member->id, false])->assertOk()->json('components.0.snapshot');
        $this->asOwner(fn () => $this->member->fresh()->syncRoles('owner'));
        $this->requestUpdate($opened, 'changeStatus')->assertForbidden();
        $this->assertTrue($this->member->fresh()->is_active);
    }

    public function test_update_rejects_password_fields_and_validation_is_atomic(): void
    {
        $this->asOwner(function (): void {
            foreach ([['password' => 'Unrequested-change-123'], ['name' => ''], ['email' => 'broken']] as $invalid) {
                try {
                    app(UpdateUser::class)->handle($this->member->id, $this->editInput($invalid));
                    $this->fail('Invalid update accepted.');
                } catch (ValidationException $exception) {
                    $this->assertNotEmpty($exception->errors());
                }
            }
            $this->assertSame($this->member->name, $this->member->fresh()->name);
            $this->assertSame($this->member->password, $this->member->fresh()->password);
            $this->assertSame(['viewer'], $this->member->fresh()->roles->pluck('name')->all());
        });
    }

    public function test_unexpected_save_failure_returns_generic_feedback_and_retains_form(): void
    {
        $this->asOwner(function (): void {
            Event::listen('eloquent.creating: '.ActivityLog::class, fn () => throw new RuntimeException('sensitive-exception-probe'));
            $component = Livewire::test(Users::class)->call('openCreate');
            foreach ($this->input() as $key => $value) {
                $component->set($key, (string) $value);
            }
            $component->call('save')->assertDispatched('toast', type: 'error', message: __('users.failed'))
                ->assertSet('formOpen', true)->assertSet('name', 'مستخدم جديد')->assertDontSee('sensitive-exception-probe');
            $this->assertSame(2, CompanyUsers::query()->count());
        });
    }

    public function test_stale_cached_actor_roles_cannot_authorize_direct_action(): void
    {
        $this->asOwner(function (): void {
            $this->assertTrue($this->owner->hasPermissionTo('users.manage'));
            $this->owner->fresh()->syncRoles('viewer');
            $this->expectException(AuthorizationException::class);
            app(CreateUser::class)->handle($this->input());
        });
    }
}
