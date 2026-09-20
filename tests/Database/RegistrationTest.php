<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Livewire\RegisterCompany as RegistrationForm;
use App\Models\User;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\DocumentSequence;
use App\Modules\Identity\Actions\RegisterCompany;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use DatabaseTransactions;

    private function input(): array
    {
        return ['company_name' => 'شركة الاختبار', 'name' => 'مؤسس الاختبار', 'email' => 'registration@example.test',
            'password' => 'Test-only-password-123', 'password_confirmation' => 'Test-only-password-123'];
    }

    private function counts(): array
    {
        // Internal test invariant: global counts detect any leaked partial registration.
        return array_map(fn ($table) => DB::table($table)->count(), ['companies', 'users', 'document_sequences']);
    }

    public function test_guest_registration_creates_linked_founder_and_sequences_without_activation_or_login(): void
    {
        $input = $this->input();
        $input['email'] = ' REGISTRATION@example.test ';
        $company = app(RegisterCompany::class)->handle($input)->refresh();
        $user = $company->founder;
        $this->assertSame('pending_setup', $company->status);
        $this->assertSame($company->id, $user->company_id);
        $this->assertSame($user->id, $company->users->sole()->id);
        $this->assertSame('registration@example.test', $user->email);
        $this->assertTrue(Hash::check($input['password'], $user->password));
        $this->assertNotSame($input['password'], $user->password);
        $this->assertTrue($user->is_active);
        $this->assertGuest();
        app(CurrentCompany::class)->run($company, function () use ($company): void {
            $sequences = $company->documentSequences()->orderBy('type')->get();
            $this->assertSame(['invoice', 'opening_balance', 'receipt'], $sequences->pluck('type')->all());
            $this->assertSame([$company->id], $sequences->pluck('company_id')->unique()->values()->all());
            $this->assertSame([1], $sequences->pluck('next_number')->unique()->values()->all());
        });
        $this->expectException(AuthorizationException::class);
        app(CurrentCompany::class)->id();
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_input_cannot_create_partial_registration(string $field, mixed $value): void
    {
        $before = $this->counts();
        try {
            app(RegisterCompany::class)->handle(array_replace($this->input(), [$field => $value]));
            $this->fail('Invalid registration was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field === 'password_confirmation' ? 'password' : $field, $exception->errors());
            $this->assertMatchesRegularExpression('/[\x{0600}-\x{06FF}]/u', $exception->getMessage());
        }
        $this->assertSame($before, $this->counts());
    }

    public static function invalidInputs(): array
    {
        return [
            ['company_name', '   '], ['company_name', str_repeat('a', 256)], ['company_name', []],
            ['name', ''], ['name', str_repeat('a', 256)], ['email', 'invalid'], ['email', []],
            ['password', 'short'], ['password', str_repeat('a', 73)], ['password', str_repeat('ع', 40)],
            ['password', "Test-password\0invalid"], ['password_confirmation', 'different'],
        ];
    }

    #[DataProvider('forbiddenFields')]
    public function test_client_cannot_supply_server_owned_fields(string $field): void
    {
        $before = $this->counts();
        try {
            app(RegisterCompany::class)->handle($this->input() + [$field => 999]);
            $this->fail('Server-owned field was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('registration', $exception->errors());
        }
        $this->assertSame($before, $this->counts());
    }

    public static function forbiddenFields(): array
    {
        return array_map(fn ($field) => [$field], ['company_id', 'status', 'is_active', 'roles', 'permissions', 'next_number', 'sequences', 'founder_user_id']);
    }

    #[DataProvider('failureStages')]
    public function test_exception_at_any_stage_rolls_back_every_record_and_restores_context(string $event, int $failAt): void
    {
        $before = $this->counts();
        $seen = 0;
        Event::listen($event, function () use (&$seen, $failAt): void {
            if (++$seen === $failAt) {
                throw new RuntimeException('Injected registration failure.');
            }
        });
        try {
            app(RegisterCompany::class)->handle($this->input());
            $this->fail('Injected failure did not propagate.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Injected registration failure.', $exception->getMessage());
        }
        $this->assertSame($failAt, $seen);
        $this->assertSame($before, $this->counts());
        $this->expectException(AuthorizationException::class);
        app(CurrentCompany::class)->id();
    }

    public static function failureStages(): array
    {
        return [
            ['eloquent.created: '.Company::class, 1],
            ['eloquent.creating: '.User::class, 1],
            ['eloquent.created: '.User::class, 1],
            ['eloquent.updated: '.Company::class, 1],
            ['eloquent.creating: '.DocumentSequence::class, 2],
            ['eloquent.created: '.DocumentSequence::class, 3],
        ];
    }

    #[DataProvider('cancelledStages')]
    public function test_cancelled_model_save_is_a_failure_and_rolls_back_every_record(string $event, int $cancelAt): void
    {
        $before = $this->counts();
        $seen = 0;
        Event::listen($event, function () use (&$seen, $cancelAt): ?bool {
            return ++$seen === $cancelAt ? false : null;
        });
        try {
            app(RegisterCompany::class)->handle($this->input());
            $this->fail('A cancelled save must not produce a successful registration.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Registration persistence was cancelled.', $exception->getMessage());
            $this->assertSame($before, $this->counts());
        }
    }

    public static function cancelledStages(): array
    {
        return [
            ['eloquent.creating: '.Company::class, 1],
            ['eloquent.creating: '.User::class, 1],
            ['eloquent.updating: '.Company::class, 1],
            ['eloquent.creating: '.DocumentSequence::class, 2],
        ];
    }

    public function test_failed_user_database_insert_rolls_back_company(): void
    {
        $before = $this->counts();
        Event::listen('eloquent.creating: '.User::class, function (User $user): void {
            $user->company_id = null;
        });
        try {
            app(RegisterCompany::class)->handle($this->input());
            $this->fail('Required company constraint was bypassed.');
        } catch (QueryException) {
            $this->assertSame($before, $this->counts());
        }
    }

    public function test_failed_sequence_database_insert_rolls_back_company_and_user(): void
    {
        $before = $this->counts();
        Event::listen('eloquent.creating: '.DocumentSequence::class, function (DocumentSequence $sequence): void {
            $sequence->next_number = 0;
        });
        try {
            app(RegisterCompany::class)->handle($this->input());
            $this->fail('Sequence constraint was bypassed.');
        } catch (QueryException) {
            $this->assertSame($before, $this->counts());
        }
    }

    public function test_duplicate_email_and_replay_create_no_second_company(): void
    {
        $company = app(RegisterCompany::class)->handle($this->input());
        $before = $this->counts();
        foreach ([$this->input(), array_replace($this->input(), ['company_name' => 'شركة أخرى', 'email' => 'REGISTRATION@example.test'])] as $input) {
            try {
                app(RegisterCompany::class)->handle($input);
                $this->fail('Duplicate registration accepted.');
            } catch (ValidationException $exception) {
                $this->assertSame([__('registration.validation.unique')], $exception->errors()['email']);
            }
            $this->assertSame($before, $this->counts());
        }
        $this->assertSame($company->id, User::where('email', $this->input()['email'])->sole()->company_id);
    }

    public function test_founder_cannot_be_mass_assigned_or_belong_to_another_company(): void
    {
        $a = Company::factory()->create();
        $b = User::factory()->create();
        $this->assertNull((new Company(['founder_user_id' => $b->id]))->founder_user_id);
        $a->founder_user_id = $b->id;
        $this->expectException(QueryException::class);
        $a->save();
    }

    public function test_authenticated_user_cannot_register_another_company(): void
    {
        $this->actingAs(User::factory()->create());
        $before = $this->counts();
        try {
            app(RegisterCompany::class)->handle($this->input());
            $this->fail('Authenticated registration accepted.');
        } catch (AuthorizationException) {
            $this->assertSame($before, $this->counts());
        }
        $this->get('/register')->assertForbidden();
    }

    public function test_livewire_registers_and_clears_passwords_without_login(): void
    {
        $form = Livewire::test(RegistrationForm::class);
        foreach ($this->input() as $key => $value) {
            $form->set($key, $value);
        }
        $form->call('register')->assertHasNoErrors()->assertSet('registered', true)
            ->assertSet('password', '')->assertSet('password_confirmation', '')
            ->assertSee(__('registration.success'));
        $this->assertGuest();
        $this->assertSame('pending_setup', User::where('email', $this->input()['email'])->sole()->company->status);
    }

    public function test_livewire_checks_authorization_again_on_submit(): void
    {
        $form = Livewire::test(RegistrationForm::class);
        $this->actingAs(User::factory()->create());
        $before = $this->counts();
        $form->call('register')->assertForbidden();
        $this->assertSame($before, $this->counts());
    }

    public function test_livewire_does_not_expose_unexpected_exception_details(): void
    {
        $before = $this->counts();
        Event::listen('eloquent.creating: '.User::class, fn () => throw new RuntimeException('private-database-detail'));
        $form = Livewire::test(RegistrationForm::class);
        foreach ($this->input() as $key => $value) {
            $form->set($key, $value);
        }
        $form->call('register')->assertHasErrors('registration')->assertSee(__('registration.failed'))
            ->assertDontSee('private-database-detail')->assertSet('password', '')->assertSet('registered', false);
        $this->assertSame($before, $this->counts());
    }

    public function test_livewire_limits_repeated_registration_attempts(): void
    {
        $before = $this->counts();
        $form = Livewire::test(RegistrationForm::class);
        for ($i = 0; $i < 5; $i++) {
            $form->call('register');
        }
        $form->call('register')->assertHasErrors('registration')->assertSee(__('registration.throttled'));
        $this->assertSame($before, $this->counts());
    }
}
