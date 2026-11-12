<?php

namespace App\Console\Commands;

use App\Models\Tournament;
use App\Models\TournamentHand;
use App\Services\Ev\DecisionEvAnalyzer;
use Illuminate\Console\Command;

/**
 * Calcula (o recalcula) el EV de las manos de un torneo a partir de su historial.
 *   php artisan ev:analyze 19            → solo manos sin analizar
 *   php artisan ev:analyze 19 --force    → recalcula todas
 */
class AnalyzeEvCommand extends Command
{
    protected $signature = 'ev:analyze {tournament : id del torneo} {--force : recalcular también las ya analizadas}';

    protected $description = 'Analiza el EV de las manos de un torneo (equity, EV por decisión y all-in ajustado)';

    public function handle(DecisionEvAnalyzer $analyzer): int
    {
        $tournament = Tournament::find($this->argument('tournament'));
        if (!$tournament) {
            $this->error('Torneo no encontrado');
            return self::FAILURE;
        }

        $q = TournamentHand::where('tournament_id', $tournament->id)->where('status', 'completed')->whereHas('handActions');
        if (!$this->option('force')) {
            $q->whereNull('ev_analyzed_at');
        }

        $n = 0;
        $q->orderBy('id')->each(function (TournamentHand $hand) use ($analyzer, &$n) {
            $analyzer->analyze($hand);
            $hand->save();
            $n++;
        });

        $this->info("{$n} manos analizadas");

        return self::SUCCESS;
    }
}
