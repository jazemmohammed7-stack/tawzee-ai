<?php

declare(strict_types=1);

namespace Tests;

use Dotenv\Dotenv;
use Illuminate\Support\ConfigurationUrlParser;
use RuntimeException;

final class DatabaseSafety
{
    public static function verify(string $environment, string $connection, array $testing, array $development): void
    {
        if ($environment !== 'testing') {
            throw new RuntimeException('Tests require APP_ENV=testing.');
        }

        if ($connection === 'sqlite' && ($testing['driver'] ?? '') === 'sqlite'
            && ($testing['database'] ?? '') === ':memory:' && empty($testing['url'])
            && empty($testing['read']) && empty($testing['write'])) {
            return;
        }

        $drivers = ['pgsql_testing' => 'pgsql', 'mysql_testing' => 'mysql'];
        if (! isset($drivers[$connection]) || ($testing['driver'] ?? '') !== $drivers[$connection]
            || ! empty($testing['url'])
            || ! empty($testing['read']) || ! empty($testing['write']) || ! empty($testing['unix_socket'])
            || ! str_ends_with((string) ($testing['database'] ?? ''), '_test')
            || empty($testing['username'])
            || in_array(strtolower($testing['username']), ['root', 'postgres'], true)
            || strtolower((string) ($testing['database'] ?? '')) === strtolower((string) ($development['database'] ?? ''))
            || ($testing['username'] ?? '') === ($development['username'] ?? '')) {
            throw new RuntimeException('Use a dedicated test connection, database ending in _test, and non-admin test role. Development credentials, DB_URL and alternate endpoints are forbidden.');
        }
    }

    public static function developmentIdentity(string $contents): array
    {
        try {
            $values = Dotenv::parse($contents);
            $resolved = (new ConfigurationUrlParser)->parseConfiguration([
                'url' => $values['DB_URL'] ?? '',
                'database' => $values['DB_DATABASE'] ?? '',
                'username' => $values['DB_USERNAME'] ?? '',
            ]);

            return array_intersect_key($resolved, array_flip(['database', 'username']));
        } catch (\Throwable $exception) {
            throw new RuntimeException('Cannot safely resolve the local development identity; inspect configuration privately.');
        }
    }

    public static function verifyLocalDevelopment(string $environment, string $connection, array $testing, string $root): void
    {
        // .env.testing must not hide a changed development identity in the real .env.
        if (is_file($root.'/.env')) {
            self::verify($environment, $connection, $testing, self::developmentIdentity(file_get_contents($root.'/.env')));
        }
    }

    public static function verifyMysqlIdentity(\PDO $pdo, array $testing): void
    {
        $identity = $pdo->query('SELECT DATABASE(), CURRENT_USER()')->fetch(\PDO::FETCH_NUM);
        if ($identity[0] !== $testing['database'] || explode('@', $identity[1])[0] !== $testing['username']) {
            throw new RuntimeException('Actual test database identity differs from configuration.');
        }

        $escapedDatabase = str_replace('_', '\\_', $testing['database']);
        foreach ($pdo->query('SHOW GRANTS')->fetchAll(\PDO::FETCH_COLUMN) as $grant) {
            if (str_starts_with($grant, 'GRANT USAGE ON *.* TO ')) {
                continue;
            }
            if (! str_contains($grant, " ON `{$escapedDatabase}`.* TO ") || str_contains($grant, 'WITH GRANT OPTION')) {
                throw new RuntimeException('Test role must have privileges on its exact test database only.');
            }
        }
    }
}
