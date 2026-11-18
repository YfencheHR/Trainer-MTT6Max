<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotsBetMatrixCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_says_so_when_there_is_no_matrix(): void
    {
        $this->artisan('bots:bet-matrix')
            ->expectsOutputToContain('No hay matriz de apuestas')
            ->assertSuccessful();
    }
}
