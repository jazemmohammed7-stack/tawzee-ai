<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\RegisterCompany;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class RegistrationPageTest extends TestCase
{
    public function test_guest_sees_accessible_arabic_registration_page_without_login_dependency(): void
    {
        $this->get('/register')->assertOk()->assertSeeLivewire(RegisterCompany::class)
            ->assertSee('lang="ar" dir="rtl"', false)->assertSee(__('registration.title'))
            ->assertSee('autocomplete="new-password"', false)->assertSee('aria-describedby=', false)
            ->assertSee('wire:submit="register"', false);
    }

    public function test_success_state_cannot_be_set_by_client(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::test(RegisterCompany::class)->set('registered', true);
    }
}
