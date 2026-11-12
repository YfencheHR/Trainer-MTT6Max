<?php

namespace Tests\Unit;

use App\Services\Tournament\BlindStructureService;
use Tests\TestCase;

class BlindStructureServiceTest extends TestCase
{
    public function test_typical_blinds_continue_growing_after_configured_levels(): void
    {
        $service = new BlindStructureService();

        $expectedBigBlinds = [10, 20, 40, 60, 80, 80, 120];

        foreach ($expectedBigBlinds as $index => $expectedBigBlind) {
            $blinds = $service->forRound(10, 'tipica', $index * 10 + 1);
            $this->assertSame($expectedBigBlind, $blinds['big_blind']);
        }

        $this->assertSame(60, $service->forRound(10, 'tipica', 61)['small_blind']);
    }

    public function test_speed_changes_the_blind_progression(): void
    {
        $service = new BlindStructureService();

        $this->assertSame(120, $service->forRound(10, 'rapida', 26)['big_blind']);
        $this->assertSame(200, $service->forRound(10, 'muy_rapida', 11)['big_blind']);
        $this->assertSame(400, $service->forRound(10, 'muy_rapida', 13)['big_blind']);
    }

    public function test_custom_hands_per_level_controls_level_changes(): void
    {
        $service = new BlindStructureService();

        $this->assertSame(1, $service->forRound(10, 'tipica', 3, 3)['level']);
        $this->assertSame(2, $service->forRound(10, 'tipica', 4, 3)['level']);
        $this->assertSame(3, $service->forRound(10, 'tipica', 7, 3)['level']);
    }

    public function test_ante_schedule_applies_by_level_and_repeats_the_last_value(): void
    {
        $service = new BlindStructureService();

        $this->assertSame(0, $service->forRound(10, 'tipica', 1, 1, [0, 5, 10])['ante']);
        $this->assertSame(5, $service->forRound(10, 'tipica', 2, 1, [0, 5, 10])['ante']);
        $this->assertSame(10, $service->forRound(10, 'tipica', 4, 1, [0, 5, 10])['ante']);
        $this->assertSame(0, $service->forRound(10, 'tipica', 1)['ante']);
    }
}
