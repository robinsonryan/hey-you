<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Tests;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;
use RobinsonRyan\HeyYou\HeyYouServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            HeyYouServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // The package schema relies on PostgreSQL's native uuidv7() as a column
        // default, so the suite runs against a real Postgres database (the DDEV
        // `db` service) rather than SQLite.
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'pgsql',
            'host' => env('HEYYOU_TEST_DB_HOST', 'db'),
            'port' => (int) env('HEYYOU_TEST_DB_PORT', 5432),
            'database' => env('HEYYOU_TEST_DB_DATABASE', 'testing'),
            'username' => env('HEYYOU_TEST_DB_USERNAME', 'db'),
            'password' => env('HEYYOU_TEST_DB_PASSWORD', 'db'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ]);

        // Register the fixture tables (users, companies) with the migrator so the
        // one-time RefreshDatabase migration run picks them up alongside the
        // package migrations that HeyYouServiceProvider already registers.
        // Registering the path — rather than calling loadMigrationsFrom() — keeps
        // Testbench from tearing the schema down and rebuilding it per test.
        $app->afterResolving('migrator', static function (Migrator $migrator): void {
            $migrator->path(__DIR__.'/Fixtures/database/migrations');
        });
    }
}
