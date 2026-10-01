<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * The base TestCase must refuse to boot against a non-testing database,
 * because RefreshDatabase would drop every table in it.
 */
class TestDatabaseGuardTest extends TestCase
{
    public function test_the_suite_is_running_against_a_testing_database(): void
    {
        $this->assertStringEndsWith('_testing', config('database.connections.'.config('database.default').'.database'));
        $this->assertSame('it_support_servicedesk_testing', DB::connection()->getDatabaseName());
    }

    public function test_the_guard_rejects_the_development_database(): void
    {
        config(['database.connections.mysql.database' => 'it_support_servicedesk']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("must end with '_testing'");

        $this->callSetUpTraits();
    }

    public function test_the_guard_rejects_a_production_like_database_name(): void
    {
        config(['database.connections.mysql.database' => 'servicedesk_production']);

        $this->expectException(RuntimeException::class);

        $this->callSetUpTraits();
    }

    private function callSetUpTraits(): void
    {
        $method = new \ReflectionMethod($this, 'setUpTraits');
        $method->invoke($this);
    }
}
