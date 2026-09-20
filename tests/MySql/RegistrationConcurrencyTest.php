<?php

declare(strict_types=1);

namespace Tests\MySql;

use App\Models\User;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\DocumentSequence;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class RegistrationConcurrencyTest extends TestCase
{
    public function test_two_real_connections_racing_for_same_email_commit_exactly_one_complete_company(): void
    {
        $this->assertSame('mysql_testing', config('database.default'));
        $marker = 'registration-race-'.Str::uuid();
        $input = ['company_name' => $marker, 'name' => 'Concurrency fixture', 'email' => $marker.'@example.test',
            'password' => 'Test-only-password-123', 'password_confirmation' => 'Test-only-password-123'];
        $workers = [];
        $streams = [];
        try {
            for ($i = 0; $i < 2; $i++) {
                $streams[$i] = new InputStream;
                $streams[$i]->write(json_encode($input)."\n");
                $workers[$i] = new Process([PHP_BINARY, base_path('tests/Support/registration-worker.php')], base_path(), input: $streams[$i], timeout: 20);
                $workers[$i]->start();
            }
            $deadline = microtime(true) + 12;
            do {
                $ready = count(array_filter($workers, fn (Process $worker) => str_contains($worker->getOutput(), "ready\n")));
                if ($ready === 2) {
                    break;
                }
                usleep(20000);
            } while (microtime(true) < $deadline);
            $this->assertSame(2, $ready, 'Both independent transactions must reach the barrier after validation.');
            foreach ($streams as $stream) {
                $stream->write("go\n");
                $stream->close();
            }
            foreach ($workers as $worker) {
                $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());
            }
            $results = array_map(fn (Process $worker) => trim(str_replace("ready\n", '', $worker->getOutput())), $workers);
            sort($results);
            $this->assertSame(['duplicate', 'success'], $results);
            $company = Company::where('name', $marker)->sole();
            $this->assertSame('pending_setup', $company->status);
            $this->assertSame($company->founder_user_id, User::where('email', $input['email'])->sole()->id);
            $this->assertSame(1, $company->users()->count());
            app(CurrentCompany::class)->run($company, function () use ($company): void {
                $this->assertSame(count(DocumentSequence::INITIAL_TYPES), $company->documentSequences()->count());
                $this->assertSame([1], $company->documentSequences()->pluck('next_number')->unique()->values()->all());
            });
        } finally {
            foreach ($streams as $stream) {
                $stream->close();
            }
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            // Only this UUID's committed test fixtures; no table reset or development access.
            foreach (Company::where('name', $marker)->get() as $company) {
                DB::transaction(function () use ($company): void {
                    $company->founder_user_id = null;
                    $company->save();
                    app(CurrentCompany::class)->run($company, fn () => $company->documentSequences()->delete());
                    $company->users()->delete();
                    $company->delete();
                });
            }
        }
    }
}
