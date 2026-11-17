<?php

namespace App\Console\Commands;

use App\Models\Bot;
use App\Models\BotHudStat;
use App\Services\Import\StatBounds;
use Illuminate\Console\Command;

/**
 * Aplica a las estadísticas YA importadas de los bots Pool el mismo margen de protección que la importación
 * (App\Services\Import\StatBounds), para no tener que repetir una importación larga. Es idempotente.
 */
class BotsProtectStatsCommand extends Command
{
    protected $signature = 'bots:protect-stats {--dry : mostrar qué cambiaría sin guardar}';

    protected $description = 'Acota las estadísticas extremas (ej. fold al 3-bet 100 %) de los bots Pool ya importados';

    public function handle(): int
    {
        $columns = array_unique(array_merge(array_keys(StatBounds::BOUNDS), ['reraise_vs_3bet']));
        $poolIds = Bot::all()->filter(fn(Bot $b) => $b->isPool())->pluck('id');

        $changedRows = 0;
        $byStat = [];
        foreach (BotHudStat::whereIn('bot_id', $poolIds)->get() as $row) {
            $before = [];
            foreach ($columns as $column) {
                if ($row->{$column} !== null) {
                    $before[$column] = $row->{$column};
                }
            }
            $after = StatBounds::apply($before);
            $diff = array_filter($after, fn($v, $k) => $v != $before[$k], ARRAY_FILTER_USE_BOTH);
            if (! $diff) {
                continue;
            }
            foreach ($diff as $column => $value) {
                $byStat[$column] = ($byStat[$column] ?? 0) + 1;
            }
            $changedRows++;
            if (! $this->option('dry')) {
                $row->fill($diff)->save();
            }
        }

        ksort($byStat);
        $this->info(($this->option('dry') ? '[dry] ' : '') . "Filas modificadas: $changedRows de " . BotHudStat::whereIn('bot_id', $poolIds)->count());
        foreach ($byStat as $column => $n) {
            $this->line("  $column: $n");
        }
        if (! $this->option('dry')) {
            $this->line('Ahora regenera los rangos: php artisan bots:audit-ranges');
        }

        return self::SUCCESS;
    }
}
