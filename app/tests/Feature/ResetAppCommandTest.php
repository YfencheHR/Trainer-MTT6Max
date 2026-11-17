<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\Card;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResetAppCommandTest extends TestCase
{
    use RefreshDatabase;

    private function populate(): void
    {
        $this->artisan('db:initialize')->assertSuccessful();
        User::factory()->create(['email' => 'otro@example.com']);
        Bot::create(['nombre' => 'Bot que borrará el reset', 'tipo' => 'pool', 'metadata' => ['source' => 'pt4_import']]);
        Tournament::create(['title' => 'Torneo a borrar', 'status' => 'pending', 'config' => ['players' => []]]);
    }

    public function test_force_wipes_everything_and_reloads_the_base_data(): void
    {
        $this->populate();
        $this->assertSame(1, Tournament::count());

        $this->artisan('app:reset --force')->assertSuccessful();

        $this->assertSame(0, Tournament::count(), 'Los torneos se borran');
        $this->assertNull(Bot::where('nombre', 'Bot que borrará el reset')->first(), 'Los bots creados se borran');
        $this->assertSame(1, User::count(), 'Solo queda el usuario de prueba de los datos base');
        $this->assertSame(52, Card::count(), 'Los datos base se vuelven a cargar');
        $this->assertSame(9, Bot::count(), 'Quedan los 9 bots primarios');
    }

    public function test_it_cancels_without_deleting_anything_when_the_user_says_no(): void
    {
        $this->populate();

        $this->artisan('app:reset')
            ->expectsConfirmation('¿Seguro que quieres borrarlo todo y empezar de cero?', 'no')
            ->assertFailed();

        $this->assertSame(1, Tournament::count());
        $this->assertNotNull(Bot::where('nombre', 'Bot que borrará el reset')->first());
    }

    public function test_it_shows_what_it_is_going_to_delete_before_asking(): void
    {
        $this->populate();

        $this->artisan('app:reset')
            ->expectsOutputToContain('BORRA TODOS los datos')
            ->expectsOutputToContain('Torneos: 1')
            ->expectsConfirmation('¿Seguro que quieres borrarlo todo y empezar de cero?', 'no')
            ->assertFailed();
    }
}
