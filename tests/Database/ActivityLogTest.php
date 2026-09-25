<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\User;
use App\Modules\Access\Actions\RecordActivity;
use App\Modules\Access\Models\ActivityLog;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\DocumentSequence;
use App\Modules\Identity\Actions\RegisterCompany;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\InteractsWithTenantIsolation;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithTenantIsolation;

    public function test_logger_records_current_company_actor_action_subject_and_properties(): void
    {
        $user = User::factory()->for(Company::factory())->create()->refresh();
        $subject = $this->withinTenant($user, fn () => DocumentSequence::create(['type' => 'invoice']));

        $log = $this->withinTenant($user, fn () => app(RecordActivity::class)->record(
            'document_sequence.viewed', $subject, ['source' => 'test', 'count' => 1],
        ));

        $this->assertSame($user->company_id, $log->company_id);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('document_sequence.viewed', $log->action);
        $this->assertSame($subject->getMorphClass(), $log->subject_type);
        $this->assertSame($subject->id, $log->subject_id);
        $this->assertSame(['source' => 'test', 'count' => 1], $log->properties);
        $this->withinTenant($user, function () use ($log, $subject, $user): void {
            $this->assertTrue($log->actor->is($user));
            $this->assertTrue($log->subject->is($subject));
        });
    }

    public function test_system_activity_has_nullable_actor(): void
    {
        $company = Company::factory()->create();
        Auth::forgetGuards();

        $log = app(CurrentCompany::class)->run($company, fn () => app(RecordActivity::class)
            ->record('company.bootstrap_checked', $company));

        $this->assertNull($log->user_id);
        $this->assertNull($log->actor);
        $this->assertSame($company->id, $log->company_id);
    }

    public function test_sensitive_properties_are_recursively_redacted(): void
    {
        $user = User::factory()->for(Company::factory())->create()->refresh();
        $subject = $this->withinTenant($user, fn () => DocumentSequence::create(['type' => 'receipt']));
        $properties = [
            'safe' => 'visible',
            'password' => 'never-store-this',
            'nested' => [
                'Authorization' => 'Bearer secret-token',
                'request' => ['csrf_token' => 'csrf-secret', 'label' => 'visible too'],
                'smtp_credentials' => ['username' => 'mail', 'password' => 'mail-secret'],
            ],
        ];

        $log = $this->withinTenant($user, fn () => app(RecordActivity::class)
            ->record('security.redaction_tested', $subject, $properties));
        $encoded = json_encode($log->properties, JSON_THROW_ON_ERROR);

        $this->assertSame('visible', $log->properties['safe']);
        $this->assertSame('[REDACTED]', $log->properties['password']);
        $this->assertSame('[REDACTED]', $log->properties['nested']['Authorization']);
        $this->assertSame('[REDACTED]', $log->properties['nested']['request']['csrf_token']);
        $this->assertSame('visible too', $log->properties['nested']['request']['label']);
        $this->assertSame('[REDACTED]', $log->properties['nested']['smtp_credentials']);
        foreach (['never-store-this', 'secret-token', 'csrf-secret', 'mail-secret'] as $secret) {
            $this->assertStringNotContainsString($secret, $encoded);
        }
    }

    public function test_logs_are_tenant_scoped_and_route_binding_hides_foreign_logs(): void
    {
        $pair = $this->tenantPair(function () {
            $subject = DocumentSequence::create(['type' => 'invoice']);

            return app(RecordActivity::class)->record('sequence.created', $subject);
        });
        $this->assertTenantReadIsolation($pair, fn ($resource) => ActivityLog::find($resource->getKey()));

        Route::middleware(['web', 'auth'])->get('/_activity-log-test/{activityLog}',
            fn (ActivityLog $activityLog) => ['id' => $activityLog->id]);
        $this->withinTenant($pair['userA'], function () use ($pair): void {
            $this->getJson('/_activity-log-test/'.$pair['resourceA']->id)
                ->assertOk()->assertExactJson(['id' => $pair['resourceA']->id]);
            $this->getJson('/_activity-log-test/'.$pair['resourceB']->id)->assertNotFound();
        });
    }

    public function test_cross_company_actor_and_subject_are_rejected(): void
    {
        $userA = User::factory()->for(Company::factory())->create()->refresh();
        $userB = User::factory()->for(Company::factory())->create()->refresh();
        $subjectB = $this->withinTenant($userB, fn () => DocumentSequence::create(['type' => 'invoice']));

        $this->expectException(AuthorizationException::class);
        $this->withinTenant($userA, fn () => app(RecordActivity::class)->record('sequence.viewed', $subjectB));
    }

    public function test_logger_does_not_accept_caller_owned_company_or_actor_arguments(): void
    {
        $method = new \ReflectionMethod(RecordActivity::class, 'record');
        $this->assertSame(['action', 'subject', 'properties'], array_map(
            fn (\ReflectionParameter $parameter) => $parameter->getName(), $method->getParameters(),
        ));
    }

    #[DataProvider('forbiddenMutations')]
    public function test_activity_logs_are_append_only(string $operation): void
    {
        $user = User::factory()->for(Company::factory())->create()->refresh();
        $log = $this->withinTenant($user, function () {
            $subject = DocumentSequence::create(['type' => 'invoice']);

            return app(RecordActivity::class)->record('sequence.created', $subject);
        });
        $before = $log->getRawOriginal();

        try {
            $this->withinTenant($user, fn () => match ($operation) {
                'save' => $log->forceFill(['action' => 'forged'])->save(),
                'save quietly' => $log->forceFill(['action' => 'forged'])->saveQuietly(),
                'update' => $log->update(['action' => 'forged']),
                'update quietly' => $log->updateQuietly(['action' => 'forged']),
                'delete' => $log->delete(),
                'delete quietly' => $log->deleteQuietly(),
                'increment' => $log->increment('subject_id'),
                'bulk update' => ActivityLog::whereKey($log->id)->update(['action' => 'forged']),
                'bulk delete' => ActivityLog::whereKey($log->id)->delete(),
                'bulk force delete' => ActivityLog::whereKey($log->id)->forceDelete(),
                'truncate' => ActivityLog::query()->truncate(),
            });
            $this->fail('Append-only mutation was accepted.');
        } catch (LogicException) {
            $stored = $this->withinTenant($user, fn () => ActivityLog::findOrFail($log->id));
            $this->assertEquals($before, $stored->getRawOriginal());
        }
    }

    public static function forbiddenMutations(): array
    {
        return array_map(fn ($operation) => [$operation], [
            'save', 'save quietly', 'update', 'update quietly', 'delete', 'delete quietly',
            'increment', 'bulk update', 'bulk delete', 'bulk force delete', 'truncate',
        ]);
    }

    public function test_business_record_and_activity_log_commit_and_roll_back_together(): void
    {
        $user = User::factory()->for(Company::factory())->create()->refresh();
        $beforeLogs = $this->withinTenant($user, fn () => ActivityLog::count());
        $beforeSequences = $this->withinTenant($user, fn () => DocumentSequence::count());

        try {
            $this->withinTenant($user, fn () => DB::transaction(function (): void {
                $subject = DocumentSequence::create(['type' => 'receipt']);
                app(RecordActivity::class)->record('sequence.created', $subject);
                throw new RuntimeException('rollback');
            }));
            $this->fail('Rollback probe did not fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('rollback', $exception->getMessage());
        }
        $this->assertSame($beforeLogs, $this->withinTenant($user, fn () => ActivityLog::count()));
        $this->assertSame($beforeSequences, $this->withinTenant($user, fn () => DocumentSequence::count()));

        $this->withinTenant($user, fn () => DB::transaction(function (): void {
            $subject = DocumentSequence::create(['type' => 'receipt']);
            app(RecordActivity::class)->record('sequence.created', $subject);
        }));
        $this->assertSame($beforeLogs + 1, $this->withinTenant($user, fn () => ActivityLog::count()));
        $this->assertSame($beforeSequences + 1, $this->withinTenant($user, fn () => DocumentSequence::count()));
    }

    public function test_registration_and_activity_log_roll_back_together_when_logging_fails(): void
    {
        $before = array_map(fn ($table) => DB::table($table)->count(),
            ['companies', 'users', 'document_sequences', 'activity_logs']);
        Event::listen('eloquent.creating: '.ActivityLog::class, fn () => throw new RuntimeException('log failed'));

        try {
            app(RegisterCompany::class)->handle([
                'company_name' => 'Audit Rollback Company', 'name' => 'Audit Tester',
                'email' => 'audit-rollback@example.test', 'password' => 'Test-only-password-123',
                'password_confirmation' => 'Test-only-password-123',
            ]);
            $this->fail('Registration succeeded without its activity log.');
        } catch (RuntimeException $exception) {
            $this->assertSame('log failed', $exception->getMessage());
        }

        $after = array_map(fn ($table) => DB::table($table)->count(),
            ['companies', 'users', 'document_sequences', 'activity_logs']);
        $this->assertSame($before, $after);
    }
}
