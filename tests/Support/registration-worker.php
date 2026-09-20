<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Actions\RegisterCompany;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\DatabaseSafety;

require dirname(__DIR__, 2).'/vendor/autoload.php';

try {
    foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql_testing', 'DB_URL' => '', 'LOG_CHANNEL' => 'null', 'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'BCRYPT_ROUNDS' => '4'] as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $_SERVER[$key] = $value;
    }
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $config = config('database.connections.mysql_testing');
    DatabaseSafety::verify($app->environment(), config('database.default'), $config, config('database.connections.mysql'));
    DatabaseSafety::verifyLocalDevelopment($app->environment(), config('database.default'), $config, $app->basePath());
    DatabaseSafety::verifyMysqlIdentity(DB::connection()->getPdo(), $config);
    $input = json_decode(trim(fgets(STDIN)), true, flags: JSON_THROW_ON_ERROR);
    User::creating(function (): void {
        echo "ready\n";
        fflush(STDOUT);
        if (trim(fgets(STDIN)) !== 'go') {
            throw new RuntimeException('Missing concurrency barrier.');
        }
    });
    app(RegisterCompany::class)->handle($input);
    echo "success\n";
} catch (ValidationException $exception) {
    echo isset($exception->errors()['email']) ? "duplicate\n" : "invalid\n";
} catch (Throwable $exception) {
    // Never emit connection/SQL/input details, even on worker failures.
    fwrite(STDERR, $exception::class."\n");
    exit(1);
}
