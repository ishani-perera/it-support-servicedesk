<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Safety net: RefreshDatabase runs migrate:fresh, which DROPS every table.
     * Refuse to run unless the configured database is clearly a testing database.
     */
    protected function setUpTraits()
    {
        $connection = config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if (! str_ends_with($database, '_testing')) {
            throw new RuntimeException(
                "Refusing to run tests against database [{$database}]: its name must end with '_testing'."
            );
        }

        return parent::setUpTraits();
    }
}
