<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Access\Models\Role;
use App\Modules\Identity\Actions\CreateUser;
use App\Modules\Identity\Actions\RegisterCompany;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\DatabaseSafety;

require dirname(__DIR__, 2).'/vendor/autoload.php';

try {
    foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql_testing', 'DB_URL' => '', 'LOG_CHANNEL' => 'null',
        'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'BCRYPT_ROUNDS' => '4'] as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $_SERVER[$key] = $value;
    }
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $config = config('database.connections.mysql_testing');
    DatabaseSafety::verify($app->environment(), config('database.default'), $config, config('database.connections.mysql'));
    DatabaseSafety::verifyLocalDevelopment($app->environment(), config('database.default'), $config, $app->basePath());
    DatabaseSafety::verifyMysqlIdentity(DB::connection()->getPdo(), $config);
    $marker = $argv[2] ?? '';
    if (! preg_match('/^[a-f0-9-]{36}$/', $marker)) {
        throw new RuntimeException('Invalid fixture marker.');
    }
    $email = 'users-browser-'.$marker.'@example.test';
    $mode = $argv[1] ?? '';
    if ($mode === 'setup') {
        DB::transaction(function () use ($email, $marker): void {
            $company = app(RegisterCompany::class)->handle(['company_name' => 'شركة المدار للتوزيع', 'name' => 'أحمد السقاف',
                'email' => $email, 'password' => 'Browser-test-only-password-123', 'password_confirmation' => 'Browser-test-only-password-123']);
            Auth::setUser($company->founder);
            app(CurrentCompany::class)->run($company, function () use ($marker): void {
                $roles = Role::pluck('id', 'name');
                foreach (['سارة أحمد', 'محمد علي', 'خالد سالم', 'فاطمة حسن', 'عبدالله عمر', 'مريم صالح', 'علي ياسين', 'هدى عبدالله', 'نبيل سعيد', 'رنا محمد', 'صالح ناصر'] as $index => $name) {
                    $user = app(CreateUser::class)->handle(['name' => $name, 'email' => 'member-'.$index.'-'.$marker.'@example.test',
                        'password' => 'Browser-test-only-password-123', 'password_confirmation' => 'Browser-test-only-password-123',
                        'role_id' => $roles[['admin', 'sales', 'warehouse', 'accountant', 'viewer'][$index % 5]]]);
                    if ($index === 3) {
                        $user->is_active = false;
                        $user->save();
                    }
                }
            });
        });
    } elseif ($mode === 'cleanup') {
        $owner = User::where('email', $email)->first();
        if ($owner) {
            $company = $owner->company;
            if ((int) $company->founder_user_id !== (int) $owner->id) {
                throw new RuntimeException('Fixture ownership changed; cleanup refused.');
            }
            DB::transaction(function () use ($company): void {
                // Test-only cleanup: raw deletes are constrained to this UUID-owned company.
                $id = $company->id;
                $roleIds = DB::table('roles')->where('company_id', $id)->pluck('id');
                foreach (['activity_logs', 'document_sequences', 'model_has_roles', 'model_has_permissions'] as $table) {
                    DB::table($table)->where('company_id', $id)->delete();
                }
                DB::table('role_has_permissions')->whereIn('role_id', $roleIds)->delete();
                DB::table('roles')->where('company_id', $id)->delete();
                $company->founder_user_id = null;
                $company->save();
                User::where('company_id', $id)->delete();
                $company->delete();
            });
        }
        if (User::where('email', $email)->exists()) {
            throw new RuntimeException('Fixture cleanup incomplete.');
        }
    } else {
        throw new RuntimeException('Invalid fixture mode.');
    }
    echo "ok\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class."\n");
    exit(1);
}
