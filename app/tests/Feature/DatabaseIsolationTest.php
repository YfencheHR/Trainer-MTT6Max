<?php

namespace Tests\Feature;

use Tests\TestCase;

class DatabaseIsolationTest extends TestCase
{
    public function test_tests_use_the_separate_postgres_test_database(): void
    {
        $this->assertSame('pgsql', config('database.default'));
        $this->assertSame('trainer_mtt_test', config('database.connections.pgsql.database'));
    }
}
