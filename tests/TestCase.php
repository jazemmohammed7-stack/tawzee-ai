<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $connection = (string) $app['config']->get('database.default');

        DatabaseSafety::verify(
            $app->environment(),
            $connection,
            $app['config']->get('database.connections.'.$connection, []),
            $app['config']->get('database.connections.'.($connection === 'mysql_testing' ? 'mysql' : 'pgsql'), []),
        );

        DatabaseSafety::verifyLocalDevelopment(
            $app->environment(), $connection,
            $app['config']->get('database.connections.'.$connection, []), $app->basePath(),
        );

        if ($connection === 'mysql_testing') {
            try {
                DatabaseSafety::verifyMysqlIdentity(
                    $app['db']->connection()->getPdo(),
                    $app['config']->get('database.connections.mysql_testing'),
                );
            } catch (\PDOException $exception) {
                throw new \RuntimeException('Test database connection failed; check local credentials privately.');
            }
        }

        return $app;
    }
}
