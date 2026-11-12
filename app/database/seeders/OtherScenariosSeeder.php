<?php

namespace Database\Seeders;

use App\Models\HandGroup;
use App\Models\HandGroupValue;
use Illuminate\Database\Seeder;

class OtherScenariosSeeder extends Seeder
{
    public function run(): void
    {
        // Crear datos para Ataque, Defensa, ISO/LIMP con diferentes stack depths
        $scenarios = ['Ataque', 'Defensa', 'ISO/LIMP'];
        $stackRanges = [
            ['min' => 20, 'max' => 50, 'label' => '20-50bb'],
            ['min' => 50, 'max' => 100, 'label' => '50-100bb'],
            ['min' => 100, 'max' => 255, 'label' => '100bb+'],
        ];

        $allHands = HandGroup::all();

        foreach ($scenarios as $scenario) {
            foreach ($stackRanges as $range) {
                foreach ($allHands as $hand) {
                    // Calcular EV basado en gto_ev con variación por rango
                    $baseEv = $hand->gto_ev;
                    $modifier = match ($range['label']) {
                        '20-50bb' => 0.8,  // 80% del base
                        '50-100bb' => 0.7, // 70% del base
                        '100bb+' => 0.6,   // 60% del base
                    };

                    $ev = $baseEv * $modifier;

                    $this->createIfMissing($hand, $scenario, [
                        'positions' => ['UTG', 'LJ', 'HJ', 'CO', 'BTN', 'SB', 'BB'],
                        'stack_depth_min' => $range['min'],
                        'stack_depth_max' => $range['max'],
                    ], round($ev, 2));
                }
            }
        }

        // También agregar Nash Call para los mismos stack depths que Nash Push
        $nashPushValues = HandGroupValue::where('scenario', 'Nash Push')->get();

        foreach ($nashPushValues as $pushValue) {
            $this->createIfMissing(
                $pushValue->handGroup,
                'Nash Call',
                [
                    'positions' => $pushValue->positions,
                    'stack_depth_min' => $pushValue->stack_depth_min,
                    'stack_depth_max' => $pushValue->stack_depth_max,
                ],
                (float) $pushValue->ev * 0.95,
            );
        }
    }

    private function createIfMissing(HandGroup $hand, string $scenario, array $config, float $ev): void
    {
        $existing = $hand->values()
            ->where('scenario', $scenario)
            ->where('stack_depth_min', $config['stack_depth_min'])
            ->where('stack_depth_max', $config['stack_depth_max'])
            ->get()
            ->contains(fn (HandGroupValue $value) => $value->positions === $config['positions']);

        if ($existing) {
            return;
        }

        HandGroupValue::create([
            'hand_group_id' => $hand->id,
            'scenario' => $scenario,
            'positions' => $config['positions'],
            'stack_depth_min' => $config['stack_depth_min'],
            'stack_depth_max' => $config['stack_depth_max'],
            'ev' => $ev,
        ]);
    }
}
