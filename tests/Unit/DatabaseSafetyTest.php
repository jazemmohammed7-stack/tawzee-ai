<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\DatabaseSafety;

class DatabaseSafetyTest extends TestCase
{
    public function test_actual_development_url_cannot_be_hidden_by_other_database_fields(): void
    {
        $identity = DatabaseSafety::developmentIdentity("DB_DATABASE=other\nDB_USERNAME=other\nDB_URL=mysql://developer:unused@localhost/tawzee_test\n");
        $this->expectException(RuntimeException::class);
        DatabaseSafety::verify('testing', 'mysql_testing', ['driver' => 'mysql', 'database' => 'tawzee_test', 'username' => 'tester'], $identity);
    }

    public function test_case_difference_cannot_disguise_the_development_database(): void
    {
        $this->expectException(RuntimeException::class);
        DatabaseSafety::verify('testing', 'mysql_testing', ['driver' => 'mysql', 'database' => 'tawzee_test', 'username' => 'tester'], ['database' => 'TAWZEE_TEST', 'username' => 'developer']);
    }

    public function test_network_driver_cannot_masquerade_as_in_memory_sqlite(): void
    {
        $this->expectException(RuntimeException::class);
        DatabaseSafety::verify('testing', 'sqlite', ['driver' => 'mysql', 'database' => ':memory:'], []);
    }

    #[DataProvider('unsafeMysqlConfigurations')]
    public function test_unsafe_mysql_connections_are_rejected(array $changes, string $environment = 'testing', string $connection = 'mysql_testing'): void
    {
        $this->expectException(RuntimeException::class);
        DatabaseSafety::verify($environment, $connection, array_replace([
            'driver' => 'mysql', 'database' => 'tawzee_test', 'username' => 'tawzee_test_app',
        ], $changes), ['database' => 'tawzee_dev', 'username' => 'tawzee_dev_app']);
    }

    public static function unsafeMysqlConfigurations(): array
    {
        return [
            'development database' => [['database' => 'tawzee_dev']],
            'development user' => [['username' => 'tawzee_dev_app']],
            'administrator' => [['username' => 'root']],
            'URL override' => [['url' => 'mysql://localhost/other']],
            'read replica override' => [['read' => ['host' => 'other']]],
            'socket override' => [['unix_socket' => '/tmp/mysql.sock']],
            'driver mismatch' => [['driver' => 'pgsql']],
            'production' => [[], 'production'],
            'development connection name' => [[], 'testing', 'mysql'],
        ];
    }

    public function test_isolated_mysql_configuration_is_accepted(): void
    {
        DatabaseSafety::verify('testing', 'mysql_testing', ['driver' => 'mysql', 'database' => 'tawzee_test', 'username' => 'tester'], ['database' => 'tawzee_dev', 'username' => 'developer']);
        $this->addToAssertionCount(1);
    }

    public function test_development_database_is_rejected_even_with_a_test_connection_name(): void
    {
        $this->expectException(RuntimeException::class);
        DatabaseSafety::verify('testing', 'pgsql_testing', ['driver' => 'pgsql', 'database' => 'tawzee_dev', 'username' => 'tester'], []);
    }

    public function test_reused_development_role_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        DatabaseSafety::verify('testing', 'pgsql_testing', ['driver' => 'pgsql', 'database' => 'tawzee_test', 'username' => 'shared'], ['username' => 'shared']);
    }

    public function test_disk_sqlite_database_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        DatabaseSafety::verify('testing', 'sqlite', ['database' => 'database/database.sqlite'], []);
    }

    public function test_separate_postgresql_database_and_role_are_accepted(): void
    {
        DatabaseSafety::verify('testing', 'pgsql_testing', ['driver' => 'pgsql', 'database' => 'tawzee_test', 'username' => 'tester'], ['database' => 'tawzee_dev', 'username' => 'developer']);
        $this->addToAssertionCount(1);
    }
}
