<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\User;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\DocumentSequence;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CompanySchemaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_defaults_and_factory_relationship_are_persisted(): void
    {
        $user = User::factory()->create()->refresh();
        $company = $user->company;
        $this->assertSame('YER', $company->currency_code);
        $this->assertSame('Asia/Aden', $company->timezone);
        $this->assertSame('pending_setup', $company->status);
        $this->assertFalse($company->allow_negative_stock);
        $this->assertTrue($user->is_active);
        $this->assertTrue($company->users->sole()->is($user));
    }

    public function test_company_relationships_do_not_return_another_company_records(): void
    {
        $a = Company::factory()->create();
        $b = Company::factory()->create();
        $userA = User::factory()->for($a)->create();
        $userB = User::factory()->for($b)->create();
        $sequenceA = app(CurrentCompany::class)->run($a, fn () => $a->documentSequences()->create(['type' => 'invoice']));
        $sequenceB = app(CurrentCompany::class)->run($b, fn () => $b->documentSequences()->create(['type' => 'invoice']));

        $this->assertSame([$userA->id], $a->users()->pluck('id')->all());
        $this->assertNull($a->users()->find($userB->id));
        $this->assertSame([$sequenceA->id], app(CurrentCompany::class)->run($a, fn () => $a->documentSequences()->pluck('id')->all()));
        $this->assertNull(app(CurrentCompany::class)->run($a, fn () => $a->documentSequences()->find($sequenceB->id)));
        $this->assertTrue(app(CurrentCompany::class)->run($b, fn () => $sequenceB->company->is($b)));
        $this->assertSame(1, app(CurrentCompany::class)->run($a, fn () => $sequenceA->refresh()->next_number));

        $this->expectException(ModelNotFoundException::class);
        app(CurrentCompany::class)->run($a, fn () => $a->documentSequences()->findOrFail($sequenceB->id));
    }

    public function test_company_id_and_sensitive_flags_cannot_be_mass_assigned(): void
    {
        $user = new User(['company_id' => 123, 'is_active' => false]);
        $sequence = new DocumentSequence(['company_id' => 123, 'next_number' => 999]);
        $company = new Company(['status' => 'active', 'allow_negative_stock' => true]);
        $this->assertNull($user->company_id);
        $this->assertNull($user->is_active);
        $this->assertNull($sequence->company_id);
        $this->assertNull($sequence->next_number);
        $this->assertNull($company->status);
        $this->assertNull($company->allow_negative_stock);
    }

    public function test_relationship_creation_ignores_a_forged_company_id(): void
    {
        $a = Company::factory()->create();
        $b = Company::factory()->create();
        $sequence = app(CurrentCompany::class)->run($a, fn () => $a->documentSequences()->create(['type' => 'receipt', 'company_id' => $b->id]));
        $this->assertSame($a->id, $sequence->company_id);
        $this->assertFalse(app(CurrentCompany::class)->run($b, fn () => $b->documentSequences()->exists()));
    }

    public function test_sequence_type_is_unique_within_each_company(): void
    {
        $company = Company::factory()->create();
        app(CurrentCompany::class)->run($company, fn () => $company->documentSequences()->create(['type' => 'invoice']));
        $this->expectException(QueryException::class);
        app(CurrentCompany::class)->run($company, fn () => $company->documentSequences()->create(['type' => 'invoice']));
    }

    public function test_sequence_requires_an_existing_company(): void
    {
        $this->expectException(QueryException::class);
        DB::table('document_sequences')->insert(['company_id' => PHP_INT_MAX, 'type' => 'invoice', 'next_number' => 1]);
    }

    public function test_sequence_rejects_non_positive_next_number_in_database(): void
    {
        $company = Company::factory()->create();
        $this->expectException(QueryException::class);
        app(CurrentCompany::class)->run($company, fn () => $company->documentSequences()->create(['type' => 'invoice'])->forceFill(['next_number' => 0])->save());
    }

    public function test_user_requires_a_company(): void
    {
        $this->expectException(QueryException::class);
        DB::table('users')->insert(['name' => 'Unassigned', 'email' => 'unassigned@example.test', 'password' => 'test-only']);
    }

    public function test_user_foreign_key_rejects_nonexistent_company(): void
    {
        $this->expectException(QueryException::class);
        DB::table('users')->insert(['name' => 'Invalid', 'email' => 'invalid@example.test', 'password' => 'test-only', 'company_id' => PHP_INT_MAX]);
    }

    public function test_email_remains_globally_unique_across_companies(): void
    {
        User::factory()->create(['email' => 'unique@example.test']);
        $this->expectException(QueryException::class);
        User::factory()->create(['email' => 'unique@example.test']);
    }

    public function test_company_with_users_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $this->expectException(QueryException::class);
        $user->company->delete();
    }

    public function test_company_with_sequences_cannot_be_deleted(): void
    {
        $company = Company::factory()->create();
        app(CurrentCompany::class)->run($company, fn () => $company->documentSequences()->create(['type' => 'invoice']));
        $this->expectException(QueryException::class);
        $company->delete();
    }

    public function test_tenant_indexes_and_non_nullable_company_column_exist(): void
    {
        $indexes = Schema::getIndexes('document_sequences');
        $this->assertNotEmpty(array_filter($indexes, fn (array $index): bool => $index['unique'] && $index['columns'] === ['company_id', 'type']));
        $userIndexes = Schema::getIndexes('users');
        $this->assertNotEmpty(array_filter($userIndexes, fn (array $index): bool => $index['columns'] === ['company_id']));
        $column = array_values(array_filter(Schema::getColumns('users'), fn (array $column): bool => $column['name'] === 'company_id'))[0];
        $this->assertFalse($column['nullable']);
    }
}
