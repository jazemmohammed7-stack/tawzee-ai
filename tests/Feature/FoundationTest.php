<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Foundation;
use App\Providers\AppServiceProvider;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    public function test_public_page_has_arabic_rtl_and_local_built_assets(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('lang="ar" dir="rtl"', false)
            ->assertSee(__('foundation.heading'))
            ->assertSee('role="status"', false)
            ->assertSee('/build/assets/', false)
            ->assertSeeLivewire(Foundation::class);

        $this->assertSame('ar', app()->getLocale());
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('Asia/Aden', config('tawzee.display_timezone'));
        $this->assertNotNull(app()->getProvider(AppServiceProvider::class));
        $this->assertNotNull(app()->getProvider(LivewireServiceProvider::class));
    }

    public function test_guest_can_complete_the_stateless_interaction(): void
    {
        Livewire::test(Foundation::class)
            ->assertSet('checked', false)
            ->call('checkConnection')
            ->assertSet('checked', true)
            ->assertSee(__('foundation.success'));
    }
}
