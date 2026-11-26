<?php

namespace App\Console\Commands;

use App\Models\Bot;
use App\Services\Bots\BetSizeMatrix;
use App\Services\Bots\BetTier;
use Illuminate\Console\Command;

/**
 * Muestra la matriz de apuestas de los perfiles importados: cuánto farol (aire) hay en su rango de apuesta por calle y, con un
 * perfil concreto, cómo reparte los tamaños con cada tipo de mano. Los datos salen de las apuestas con cartas conocidas de
 * PokerTracker, mezcladas con las de toda la población según cuántas tenga el perfil.
 */
class BotsBetMatrixCommand extends Command
{
    protected $signature = 'bots:bet-matrix {bot? : id de un perfil para ver el detalle}';

    protected $description = 'Muestra la matriz de apuestas (farol y tamaños por tipo de mano) de los perfiles importados';

    public function handle(BetSizeMatrix $matrix): int
    {
        if ($this->argument('bot')) {
            return $this->detail(Bot::findOrFail((int) $this->argument('bot')), $matrix);
        }

        $rows = [];
        foreach (Bot::all()->filter(fn (Bot $b) => $b->isPool())->sortBy('id') as $bot) {
            $m = $matrix->forBot($bot);
            if (! $m) {
                continue;
            }
            $rows[] = array_merge(
                [$bot->id, mb_substr($bot->nombre, 0, 28)],
                ...array_map(fn ($s) => [$m[$s]['n'], $m[$s]['bluff_share'].'%'], array_keys(BetTier::STREETS))
            );
        }
        if (! $rows) {
            $this->warn('No hay matriz de apuestas: importa un historial de PokerTracker.');

            return self::SUCCESS;
        }

        $pop = $matrix->population();
        $popRow = ['-', 'POBLACIÓN'];
        foreach (array_keys(BetTier::STREETS) as $street) {
            $total = array_sum(array_map('array_sum', $pop[$street] ?? []));
            $popRow[] = $total;
            $popRow[] = $total > 0 ? round(100 * array_sum($pop[$street]['air'] ?? []) / $total, 2).'%' : '-';
        }
        array_unshift($rows, $popRow);

        $this->table(['id', 'perfil', 'flop n', 'farol flop', 'turn n', 'farol turn', 'river n', 'farol river'], $rows);
        $this->line('Farol = parte de las apuestas hechas con aire (no mejora el tablero). n = apuestas observadas del perfil.');
        $this->line('Detalle de un perfil: php artisan bots:bet-matrix {id}');

        return self::SUCCESS;
    }

    private function detail(Bot $bot, BetSizeMatrix $matrix): int
    {
        $m = $matrix->forBot($bot);
        if (! $m) {
            $this->warn('Este perfil no tiene matriz de apuestas.');

            return self::SUCCESS;
        }

        $labels = ['25' => '<=25 %', '40' => '26-40 %', '60' => '41-60 %', '85' => '61-85 %', 'max' => '>85 %'];
        $this->info($bot->nombre);
        foreach ($m as $street => $data) {
            $this->newLine();
            $this->line("<comment>{$street}</comment>  ({$data['n']} apuestas observadas)");
            $rows = [];
            foreach (BetTier::TIERS as $tier) {
                $rows[] = array_merge([$tier, $data['share'][$tier].'%'], array_map(fn ($b) => $data['sizes'][$tier][$b].'%', array_keys(BetTier::BUCKETS)));
            }
            $this->table(array_merge(['mano', 'parte del rango'], array_values($labels)), $rows);
        }

        return self::SUCCESS;
    }
}
