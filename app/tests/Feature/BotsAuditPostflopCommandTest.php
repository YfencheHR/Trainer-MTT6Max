<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotsAuditPostflopCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_runs_without_decisions_and_says_so(): void
    {
        $this->artisan('bots:audit-postflop')
            ->expectsOutputToContain('No hay decisiones postflop de bots registradas.')
            ->assertSuccessful();
    }
}
