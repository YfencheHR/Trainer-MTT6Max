<?php

namespace App\Console\Commands;

use App\Services\Equity\HeuristicPreflopStrategyGenerator;
use Illuminate\Console\Command;

class GenerateHeuristicPreflopStrategies extends Command
{
    protected $signature = 'hand-matrix:generate-preflop {--force : Recalcular y sobrescribir las estrategias existentes}';

    protected $description = 'Genera estrategias preflop heurísticas por mano, posición y profundidad';

    public function handle(HeuristicPreflopStrategyGenerator $generator): int
    {
        $this->info('Generando tablas preflop heurísticas mediante simulación de equity...');
        $results = $generator->generate((bool) $this->option('force'));

        $this->table(
            ['Celdas nuevas', 'Celdas recalculadas', 'Celdas manuales conservadas', 'Muestras Monte Carlo'],
            [[
                $results['created'],
                $results['updated'],
                $results['preserved'],
                $results['simulations'],
            ]],
        );
        $this->line('Las estimaciones son heurísticas chipEV; no son una solución GTO/Nash.');

        return self::SUCCESS;
    }
}
