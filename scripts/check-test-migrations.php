<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\DatabaseSafety;

require dirname(__DIR__).'/vendor/autoload.php';
putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'testing';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$connection = config('database.default');
$testing = config('database.connections.'.$connection, []);
DatabaseSafety::verify($app->environment(), $connection, $testing, config('database.connections.mysql', []));
DatabaseSafety::verifyLocalDevelopment($app->environment(), $connection, $testing, $app->basePath());
if ($connection !== 'mysql_testing') {
    throw new RuntimeException('This round-trip check is restricted to the dedicated MySQL test database.');
}
DatabaseSafety::verifyMysqlIdentity(DB::connection()->getPdo(), $testing);

// Only the three new migrations, on an empty, verified disposable test schema.
$expected = [
    '2026_09_20_000100_create_companies_table',
    '2026_09_20_000200_add_company_to_users_table',
    '2026_09_20_000300_create_document_sequences_table',
];
$latest = DB::table('migrations')->orderByDesc('id')->limit(3)->pluck('migration')->reverse()->values()->all();
if ($latest !== $expected) {
    throw new RuntimeException('Unexpected migration history; refusing rollback.');
}
foreach (['users', 'companies', 'document_sequences'] as $table) {
    if (DB::table($table)->exists()) {
        throw new RuntimeException('Test schema contains tenant data; refusing rollback.');
    }
}
$before = DB::table('migrations')->count();
$status = Artisan::call('migrate:rollback', ['--database' => 'mysql_testing', '--step' => 3, '--no-interaction' => true]);
if ($status !== 0 || DB::table('migrations')->count() !== $before - 3) {
    throw new RuntimeException('Rollback check failed; inspect the test database before continuing.');
}
echo "P1-T01: three test migrations rolled back; baseline preserved.\n";
$status = Artisan::call('migrate', ['--database' => 'mysql_testing', '--no-interaction' => true]);
if ($status !== 0 || DB::table('migrations')->count() !== $before) {
    throw new RuntimeException('Reapply check failed.');
}
echo "P1-T01: three test migrations reapplied. Development database untouched.\n";
