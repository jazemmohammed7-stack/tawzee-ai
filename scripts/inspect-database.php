<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\DatabaseSafety;

require dirname(__DIR__).'/vendor/autoload.php';
$testing = in_array('--testing', $argv, true);
if ($testing) {
    putenv('APP_ENV=testing');
    $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'testing';
}
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment(['local', 'testing'])) {
    fwrite(STDERR, "Refusing to inspect a non-local environment.\n");
    exit(1);
}

$connection = config('database.default');
echo 'Configured driver: '.config('database.connections.'.$connection.'.driver').PHP_EOL;
echo 'PostgreSQL PHP driver: '.(extension_loaded('pdo_pgsql') ? 'available' : 'missing').PHP_EOL;

try {
    if ($testing) {
        DatabaseSafety::verify(
            $app->environment(),
            $connection,
            config('database.connections.'.$connection, []),
            config('database.connections.mysql', []),
        );
        DatabaseSafety::verifyLocalDevelopment(
            $app->environment(), $connection, config('database.connections.'.$connection, []), $app->basePath(),
        );
    }
    $pdo = DB::connection()->getPdo();
    if (config('database.connections.'.$connection.'.driver') === 'mysql') {
        if ($testing) {
            DatabaseSafety::verifyMysqlIdentity($pdo, config('database.connections.'.$connection));
        }
        $identity = $pdo->query('SELECT VERSION() AS version, DATABASE() AS db, CURRENT_USER() AS account')->fetch(PDO::FETCH_ASSOC);
        if (str_starts_with($identity['account'], 'root@')) {
            throw new RuntimeException('Administrative application account prohibited.');
        }
        echo 'Server: '.$identity['version'].'; database: '.$identity['db'].'; account: '.$identity['account'].PHP_EOL;
        echo 'Existing tables: '.$pdo->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchColumn().PHP_EOL;
    }
    echo "Configured database connection: successful (no values displayed).\n";
} catch (Throwable $exception) {
    // Connection exceptions can contain credentials, so do not print them.
    fwrite(STDERR, "Configured database connection: failed; check local settings privately.\n");
    exit(1);
}
