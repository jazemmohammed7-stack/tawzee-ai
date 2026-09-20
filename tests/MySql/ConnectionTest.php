<?php

declare(strict_types=1);

namespace Tests\MySql;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ConnectionTest extends TestCase
{
    public function test_real_connection_baseline_schema_and_innodb(): void
    {
        $this->assertSame('mysql_testing', config('database.default'));
        foreach (['migrations', 'users', 'cache', 'jobs'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
        $engines = DB::select('SELECT DISTINCT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()');
        $this->assertSame(['InnoDB'], array_column($engines, 'ENGINE'));
    }

    public function test_test_account_cannot_access_development_database(): void
    {
        $pdo = DB::connection()->getPdo();
        try {
            $pdo->exec('USE `tawzee_dev`');
            $this->fail('Test role unexpectedly accessed development.');
        } catch (\PDOException $exception) {
            $this->assertSame(1044, $exception->errorInfo[1]);
        }
    }

    public function test_transaction_really_rolls_back(): void
    {
        DB::beginTransaction();
        $key = 'phase-zero-'.bin2hex(random_bytes(12));
        try {
            DB::table('cache')->insert(['key' => $key, 'value' => 'probe', 'expiration' => 1]);
            $this->assertTrue(DB::table('cache')->where('key', $key)->exists());
        } finally {
            DB::rollBack();
        }
        $this->assertFalse(DB::table('cache')->where('key', $key)->exists());
    }
}
