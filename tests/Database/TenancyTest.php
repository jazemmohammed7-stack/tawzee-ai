<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\User;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\DocumentSequence;
use App\Support\Tenancy\CompanyScope;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithTenantIsolation;
use Tests\TestCase;

class TenancyTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithTenantIsolation;

    private Company $a;

    private Company $b;

    private User $userA;

    private User $userB;

    private DocumentSequence $sequenceA;

    private DocumentSequence $sequenceB;

    protected function setUp(): void
    {
        parent::setUp();
        $pair = $this->tenantPair(fn () => DocumentSequence::create(['type' => 'invoice']));
        $this->userA = $pair['userA'];
        $this->userB = $pair['userB'];
        $this->a = $this->userA->company;
        $this->b = $this->userB->company;
        $this->sequenceA = $pair['resourceA'];
        $this->sequenceB = $pair['resourceB'];
    }

    public function test_authenticated_user_only_reads_own_rows_even_with_an_or_condition(): void
    {
        $this->assertTenantReadIsolation([
            'userA' => $this->userA, 'userB' => $this->userB, 'resourceA' => $this->sequenceA, 'resourceB' => $this->sequenceB,
        ], fn ($resource) => DocumentSequence::find($resource->getKey()));
        $this->actingAs($this->userA);
        $this->assertSame([$this->sequenceA->id], DocumentSequence::query()->pluck('id')->all());
        $this->assertNull(DocumentSequence::find($this->sequenceB->id));
        $this->assertSame(1, DocumentSequence::where('type', 'invoice')->orWhere('id', $this->sequenceB->id)->count());
        $this->assertFalse($this->b->documentSequences()->exists());
        $this->assertTrue(DocumentSequence::with('company')->sole()->company->is($this->a));
    }

    public function test_scoped_bulk_write_and_delete_cannot_touch_other_company(): void
    {
        $this->actingAs($this->userA);
        $this->assertSame(0, DocumentSequence::whereKey($this->sequenceB->id)->update(['next_number' => 9]));
        $this->assertSame(0, DocumentSequence::whereKey($this->sequenceB->id)->delete());
        $this->assertSame(0, DocumentSequence::whereKey($this->sequenceB->id)->forceDelete());
        $this->assertSame(1, DocumentSequence::whereKey($this->sequenceA->id)->increment('next_number'));
        $this->assertSame(2, $this->sequenceA->refresh()->next_number);
        $this->actingAs($this->userB);
        $this->assertSame(1, $this->sequenceB->refresh()->next_number);
    }

    #[DataProvider('loadedForeignOperations')]
    public function test_preloaded_foreign_model_cannot_be_written(string $operation): void
    {
        $this->actingAs($this->userA);
        try {
            match ($operation) {
                'save' => $this->sequenceB->forceFill(['next_number' => 7])->save(),
                'quiet save' => $this->sequenceB->forceFill(['next_number' => 7])->saveQuietly(),
                'delete' => $this->sequenceB->delete(),
                'quiet delete' => $this->sequenceB->deleteQuietly(),
                'increment' => $this->sequenceB->increment('next_number'),
                'forge owner' => $this->sequenceB->forceFill(['company_id' => $this->a->id])->save(),
            };
            $this->fail('Foreign write was accepted.');
        } catch (AuthorizationException) {
            $this->actingAs($this->userB);
            $stored = DocumentSequence::findOrFail($this->sequenceB->id);
            $this->assertSame($this->b->id, $stored->company_id);
            $this->assertSame(1, $stored->next_number);
        }
    }

    public static function loadedForeignOperations(): array
    {
        return array_map(fn ($operation) => [$operation], ['save', 'quiet save', 'delete', 'quiet delete', 'increment', 'forge owner']);
    }

    public function test_creation_fills_company_and_ignores_request_company_id(): void
    {
        $this->actingAs($this->userA);
        $created = DocumentSequence::create(['type' => 'receipt', 'company_id' => $this->b->id]);
        $this->assertSame($this->a->id, $created->refresh()->company_id);
        $this->assertSame(1, $created->next_number);
    }

    public function test_own_model_save_and_delete_work_and_bulk_inserts_get_trusted_company(): void
    {
        $this->actingAs($this->userA);
        $this->sequenceA->next_number = 5;
        $this->assertTrue($this->sequenceA->saveQuietly());
        $this->assertSame(5, $this->sequenceA->refresh()->next_number);
        DocumentSequence::insert([['type' => 'receipt'], ['type' => 'opening']]);
        $this->assertSame([$this->a->id], DocumentSequence::query()->distinct()->pluck('company_id')->all());
        $this->assertSame(3, DocumentSequence::count());
        $this->assertTrue($this->sequenceA->deleteQuietly());
        $this->assertNull(DocumentSequence::find($this->sequenceA->id));
    }

    public function test_unsaved_company_cannot_supply_internal_context(): void
    {
        $this->expectException(AuthorizationException::class);
        app(CurrentCompany::class)->run(new Company(['name' => 'Untrusted']), fn () => DocumentSequence::count());
    }

    public function test_escaping_job_exception_clears_context_before_next_operation(): void
    {
        $context = app(CurrentCompany::class);
        try {
            $context->run($this->a, fn () => throw new \RuntimeException('failed'));
        } catch (\RuntimeException $exception) {
            $this->assertSame('failed', $exception->getMessage());
        }
        $this->expectException(AuthorizationException::class);
        DocumentSequence::count();
    }

    public function test_creation_without_context_is_rejected_even_without_events(): void
    {
        $this->expectException(AuthorizationException::class);
        (new DocumentSequence(['type' => 'receipt']))->saveQuietly();
    }

    public function test_read_without_context_is_fail_closed(): void
    {
        $this->expectException(AuthorizationException::class);
        DocumentSequence::count();
    }

    #[DataProvider('invalidAssociations')]
    public function test_cross_company_associations_are_rejected(string $operation): void
    {
        $this->actingAs($this->userA);
        $this->expectException(AuthorizationException::class);
        match ($operation) {
            'create through foreign company' => $this->b->documentSequences()->create(['type' => 'receipt']),
            'save through foreign company' => $this->b->documentSequences()->save($this->sequenceA),
            'associate' => $this->sequenceA->company()->associate($this->b)->save(),
            'direct assignment' => $this->sequenceA->forceFill(['company_id' => $this->b->id])->saveQuietly(),
        };
    }

    public static function invalidAssociations(): array
    {
        return array_map(fn ($operation) => [$operation], ['create through foreign company', 'save through foreign company', 'associate', 'direct assignment']);
    }

    #[DataProvider('unsafeBulkOperations')]
    public function test_bulk_shortcuts_cannot_reassign_or_bypass_company(string $operation): void
    {
        $this->actingAs($this->userA);
        $this->expectException(AuthorizationException::class);
        match ($operation) {
            'update owner' => DocumentSequence::query()->update(['company_id' => $this->b->id]),
            'increment owner' => DocumentSequence::query()->increment('company_id'),
            'increment extras' => DocumentSequence::query()->increment('next_number', 1, ['company_id' => $this->b->id]),
            'insert foreign' => DocumentSequence::insert(['company_id' => $this->b->id, 'type' => 'receipt']),
            'upsert' => DocumentSequence::upsert(['id' => $this->sequenceB->id, 'type' => 'receipt'], ['id']),
            'updateOrInsert' => DocumentSequence::updateOrInsert(['id' => $this->sequenceB->id], ['type' => 'receipt']),
            'remove scope' => DocumentSequence::withoutGlobalScope(CompanyScope::class)->get(),
            'remove all scopes' => DocumentSequence::withoutGlobalScopes()->get(),
        };
    }

    public static function unsafeBulkOperations(): array
    {
        return array_map(fn ($operation) => [$operation], ['update owner', 'increment owner', 'increment extras', 'insert foreign', 'upsert', 'updateOrInsert', 'remove scope', 'remove all scopes']);
    }

    public function test_foreign_model_refresh_and_queue_restoration_use_company_scope(): void
    {
        $this->actingAs($this->userA);
        $this->assertNull($this->sequenceB->fresh());
        $this->assertFalse((new DocumentSequence)->newQueryForRestoration($this->sequenceB->id)->exists());
        $this->expectException(ModelNotFoundException::class);
        $this->sequenceB->refresh();
    }

    public function test_laravel_binding_on_test_only_route_returns_404_and_ignores_input_context(): void
    {
        // Exercises Laravel binding with auth; this is NOT the future P1-T05 middleware or a production endpoint.
        Route::middleware(['web', 'auth'])->get('/_tenancy-test/{sequence}', fn (DocumentSequence $sequence) => ['id' => $sequence->id]);
        $this->actingAs($this->userA);
        $this->withHeader('X-Company-Id', (string) $this->b->id)
            ->getJson('/_tenancy-test/'.$this->sequenceB->id.'?company_id='.$this->b->id)->assertNotFound();
        $this->getJson('/_tenancy-test/'.$this->sequenceA->id)->assertOk()->assertExactJson(['id' => $this->sequenceA->id]);
    }

    public function test_internal_context_is_restored_after_nested_failure_and_next_job(): void
    {
        $context = app(CurrentCompany::class);
        $context->run($this->a, function () use ($context): void {
            try {
                $context->run($this->b, function () use ($context): void {
                    $this->assertSame($this->b->id, $context->id());
                    throw new \RuntimeException('job failed');
                });
            } catch (\RuntimeException $exception) {
                $this->assertSame('job failed', $exception->getMessage());
            }
            $this->assertSame($this->a->id, $context->id());
        });
        $this->assertSame($this->b->id, $context->run($this->b, fn () => $context->id()));
        $this->expectException(AuthorizationException::class);
        $context->id();
    }

    public function test_authenticated_identity_is_not_cached_or_changed_by_unsaved_attributes(): void
    {
        $context = app(CurrentCompany::class);
        $this->actingAs($this->userA);
        $this->userA->company_id = $this->b->id;
        $this->assertSame($this->a->id, $context->id());
        $this->actingAs($this->userB);
        $this->assertSame($this->b->id, $context->id());
        Auth::forgetGuards();
        $this->expectException(AuthorizationException::class);
        $context->id();
    }

    public function test_internal_context_cannot_override_authenticated_company(): void
    {
        $this->actingAs($this->userA);
        $context = app(CurrentCompany::class);
        try {
            $context->run($this->b, fn () => DocumentSequence::count());
            $this->fail('Conflicting authenticated context was accepted.');
        } catch (AuthorizationException) {
            $this->assertSame($this->a->id, $context->id());
        }
    }

    public function test_container_scope_reset_drops_internal_context(): void
    {
        $context = app(CurrentCompany::class);
        $context->run($this->a, function () use ($context): void {
            app()->forgetScopedInstances();
            $this->assertNotSame($context, app(CurrentCompany::class));
            $this->expectException(AuthorizationException::class);
            app(CurrentCompany::class)->id();
        });
    }

    public function test_authentication_provider_still_resolves_and_validates_users_without_tenant_context(): void
    {
        $provider = Auth::guard()->getProvider();
        $found = $provider->retrieveByCredentials(['email' => $this->userB->email, 'password' => 'password']);
        $this->assertTrue($found->is($this->userB));
        $this->assertTrue($provider->validateCredentials($found, ['password' => 'password']));
        $this->assertFalse($provider->validateCredentials($found, ['password' => 'incorrect']));
        $this->assertTrue($provider->retrieveById($this->userA->id)->is($this->userA));
    }
}
