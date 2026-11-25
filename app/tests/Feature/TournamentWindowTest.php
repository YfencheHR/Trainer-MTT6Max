<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Services\Tournament\TournamentWindowLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Un torneo solo se puede jugar desde una ventana a la vez. */
class TournamentWindowTest extends TestCase
{
    use RefreshDatabase;

    private function tournament(): Tournament
    {
        return Tournament::create(['title' => 'MTT-window', 'status' => 'in_progress', 'config' => ['players' => []]]);
    }

    private function claim(Tournament $t, string $token, bool $take = false)
    {
        return $this->postJson(route('mtt-simulator.window.claim', $t), ['token' => $token, 'take' => $take]);
    }

    public function test_the_first_window_gets_the_tournament_and_a_second_one_does_not(): void
    {
        $t = $this->tournament();

        $this->claim($t, 'ventana-a')->assertOk()->assertJsonPath('data.owner', true);
        $this->claim($t, 'ventana-b')->assertOk()->assertJsonPath('data.owner', false);
    }

    public function test_the_active_window_renews_its_turn(): void
    {
        $t = $this->tournament();
        $this->claim($t, 'ventana-a');

        $this->claim($t, 'ventana-a')->assertJsonPath('data.owner', true)->assertJsonPath('data.took', false);
    }

    public function test_another_window_can_take_over_and_the_first_one_finds_out_on_its_next_renewal(): void
    {
        $t = $this->tournament();
        $this->claim($t, 'ventana-a');

        $this->claim($t, 'ventana-b', true)->assertJsonPath('data.owner', true)->assertJsonPath('data.took', true);
        $this->claim($t, 'ventana-a')->assertJsonPath('data.owner', false);
    }

    public function test_closing_the_active_window_frees_the_tournament_but_closing_another_one_does_not(): void
    {
        $t = $this->tournament();
        $this->claim($t, 'ventana-a');

        $this->postJson(route('mtt-simulator.window.release', $t), ['token' => 'ventana-b'])->assertOk();
        $this->claim($t, 'ventana-b')->assertJsonPath('data.owner', false);

        $this->postJson(route('mtt-simulator.window.release', $t), ['token' => 'ventana-a'])->assertOk();
        $this->claim($t, 'ventana-b')->assertJsonPath('data.owner', true);
    }

    public function test_the_permission_expires_by_itself_when_a_window_disappears(): void
    {
        $t = $this->tournament();
        $this->claim($t, 'ventana-a');

        $this->travel(TournamentWindowLock::TTL + 1)->seconds();

        $this->claim($t, 'ventana-b')->assertJsonPath('data.owner', true)->assertJsonPath('data.took', false);
    }

    public function test_the_server_rejects_actions_from_a_window_that_is_not_the_active_one(): void
    {
        $t = $this->tournament();
        $table = $t->tables()->create(['table_number' => 1, 'is_active' => true]);
        $this->claim($t, 'ventana-a');
        $url = route('mtt-simulator.table.resolve', [$t, $table]);

        $this->withHeader('X-Tournament-Window', 'ventana-b')->postJson($url, ['round' => 1])
            ->assertStatus(409)->assertJsonPath('code', 'window_taken');
        $this->postJson($url, ['round' => 1])->assertStatus(409);   // sin identificar la ventana tampoco
    }

    public function test_the_active_window_and_a_tournament_nobody_has_open_are_not_blocked_by_the_lock(): void
    {
        $t = $this->tournament();
        $table = $t->tables()->create(['table_number' => 1, 'is_active' => true]);
        $url = route('mtt-simulator.table.resolve', [$t, $table]);

        $this->assertNotSame(409, $this->postJson($url, ['round' => 1])->getStatusCode());   // nadie lo tiene abierto

        $this->claim($t, 'ventana-a');
        $this->assertNotSame(409, $this->withHeader('X-Tournament-Window', 'ventana-a')->postJson($url, ['round' => 1])->getStatusCode());
    }
}
