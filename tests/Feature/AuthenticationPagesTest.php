<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\ResetPassword;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationPagesTest extends TestCase
{
    #[DataProvider('pages')]
    public function test_guest_authentication_pages_are_arabic_accessible_and_not_cached(string $url, string $component): void
    {
        $this->get($url)->assertOk()->assertSeeLivewire($component)->assertSee('lang="ar" dir="rtl"', false)
            ->assertSee('aria-describedby=', false)->assertSee('wire:loading', false)
            ->assertSee('name="referrer" content="no-referrer"', false)->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private');
    }

    public static function pages(): array
    {
        return [['/login', Login::class], ['/forgot-password', ForgotPassword::class], ['/reset-password/'.str_repeat('a', 64).'?email=test@example.test', ResetPassword::class]];
    }

    public function test_guest_cannot_access_pending_page(): void
    {
        $this->get('/pending-setup')->assertRedirect(route('login'));
    }

    public function test_reset_token_cannot_be_changed_as_livewire_property(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::test(ResetPassword::class, ['token' => str_repeat('a', 64)])->set('token', str_repeat('b', 64));
    }
}
