<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use RobinsonRyan\HeyYou\HeyYouServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    protected function getPackageProviders($app): array
    {
        return [
            HeyYouServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }

    protected function setUpDatabase(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
