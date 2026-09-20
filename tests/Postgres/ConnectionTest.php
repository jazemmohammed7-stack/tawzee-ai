<?php

declare(strict_types=1);

namespace Tests\Postgres;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ConnectionTest extends TestCase
{
    public function test_postgresql_connection_and_existing_baseline_migrations(): void
    {
        $this->assertSame('pgsql_testing', config('database.default'));
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $identity = DB::selectOne('select current_database() as database, current_user as username');
        $this->assertSame(config('database.connections.pgsql_testing.database'), $identity->database);
        $this->assertSame(config('database.connections.pgsql_testing.username'), $identity->username);
        foreach (['migrations', 'users', 'cache', 'jobs'] as $table) {
            $this->assertTrue(Schema::hasTable($table), 'Run reviewed baseline migrations on the isolated test database first.');
        }
    }

    public function test_postgresql_transaction_rolls_back_temporary_data(): void
    {
        DB::beginTransaction();
        try {
            DB::statement('CREATE TEMP TABLE phase_zero_probe (amount numeric(15,3) NOT NULL CHECK (amount > 0)) ON COMMIT DROP');
            DB::insert('INSERT INTO phase_zero_probe (amount) VALUES (?)', ['1.125']);
            $this->assertSame('1.125', (string) DB::selectOne('SELECT amount FROM phase_zero_probe')->amount);
        } finally {
            DB::rollBack();
        }
        $this->assertNull(DB::selectOne("SELECT to_regclass('pg_temp.phase_zero_probe') AS name")->name);
    }
}
