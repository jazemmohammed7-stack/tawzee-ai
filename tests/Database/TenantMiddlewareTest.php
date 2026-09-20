<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\User;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\DocumentSequence;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\TenantProbe;
use Tests\TestCase;

class TenantMiddlewareTest extends TestCase
{
    use DatabaseTransactions;

    private User $a;

    private User $b;

    private DocumentSequence $sequenceA;

    private DocumentSequence $sequenceB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = User::factory()->create()->refresh();
        $this->b = User::factory()->create()->refresh();
        $this->sequenceA = app(CurrentCompany::class)->run($this->a->company, fn () => DocumentSequence::create(['type' => 'probe']));
        $this->sequenceB = app(CurrentCompany::class)->run($this->b->company, fn () => DocumentSequence::create(['type' => 'probe']));
        Route::middleware(['web', 'auth', 'tenant'])->group(function (): void {
            Route::match(['GET', 'POST'], '/_tenant/context', fn () => ['company' => app(CurrentCompany::class)->id()]);
            Route::get('/_tenant/url/{company_id}', fn () => ['unexpected' => true]);
            Route::get('/_tenant/sequence/{sequence}', fn (DocumentSequence $sequence) => ['id' => $sequence->id]);
            Route::get('/_tenant/crash', fn () => throw new \RuntimeException('Tenant probe failure.'));
            Route::get('/_tenant/probe', fn () => view('tenant-test-probe'));
            Route::get('/_tenant/business', fn () => view('tenant-test-probe'))->middleware('company.ready');
        });
        Livewire::component('tenant-test-probe', TenantProbe::class);
        app('view')->addLocation(base_path('tests/Fixtures/views'));
    }

    private function assertContextClosed(): void
    {
        try {
            app(CurrentCompany::class)->id();
            $this->fail('HTTP context leaked outside the request.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }
    }

    private function snapshot(string $path = '/_tenant/probe'): string
    {
        $response = $this->get($path)->assertOk();
        preg_match('/wire:snapshot="([^"]+)"/', $response->getContent(), $match);

        return html_entity_decode($match[1], ENT_QUOTES);
    }

    private function update(string $snapshot, array $updates = [], string $method = 'change')
    {
        // Real HTTP request through web + persistent middleware, never Livewire::test's middleware bypass.
        return $this->withHeader('X-Livewire', 'true')->postJson(app('livewire')->getUpdateUri(), [
            'components' => [['snapshot' => $snapshot, 'updates' => $updates,
                'calls' => [['path' => '', 'method' => $method, 'params' => []]]]],
        ]);
    }

    public function test_guest_is_denied_and_pending_user_can_view_real_pending_page(): void
    {
        $this->get('/pending-setup')->assertRedirect(route('login'));
        $this->assertContextClosed();
        $this->actingAs($this->a)->get('/pending-setup')->assertOk()->assertSee(__('authentication.pending'));
        $this->assertContextClosed();
    }

    public function test_trusted_database_identity_overrides_unsaved_fields_and_relations(): void
    {
        $this->a->company_id = $this->b->company_id;
        $this->a->setRelation('company', $this->b->company);
        $this->actingAs($this->a)->getJson('/_tenant/context')->assertExactJson(['company' => $this->sequenceA->company_id]);
        $this->assertContextClosed();
    }

    public function test_old_disabled_session_is_terminated_even_on_public_page(): void
    {
        $this->actingAs($this->a);
        User::whereKey($this->a->id)->update(['is_active' => false]);
        $this->a->company_id = $this->b->company_id;
        $this->get('/')->assertRedirect(route('login'));
        $this->assertDatabaseHas('users', ['id' => $this->a->id, 'company_id' => $this->sequenceA->company_id, 'is_active' => false]);
        $this->assertGuest();
        $this->assertContextClosed();
    }

    public function test_deleted_identity_cannot_keep_authenticated_company_context(): void
    {
        $this->actingAs($this->a);
        $this->a->delete();
        $this->getJson('/_tenant/context')->assertUnauthorized();
        $this->assertDatabaseMissing('users', ['id' => $this->a->id]);
        $this->assertGuest();
        $this->assertContextClosed();
    }

    public function test_unresolvable_company_is_rejected_without_disabling_foreign_keys(): void
    {
        // Simulate an unavailable/missing company without corrupting the database's FK constraints.
        Company::addGlobalScope('unavailable-for-test', fn ($query) => $query->whereRaw('1 = 0'));
        $this->actingAs($this->a)->getJson('/_tenant/context')->assertUnauthorized();
        $this->assertGuest();
        $this->assertContextClosed();
    }

    public function test_invalid_company_id_from_identity_hydration_is_rejected(): void
    {
        $this->actingAs($this->a);
        // Simulated invalid persisted identity; real FK constraints remain enabled and unchanged.
        Event::listen('eloquent.retrieved: '.User::class, function (User $user): void {
            $attributes = $user->getAttributes();
            $attributes['company_id'] = PHP_INT_MAX;
            $user->setRawAttributes($attributes, true);
        });
        $this->getJson('/_tenant/context')->assertUnauthorized();
        $this->assertGuest();
        $this->assertContextClosed();
    }

    public function test_guest_routes_redirect_authenticated_users_to_safe_pending_page(): void
    {
        $this->actingAs($this->a);
        foreach (['/login', '/forgot-password', '/reset-password/'.str_repeat('a', 64)] as $path) {
            $this->get($path)->assertRedirect(route('setup.pending'));
        }
        $this->assertContextClosed();
    }

    #[DataProvider('injectionSources')]
    public function test_client_company_id_is_rejected_even_when_it_matches(string $source, bool $own): void
    {
        $this->actingAs($this->a);
        $id = $own ? $this->a->company_id : $this->b->company_id;
        $response = match ($source) {
            'query' => $this->getJson('/_tenant/context?company_id='.$id),
            'url' => $this->getJson('/_tenant/url/'.$id),
            'header' => $this->withHeader('X-Company-Id', (string) $id)->getJson('/_tenant/context'),
            'body' => $this->postJson('/_tenant/context', ['company_id' => $id]),
            'nested' => $this->postJson('/_tenant/context', ['data' => ['company_id' => $this->b->company_id]]),
            'cookie' => $this->withCredentials()->withCookie('company_id', (string) $id)->getJson('/_tenant/context'),
        };
        $response->assertForbidden();
        $this->assertContextClosed();
    }

    public static function injectionSources(): array
    {
        return array_merge(
            array_map(fn ($source) => [$source, true], ['query', 'url', 'header', 'body', 'nested', 'cookie']),
            array_map(fn ($source) => [$source, false], ['query', 'url', 'header', 'body', 'nested', 'cookie']),
        );
    }

    public function test_scoped_binding_returns_404_before_controller_and_own_record_is_visible(): void
    {
        $this->actingAs($this->a)->getJson('/_tenant/sequence/'.$this->sequenceB->id)->assertNotFound();
        $this->getJson('/_tenant/sequence/'.$this->sequenceA->id)->assertExactJson(['id' => $this->sequenceA->id]);
        $this->assertContextClosed();
    }

    public function test_sequential_requests_and_exception_cannot_retain_company_context(): void
    {
        $this->actingAs($this->a)->getJson('/_tenant/context')->assertExactJson(['company' => $this->a->company_id]);
        $this->assertContextClosed();
        $this->withoutExceptionHandling();
        try {
            $this->get('/_tenant/crash');
            $this->fail('Exception missing.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Tenant probe failure.', $exception->getMessage());
        }
        $this->assertContextClosed();
        $this->actingAs($this->b)->getJson('/_tenant/context')->assertExactJson(['company' => $this->b->company_id]);
        $this->assertContextClosed();
    }

    public function test_pending_company_cannot_access_business_route_but_can_logout(): void
    {
        $this->actingAs($this->a)->get('/_tenant/business')->assertForbidden();
        $this->get('/pending-setup')->assertOk();
        $this->post('/logout')->assertRedirect(route('home'));
        $this->assertGuest();
        $this->assertContextClosed();
    }

    public function test_real_livewire_action_has_context_and_writes_only_own_company(): void
    {
        $this->actingAs($this->a);
        $snapshot = $this->snapshot();
        $this->update($snapshot, ['sequenceId' => $this->sequenceA->id])->assertOk();
        $this->assertDatabaseHas('document_sequences', ['id' => $this->sequenceA->id, 'next_number' => 2]);
        $this->update($snapshot, ['sequenceId' => $this->sequenceB->id])->assertNotFound();
        $this->assertDatabaseHas('document_sequences', ['id' => $this->sequenceB->id, 'next_number' => 1]);
        $this->assertContextClosed();
    }

    public function test_livewire_rejects_public_company_property_before_action(): void
    {
        $this->actingAs($this->a);
        $snapshot = $this->snapshot();
        $this->update($snapshot, ['sequenceId' => $this->sequenceA->id, 'company_id' => $this->a->company_id])->assertForbidden();
        $this->assertDatabaseHas('document_sequences', ['id' => $this->sequenceA->id, 'next_number' => 1]);
        $this->assertContextClosed();
    }

    public function test_livewire_guest_replaying_authenticated_snapshot_is_denied(): void
    {
        $this->actingAs($this->a);
        $snapshot = $this->snapshot();
        Auth::logout();
        $this->update($snapshot, ['sequenceId' => $this->sequenceA->id])->assertUnauthorized();
        $this->assertDatabaseHas('document_sequences', ['id' => $this->sequenceA->id, 'next_number' => 1]);
        $this->assertContextClosed();
    }

    public function test_livewire_disabled_existing_session_is_denied_before_action(): void
    {
        $this->actingAs($this->a);
        $snapshot = $this->snapshot();
        User::whereKey($this->a->id)->update(['is_active' => false]);
        $this->update($snapshot, ['sequenceId' => $this->sequenceA->id])->assertUnauthorized();
        $this->assertGuest();
        $this->assertDatabaseHas('document_sequences', ['id' => $this->sequenceA->id, 'next_number' => 1]);
        $this->assertContextClosed();
    }

    public function test_livewire_rechecks_readiness_after_initial_render(): void
    {
        Company::whereKey($this->a->company_id)->update(['status' => 'active']);
        $this->actingAs($this->a);
        $snapshot = $this->snapshot('/_tenant/business');
        Company::whereKey($this->a->company_id)->update(['status' => 'pending_setup']);
        $this->update($snapshot, ['sequenceId' => $this->sequenceA->id])->assertForbidden();
        $this->assertDatabaseHas('document_sequences', ['id' => $this->sequenceA->id, 'next_number' => 1]);
        $this->assertContextClosed();
    }

    public function test_livewire_snapshot_from_a_cannot_write_after_switching_to_b(): void
    {
        $this->actingAs($this->a);
        $snapshot = $this->snapshot();
        $this->actingAs($this->b);
        $this->update($snapshot, ['sequenceId' => $this->sequenceA->id])->assertNotFound();
        $this->assertDatabaseHas('document_sequences', ['id' => $this->sequenceA->id, 'next_number' => 1]);
        $this->assertContextClosed();
    }

    public function test_livewire_exception_also_closes_context(): void
    {
        $this->actingAs($this->a);
        $snapshot = $this->snapshot();
        $this->withoutExceptionHandling();
        try {
            $this->update($snapshot, [], 'crash');
            $this->fail('Exception missing.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Tenant probe failure.', $exception->getMessage());
        }
        $this->assertContextClosed();
    }
}
