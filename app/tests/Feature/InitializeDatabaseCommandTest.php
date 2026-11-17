<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\Card;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InitializeDatabaseCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_base_data_on_an_empty_database(): void
    {
        $this->assertSame(0, Card::count());

        $this->artisan('db:initialize')->assertSuccessful();

        $this->assertSame(52, Card::count());
        $this->assertGreaterThan(0, Bot::count());
    }

    public function test_running_it_again_does_not_duplicate_anything(): void
    {
        $this->artisan('db:initialize')->assertSuccessful();
        $bots = Bot::count();
        $cards = Card::count();

        // Arranques posteriores (cada vez que se reinicia el contenedor)
        $this->artisan('db:initialize')->assertSuccessful();
        $this->artisan('db:initialize')->assertSuccessful();

        $this->assertSame($cards, Card::count());
        $this->assertSame($bots, Bot::count());
    }

    public function test_it_does_not_reseed_when_the_user_changed_their_bots(): void
    {
        $this->artisan('db:initialize')->assertSuccessful();
        $bot = Bot::first();
        $bot->update(['nombre' => 'Mi bot personalizado']);
        Bot::where('id', '!=', $bot->id)->delete();

        $this->artisan('db:initialize')->assertSuccessful();

        $this->assertSame(1, Bot::count());
        $this->assertSame('Mi bot personalizado', Bot::first()->nombre);
    }
}
