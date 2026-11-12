<?php

namespace Database\Seeders;

use App\Models\HandGroup;
use App\Models\HandGroupValue;
use Illuminate\Database\Seeder;

class NashHandGroupValueSeeder extends Seeder
{
    public function run(): void
    {
        $nashValues = $this->getNashValues();

        foreach ($nashValues as $handName => $configs) {
            $hand = HandGroup::where('group_name', $handName)->first();

            if (!$hand) continue;

            foreach ($configs as $config) {
                $this->createIfMissing($hand, 'Nash Push', $config, $config['ev']);
                $this->createIfMissing($hand, 'Nash Call', $config, round($config['ev'] * 0.95, 2));
            }
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

    private function getNashValues()
    {
        return [
            // Pairs con valores diferentes por posición
            'AA' => [
                ['positions' => ['UTG'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 82.0],
                ['positions' => ['LJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 83.5],
                ['positions' => ['HJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 85.0],
                ['positions' => ['CO'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 86.5],
                ['positions' => ['BTN'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 88.0],
                ['positions' => ['SB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 89.5],
                ['positions' => ['BB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 90.0],
            ],
            'KK' => [
                ['positions' => ['UTG'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 75.0],
                ['positions' => ['LJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 76.5],
                ['positions' => ['HJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 78.0],
                ['positions' => ['CO'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 79.5],
                ['positions' => ['BTN'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 81.0],
                ['positions' => ['SB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 82.5],
                ['positions' => ['BB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 83.0],
            ],
            'QQ' => [
                ['positions' => ['UTG'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 68.0],
                ['positions' => ['LJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 69.5],
                ['positions' => ['HJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 71.0],
                ['positions' => ['CO'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 72.5],
                ['positions' => ['BTN'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 74.0],
                ['positions' => ['SB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 75.5],
                ['positions' => ['BB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 76.0],
            ],
            'JJ' => [
                ['positions' => ['UTG'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 61.0],
                ['positions' => ['LJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 62.5],
                ['positions' => ['HJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 64.0],
                ['positions' => ['CO'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 65.5],
                ['positions' => ['BTN'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 67.0],
                ['positions' => ['SB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 68.5],
                ['positions' => ['BB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 69.0],
            ],
            'TT' => [
                ['positions' => ['UTG'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 54.0],
                ['positions' => ['LJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 55.5],
                ['positions' => ['HJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 57.0],
                ['positions' => ['CO'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 58.5],
                ['positions' => ['BTN'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 60.0],
                ['positions' => ['SB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 61.5],
                ['positions' => ['BB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 62.0],
            ],
            '99' => [
                ['positions' => ['UTG'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 47.0],
                ['positions' => ['LJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 48.5],
                ['positions' => ['HJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 50.0],
                ['positions' => ['CO'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 51.5],
                ['positions' => ['BTN'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 53.0],
                ['positions' => ['SB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 54.5],
                ['positions' => ['BB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 55.0],
            ],
            '88' => [
                ['positions' => ['UTG'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 40.0],
                ['positions' => ['LJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 41.5],
                ['positions' => ['HJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 43.0],
                ['positions' => ['CO'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 44.5],
                ['positions' => ['BTN'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 46.0],
                ['positions' => ['SB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 47.5],
                ['positions' => ['BB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 48.0],
            ],
            '77' => [
                ['positions' => ['UTG'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 33.0],
                ['positions' => ['LJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 34.5],
                ['positions' => ['HJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 36.0],
                ['positions' => ['CO'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 37.5],
                ['positions' => ['BTN'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 39.0],
                ['positions' => ['SB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 40.5],
                ['positions' => ['BB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 41.0],
            ],
            '66' => [
                ['positions' => ['UTG'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 26.0],
                ['positions' => ['LJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 27.5],
                ['positions' => ['HJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 29.0],
                ['positions' => ['CO'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 30.5],
                ['positions' => ['BTN'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 32.0],
                ['positions' => ['SB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 33.5],
                ['positions' => ['BB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 34.0],
            ],
            '55' => [
                ['positions' => ['UTG'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 19.0],
                ['positions' => ['LJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 20.5],
                ['positions' => ['HJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 22.0],
                ['positions' => ['CO'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 23.5],
                ['positions' => ['BTN'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 25.0],
                ['positions' => ['SB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 26.5],
                ['positions' => ['BB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 27.0],
            ],
            '44' => [
                ['positions' => ['UTG'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 12.0],
                ['positions' => ['LJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 13.5],
                ['positions' => ['HJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 15.0],
                ['positions' => ['CO'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 16.5],
                ['positions' => ['BTN'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 18.0],
                ['positions' => ['SB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 19.5],
                ['positions' => ['BB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 20.0],
            ],
            '33' => [
                ['positions' => ['UTG'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 5.0],
                ['positions' => ['LJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 6.5],
                ['positions' => ['HJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 8.0],
                ['positions' => ['CO'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 9.5],
                ['positions' => ['BTN'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 11.0],
                ['positions' => ['SB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 12.5],
                ['positions' => ['BB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 13.0],
            ],
            '22' => [
                ['positions' => ['UTG'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 2.0],
                ['positions' => ['LJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 3.5],
                ['positions' => ['HJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 5.0],
                ['positions' => ['CO'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 6.5],
                ['positions' => ['BTN'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 8.0],
                ['positions' => ['SB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 9.5],
                ['positions' => ['BB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 10.0],
            ],

            // Suited High Cards
            'AKs' => [
                ['positions' => ['UTG'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 72.0],
                ['positions' => ['LJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 74.0],
                ['positions' => ['HJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 76.0],
                ['positions' => ['CO'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 78.0],
                ['positions' => ['BTN'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 80.0],
                ['positions' => ['SB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 82.0],
                ['positions' => ['BB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 83.0],
            ],
            'AQs' => [
                ['positions' => ['UTG'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 65.0],
                ['positions' => ['LJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 67.0],
                ['positions' => ['HJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 69.0],
                ['positions' => ['CO'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 71.0],
                ['positions' => ['BTN'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 73.0],
                ['positions' => ['SB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 75.0],
                ['positions' => ['BB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 76.0],
            ],
            'AJs' => [
                ['positions' => ['UTG'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 58.0],
                ['positions' => ['LJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 60.0],
                ['positions' => ['HJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 62.0],
                ['positions' => ['CO'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 64.0],
                ['positions' => ['BTN'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 66.0],
                ['positions' => ['SB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 68.0],
                ['positions' => ['BB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 69.0],
            ],
            'KQs' => [
                ['positions' => ['UTG'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 54.0],
                ['positions' => ['LJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 56.0],
                ['positions' => ['HJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 58.0],
                ['positions' => ['CO'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 60.0],
                ['positions' => ['BTN'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 62.0],
                ['positions' => ['SB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 64.0],
                ['positions' => ['BB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 65.0],
            ],
            'KJs' => [
                ['positions' => ['UTG'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 48.0],
                ['positions' => ['LJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 50.0],
                ['positions' => ['HJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 52.0],
                ['positions' => ['CO'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 54.0],
                ['positions' => ['BTN'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 56.0],
                ['positions' => ['SB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 58.0],
                ['positions' => ['BB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 59.0],
            ],
            'QJs' => [
                ['positions' => ['UTG'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 42.0],
                ['positions' => ['LJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 44.0],
                ['positions' => ['HJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 46.0],
                ['positions' => ['CO'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 48.0],
                ['positions' => ['BTN'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 50.0],
                ['positions' => ['SB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 52.0],
                ['positions' => ['BB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 53.0],
            ],
            'JTs' => [
                ['positions' => ['UTG'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 36.0],
                ['positions' => ['LJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 38.0],
                ['positions' => ['HJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 40.0],
                ['positions' => ['CO'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 42.0],
                ['positions' => ['BTN'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 44.0],
                ['positions' => ['SB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 46.0],
                ['positions' => ['BB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 47.0],
            ],

            // Offsuit High Cards
            'AKo' => [
                ['positions' => ['UTG'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 54.0],
                ['positions' => ['LJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 56.0],
                ['positions' => ['HJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 58.0],
                ['positions' => ['CO'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 60.0],
                ['positions' => ['BTN'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 62.0],
                ['positions' => ['SB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 64.0],
                ['positions' => ['BB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 65.0],
            ],
            'AQo' => [
                ['positions' => ['UTG'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 44.0],
                ['positions' => ['LJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 46.0],
                ['positions' => ['HJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 48.0],
                ['positions' => ['CO'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 50.0],
                ['positions' => ['BTN'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 52.0],
                ['positions' => ['SB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 54.0],
                ['positions' => ['BB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 55.0],
            ],
            'AJo' => [
                ['positions' => ['UTG'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 34.0],
                ['positions' => ['LJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 36.0],
                ['positions' => ['HJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 38.0],
                ['positions' => ['CO'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 40.0],
                ['positions' => ['BTN'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 42.0],
                ['positions' => ['SB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 44.0],
                ['positions' => ['BB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 45.0],
            ],
            'KQo' => [
                ['positions' => ['UTG'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 30.0],
                ['positions' => ['LJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 32.0],
                ['positions' => ['HJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 34.0],
                ['positions' => ['CO'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 36.0],
                ['positions' => ['BTN'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 38.0],
                ['positions' => ['SB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 40.0],
                ['positions' => ['BB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 41.0],
            ],
            'KJo' => [
                ['positions' => ['UTG'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 20.0],
                ['positions' => ['LJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 22.0],
                ['positions' => ['HJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 24.0],
                ['positions' => ['CO'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 26.0],
                ['positions' => ['BTN'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 28.0],
                ['positions' => ['SB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 30.0],
                ['positions' => ['BB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 31.0],
            ],
            'QJo' => [
                ['positions' => ['UTG'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 10.0],
                ['positions' => ['LJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 12.0],
                ['positions' => ['HJ'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 14.0],
                ['positions' => ['CO'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 16.0],
                ['positions' => ['BTN'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 18.0],
                ['positions' => ['SB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 20.0],
                ['positions' => ['BB'], 'stack_depth_min' => 0, 'stack_depth_max' => 20, 'ev' => 21.0],
            ],
        ];
    }
}
