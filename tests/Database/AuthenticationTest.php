<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\ResetPassword;
use App\Models\User;
use App\Modules\Identity\Actions\SendPasswordResetLink;
use App\Modules\Identity\Mail\PasswordResetLink;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use DatabaseTransactions;

    private const PASSWORD = 'Test-only-password-123';

    private const NEW_PASSWORD = 'New-test-only-password-456';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['auth.password_reset_mail_enabled' => true, 'mail.default' => 'smtp', 'mail.mailers.smtp.transport' => 'smtp']);
    }

    private function user(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['email' => 'auth@example.test', 'password' => self::PASSWORD])->refresh();
    }

    private function token(User $user): string
    {
        app(SendPasswordResetLink::class)->handle($user->email);
        Mail::assertSent(PasswordResetLink::class, fn ($mail) => $mail->hasTo($user->email));
        $url = Mail::sent(PasswordResetLink::class)->last()->resetUrl;

        return basename(parse_url($url, PHP_URL_PATH));
    }

    private function resetForm(string $email, string $token): Testable
    {
        return Livewire::test(ResetPassword::class, ['token' => $token])->set('email', $email)
            ->set('password', self::NEW_PASSWORD)->set('password_confirmation', self::NEW_PASSWORD);
    }

    public function test_login_normalizes_email_rotates_session_and_only_opens_pending_page(): void
    {
        $user = $this->user();
        $this->get('/login')->assertOk();
        $oldId = session()->getId();
        $oldToken = session()->token();
        session()->put('url.intended', 'https://untrusted.example.test');
        Livewire::test(Login::class)->set('email', ' AUTH@example.test ')->set('password', self::PASSWORD)
            ->call('login')->assertHasNoErrors()->assertRedirect(route('setup.pending'))->assertSet('password', '');
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldId, session()->getId());
        $this->assertNotSame($oldToken, session()->token());
        $this->assertTrue(session()->get('password_hash_web') === $user->password);
        $this->get('/pending-setup')->assertOk()->assertSee(__('authentication.pending'))
            ->assertSee(__('authentication.temporary'))->assertSee('method="POST"', false);
        $this->assertSame('pending_setup', $user->company->status);
        $this->assertSame($user->company_id, $user->fresh()->company_id);
        $this->assertDatabaseCount('model_has_roles', 0);
    }

    #[DataProvider('failedLogins')]
    public function test_wrong_password_unknown_and_disabled_accounts_have_identical_safe_error(string $kind): void
    {
        $this->user(['is_active' => $kind !== 'disabled']);
        $email = $kind === 'unknown' ? 'absent@example.test' : 'auth@example.test';
        $password = $kind === 'wrong' ? 'Incorrect-test-password' : self::PASSWORD;
        Livewire::test(Login::class)->set('email', $email)->set('password', $password)
            ->call('login')->assertHasErrors('email')->assertSee(__('authentication.failed'))->assertSet('password', '');
        $this->assertGuest();
    }

    public static function failedLogins(): array
    {
        return [['wrong'], ['unknown'], ['disabled']];
    }

    public function test_login_is_rate_limited_for_normalized_email_and_ip(): void
    {
        $this->user();
        $form = Livewire::test(Login::class)->set('email', 'auth@example.test');
        for ($i = 0; $i < 5; $i++) {
            $form->set('password', 'Incorrect-password')->call('login');
        }
        $form->set('email', ' AUTH@example.test ')->set('password', self::PASSWORD)->call('login')
            ->assertHasErrors('email')->assertSee(__('authentication.throttled'));
        $this->assertGuest();
        $this->travel(61)->seconds();
        $form->set('password', self::PASSWORD)->call('login')->assertHasNoErrors()->assertRedirect(route('setup.pending'));
    }

    public function test_ip_limit_cannot_be_avoided_by_rotating_emails(): void
    {
        for ($i = 0; $i < 20; $i++) {
            Livewire::test(Login::class)->set('email', 'unknown'.$i.'@example.test')->set('password', self::PASSWORD)->call('login');
        }
        Livewire::test(Login::class)->set('email', 'new@example.test')->set('password', self::PASSWORD)->call('login')
            ->assertSee(__('authentication.throttled'));
        $this->assertGuest();
    }

    public function test_logout_invalidates_session_and_csrf_and_guest_cannot_return(): void
    {
        $user = $this->user();
        Livewire::test(Login::class)->set('email', $user->email)->set('password', self::PASSWORD)->call('login');
        session()->put('private-marker', 'present');
        session()->save();
        $id = session()->getId();
        $csrf = session()->token();
        $this->withCookie(config('session.cookie'), $id)->post('/logout')->assertRedirect(route('home'));
        $this->assertGuest();
        $this->assertNotSame($id, session()->getId());
        $this->assertNotSame($csrf, session()->token());
        $this->assertFalse(session()->has('private-marker'));
        $this->assertTrue(session()->getHandler()->read($id) === '', 'The previous authenticated session must be destroyed.');
        $this->get('/pending-setup')->assertRedirect(route('login'));
        $this->get('/logout')->assertStatus(405);
    }

    public function test_real_livewire_http_login_requires_csrf_and_rotates_persisted_session(): void
    {
        $user = $this->user();
        $this->app['env'] = 'local'; // Retain the verified isolated test connection.
        $response = $this->get('/login')->assertOk();
        preg_match('/wire:snapshot="([^"]+)"/', $response->getContent(), $match);
        $snapshot = html_entity_decode($match[1], ENT_QUOTES);
        $oldId = session()->getId();
        $payload = ['components' => [['snapshot' => $snapshot, 'updates' => ['email' => $user->email, 'password' => self::PASSWORD],
            'calls' => [['path' => '', 'method' => 'login', 'params' => []]]]]];
        $uri = app('livewire')->getUpdateUri();
        $this->withCredentials()->withCookie(config('session.cookie'), $oldId)->withHeader('X-Livewire', 'true')->postJson($uri, $payload)->assertStatus(419);
        $this->assertGuest();
        $this->withHeader('X-CSRF-TOKEN', session()->token())->postJson($uri, $payload)->assertOk()
            ->assertJsonPath('components.0.effects.redirect', route('setup.pending'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldId, session()->getId());
        $this->assertTrue(session()->getHandler()->read($oldId) === '', 'The pre-login session must be destroyed.');
    }

    public function test_logout_requires_real_csrf_middleware(): void
    {
        $this->actingAs($this->user());
        // Keep verified test DB; disable only the framework's unit-test CSRF exemption.
        $this->app['env'] = 'local';
        $this->post('/logout')->assertStatus(419);
        $this->assertAuthenticated();
        $this->withSession(['_token' => 'test-only-csrf'])->post('/logout', ['_token' => 'test-only-csrf'])->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_deactivated_existing_session_is_terminated_on_pending_page(): void
    {
        $user = $this->user();
        Livewire::test(Login::class)->set('email', $user->email)->set('password', self::PASSWORD)->call('login');
        User::whereKey($user->id)->update(['is_active' => false]);
        $this->get('/pending-setup')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_authentication_actions_recheck_guest_authorization_on_submit(): void
    {
        $login = Livewire::test(Login::class);
        $forgot = Livewire::test(ForgotPassword::class);
        $reset = Livewire::test(ResetPassword::class, ['token' => str_repeat('a', 64)]);
        $this->actingAs($this->user());
        $login->call('login')->assertForbidden();
        $forgot->call('sendLink')->assertForbidden();
        $reset->call('resetPassword')->assertForbidden();
        Mail::assertNothingSent();
    }

    public function test_broker_sends_fake_mail_with_trusted_origin_and_stores_hashed_token(): void
    {
        config(['app.url' => 'https://trusted.example.test']);
        $user = $this->user();
        $token = $this->token($user);
        $row = DB::table('password_reset_tokens')->where('email', $user->email)->first();
        $this->assertTrue(Hash::check($token, $row->token));
        $this->assertTrue($row->token !== $token);
        Mail::assertSent(PasswordResetLink::class, function ($mail): bool {
            return str_starts_with($mail->resetUrl, 'https://trusted.example.test/reset-password/')
                && str_contains($mail->render(), __('authentication.mail_ignore'));
        });
    }

    #[DataProvider('resetRequests')]
    public function test_recovery_does_not_reveal_account_existence_or_disabled_state(string $kind): void
    {
        if ($kind !== 'unknown') {
            $this->user(['is_active' => $kind !== 'disabled']);
        }
        $form = Livewire::test(ForgotPassword::class)->set('email', ' AUTH@example.test ');
        $form->call('sendLink')->assertHasNoErrors()->assertSee(__('authentication.sent'));
        $form->call('sendLink')->assertHasNoErrors()->assertSee(__('authentication.sent'));
        Mail::assertSentCount($kind === 'active' ? 1 : 0);
    }

    public static function resetRequests(): array
    {
        return [['active'], ['disabled'], ['unknown']];
    }

    public function test_forgot_and_reset_have_separate_rate_limits(): void
    {
        $form = Livewire::test(ForgotPassword::class)->set('email', 'missing@example.test');
        for ($i = 0; $i < 5; $i++) {
            $form->call('sendLink');
        }
        $form->call('sendLink')->assertSee(__('authentication.throttled'));
        for ($i = 0; $i < 5; $i++) {
            $this->resetForm('missing@example.test', str_repeat('a', 64))->call('resetPassword')->assertSee(__('authentication.invalid_reset'));
        }
        $this->resetForm('missing@example.test', str_repeat('a', 64))->call('resetPassword')->assertSee(__('authentication.throttled'));
    }

    public function test_reset_changes_only_password_and_remember_token_and_consumes_token(): void
    {
        $user = $this->user();
        $before = $user->getAttributes();
        $token = $this->token($user);
        $this->resetForm($user->email, $token)->call('resetPassword')->assertHasNoErrors()->assertRedirect(route('login'))
            ->assertSet('password', '')->assertSet('password_confirmation', '')->assertSet('token', '');
        $user->refresh();
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->password));
        $this->assertFalse(Hash::check(self::PASSWORD, $user->password));
        $this->assertTrue($before['remember_token'] !== $user->remember_token);
        foreach (['company_id', 'is_active', 'name', 'email'] as $field) {
            $this->assertSame($before[$field], $user->getAttributes()[$field]);
        }
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertGuest();
        $this->resetForm($user->email, $token)->call('resetPassword')->assertSee(__('authentication.invalid_reset'));
        Livewire::test(Login::class)->set('email', $user->email)->set('password', self::NEW_PASSWORD)->call('login')->assertRedirect(route('setup.pending'));
    }

    #[DataProvider('invalidTokens')]
    public function test_invalid_expired_foreign_or_disabled_tokens_do_not_change_password(string $kind): void
    {
        $user = $this->user();
        $original = $user->password;
        $token = $this->token($user);
        $email = $user->email;
        if ($kind === 'invalid') {
            $token = str_repeat('x', 64);
        }
        if ($kind === 'expired') {
            $this->travel(61)->minutes();
        }
        if ($kind === 'other-user') {
            $email = $this->user(['email' => 'other@example.test'])->email;
        }
        if ($kind === 'disabled') {
            User::whereKey($user->id)->update(['is_active' => false]);
        }
        $this->resetForm($email, $token)->call('resetPassword')->assertHasErrors('email')->assertSee(__('authentication.invalid_reset'));
        $this->assertTrue($original === $user->fresh()->password);
        $this->assertGuest();
        if ($kind === 'disabled') {
            $this->assertFalse($user->fresh()->is_active);
        }
    }

    public static function invalidTokens(): array
    {
        return [['invalid'], ['expired'], ['other-user'], ['disabled']];
    }

    public function test_replaced_token_is_invalid_and_latest_token_works(): void
    {
        $user = $this->user();
        $old = $this->token($user);
        $this->travel(61)->seconds();
        $new = $this->token($user);
        $this->resetForm($user->email, $old)->call('resetPassword')->assertSee(__('authentication.invalid_reset'));
        $this->resetForm($user->email, $new)->call('resetPassword')->assertHasNoErrors();
    }

    public function test_password_validation_preserves_token_for_retry(): void
    {
        $user = $this->user();
        $token = $this->token($user);
        foreach ([['short', 'short'], [self::NEW_PASSWORD, 'mismatch'], [str_repeat('ع', 40), str_repeat('ع', 40)]] as [$password, $confirmation]) {
            $this->resetForm($user->email, $token)->set('password', $password)->set('password_confirmation', $confirmation)
                ->call('resetPassword')->assertHasErrors('password');
        }
        $this->assertTrue(Password::broker()->tokenExists($user, $token));
    }

    public function test_cancelled_password_save_rolls_back_without_consuming_token(): void
    {
        $user = $this->user();
        $token = $this->token($user);
        $original = $user->password;
        Event::listen('eloquent.updating: '.User::class, fn () => false);
        $this->resetForm($user->email, $token)->call('resetPassword')->assertSee(__('authentication.invalid_reset'));
        $this->assertTrue($original === $user->fresh()->password);
        $this->assertTrue(Password::broker()->tokenExists($user, $token));
    }

    public function test_previous_session_is_rejected_after_password_changes(): void
    {
        $user = $this->user();
        Livewire::test(Login::class)->set('email', $user->email)->set('password', self::PASSWORD)->call('login');
        // Simulate a password reset from another browser, retaining the original session.
        User::whereKey($user->id)->update(['password' => Hash::make(self::NEW_PASSWORD)]);
        Auth::forgetGuards();
        $this->get('/pending-setup')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_delivery_is_disabled_by_default_and_log_mailer_never_receives_tokens(): void
    {
        $user = $this->user();
        config(['auth.password_reset_mail_enabled' => false]);
        app(SendPasswordResetLink::class)->handle($user->email);
        config(['auth.password_reset_mail_enabled' => true, 'mail.default' => 'log']);
        app(SendPasswordResetLink::class)->handle($user->email);
        Mail::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }
}
