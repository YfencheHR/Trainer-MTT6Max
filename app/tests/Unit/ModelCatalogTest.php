<?php

namespace Tests\Unit;

use App\Services\Ai\ModelCatalog;
use Tests\TestCase;

class ModelCatalogTest extends TestCase
{
    private function pc(float $ram, float $disk = 200, array $gpus = []): array
    {
        return ['ram_gb' => $ram, 'disk_free_gb' => $disk, 'cpu_threads' => 8, 'gpus' => $gpus];
    }

    public function test_the_shipped_catalog_is_consistent(): void
    {
        $catalog = new ModelCatalog;

        $this->assertNotEmpty($catalog->models());
        $this->assertSame('qwen3:8b', $catalog->minimum()['id']);
        foreach ($catalog->models() as $m) {
            $this->assertNotEmpty($m['license']);
            $this->assertGreaterThan(0, $m['disk_gb']);
            $this->assertGreaterThanOrEqual($m['disk_gb'], $m['ram_gb'], 'en procesador el modelo entero cabe en memoria');
        }
    }

    public function test_a_small_pc_does_not_meet_the_minimum_requirements(): void
    {
        $catalog = new ModelCatalog;

        $this->assertFalse($catalog->meetsMinimum($this->pc(8)));
        $this->assertSame([], $catalog->usable($this->pc(8)));
    }

    public function test_memory_decides_which_models_run_on_the_processor(): void
    {
        $usable = (new ModelCatalog)->usable($this->pc(16));

        $this->assertSame(['phi4', 'qwen3:14b', 'qwen3:8b'], array_column($usable, 'id'));
        $this->assertSame(['cpu'], array_values(array_unique(array_column($usable, 'runs_on'))));
    }

    public function test_an_nvidia_card_runs_what_fits_in_its_memory_and_the_rest_falls_back_to_processor(): void
    {
        $usable = (new ModelCatalog)->usable($this->pc(32, 200, [['name' => 'RTX 3060', 'vram_gb' => 12]]));
        $by = array_column($usable, 'runs_on', 'id');

        $this->assertSame('gpu', $by['qwen3:14b']);
        $this->assertSame('gpu', $by['qwen3:8b']);
        $this->assertSame('cpu', $by['qwen3:32b'], '32B no cabe en 12 GB de tarjeta pero sí en 32 GB de memoria');
        $this->assertSame('qwen3:32b', $usable[0]['id'], 'primero el más capaz');
    }

    public function test_not_enough_disk_blocks_a_model_even_with_memory(): void
    {
        $catalog = new ModelCatalog;

        $this->assertSame('disk', $catalog->fit($catalog->minimum(), $this->pc(64, 3))['reason']);
        $this->assertFalse($catalog->meetsMinimum($this->pc(64, 3)));
    }
}
