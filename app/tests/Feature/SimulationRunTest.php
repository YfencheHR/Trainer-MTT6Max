<?php

namespace Tests\Feature;

use App\Services\Tournament\SimulationLauncher;
use Tests\TestCase;

/** La simulación de torneos desde la página de importación: validación y una sola a la vez (sin lanzar procesos reales). */
class SimulationRunTest extends TestCase
{
    private function fakeLauncher(?string $running = null): object
    {
        $fake = new class($running) extends SimulationLauncher {
            public array $launched = [];

            public function __construct(private ?string $busy) {}

            public function running(): ?string
            {
                return $this->busy;
            }

            public function launch(string $id, int $players, string $speed): void
            {
                $this->launched[] = [$id, $players, $speed];
            }
        };
        $this->app->instance(SimulationLauncher::class, $fake);

        return $fake;
    }

    public function test_the_import_page_offers_the_simulation(): void
    {
        $this->get(route('import.index'))->assertOk()->assertSee(__('simulation.button'));
    }

    public function test_it_launches_a_simulation(): void
    {
        $fake = $this->fakeLauncher();

        $this->postJson(route('import.simulate'), ['players' => 600, 'speed' => 'tipica'])
            ->assertOk()->assertJson(['success' => true]);

        $this->assertCount(1, $fake->launched);
        $this->assertSame([600, 'tipica'], [$fake->launched[0][1], $fake->launched[0][2]]);
    }

    public function test_it_validates_the_options(): void
    {
        $fake = $this->fakeLauncher();

        $this->postJson(route('import.simulate'), ['players' => 5, 'speed' => 'tipica'])->assertStatus(422);
        $this->postJson(route('import.simulate'), ['players' => 601, 'speed' => 'tipica'])->assertStatus(422);
        $this->postJson(route('import.simulate'), ['players' => 100, 'speed' => 'turbo'])->assertStatus(422);
        $this->assertSame([], $fake->launched);
    }

    public function test_only_one_simulation_runs_at_a_time(): void
    {
        $fake = $this->fakeLauncher('11111111-1111-1111-1111-111111111111');

        $this->postJson(route('import.simulate'), ['players' => 60, 'speed' => 'rapida'])
            ->assertStatus(409)->assertJson(['success' => false, 'run' => '11111111-1111-1111-1111-111111111111']);

        $this->assertSame([], $fake->launched);
    }
}
