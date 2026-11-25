<?php

namespace Tests;

use App\Services\Bots\Brain\BoardTextureStats;
use App\Services\Bots\Brain\PostflopPotStats;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Cachés estáticas de lo importado de PokerTracker: sobreviven al rollback de la base de datos, así que un test no puede heredar las de otro
        BoardTextureStats::flush();
        PostflopPotStats::flush();
        BoardTextureStats::$disabled = false;
    }

    protected function tearDown(): void
    {
        BoardTextureStats::flush();
        PostflopPotStats::flush();

        parent::tearDown();
    }
}
