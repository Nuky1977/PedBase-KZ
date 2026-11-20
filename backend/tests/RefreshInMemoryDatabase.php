<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;

trait RefreshInMemoryDatabase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $database = $this->app['db']->connection();

        if (! $this->app->environment('testing') || $database->getDriverName() !== 'sqlite' || $database->getDatabaseName() !== ':memory:') {
            throw new RuntimeException('Feature tests require the SQLite in-memory testing database.');
        }
    }

    protected function migrateDatabases(): void
    {
        $this->artisan('migrate', ['--database' => 'sqlite', '--no-interaction' => true])->assertSuccessful();
    }
}
