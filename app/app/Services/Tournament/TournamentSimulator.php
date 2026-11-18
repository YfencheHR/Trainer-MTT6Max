<?php

namespace App\Services\Tournament;

use App\Models\Table;
use App\Models\Tournament;
use App\Models\TournamentEvent;

/**
 * Juega un torneo completo SIN héroe y sin interfaz, con el mismo motor de mesas, bots, ciegas, eliminaciones y nivelación
 * que el simulador normal (TournamentRoundService / HandPlayService). Todo queda en el historial como en un torneo jugado,
 * así que se puede auditar igual (bots:audit-postflop).
 *
 * En cada ronda todas las mesas activas juegan una mano; después se nivelan las mesas, se registran las subidas de nivel y
 * el torneo termina cuando queda un solo jugador.
 */
class TournamentSimulator
{
    public function __construct(
        private TournamentRoundService $rounds,
        private TableBalanceService $balance,
    ) {
    }

    /**
     * @param callable|null $progress fn(int $round, int $alive, int $tables): void tras cada ronda
     * @return array{rounds:int, finished:bool, winner:?string, players:int}
     */
    public function run(Tournament $t, ?callable $progress = null, int $maxRounds = 3000): array
    {
        for ($round = 1; $round <= $maxRounds; $round++) {
            foreach (Table::where('tournament_id', $t->id)->where('is_active', true)->orderBy('table_number')->get() as $table) {
                $this->rounds->resolveTable($t, $table, $round);
            }

            $alive = $t->players()->where('is_eliminated', false)->where('current_stack', '>', 0)->count();

            if ($alive > 1) {
                $this->balanceTables($t, $round);
                $this->recordLevelUp($t, $round);
            }
            $this->clearPendingEvents($t);

            if ($progress) {
                $progress($round, $alive, Table::where('tournament_id', $t->id)->where('is_active', true)->count());
            }

            if ($alive <= 1) {
                return $this->finish($t, $round);
            }
        }

        return ['rounds' => $maxRounds, 'finished' => false, 'winner' => null, 'players' => $t->players()->count()];
    }

    private function balanceTables(Tournament $t, int $round): void
    {
        if (Table::where('tournament_id', $t->id)->where('is_active', true)->count() <= 1) {
            return;
        }
        foreach ($this->balance->rebalance($t) as $ev) {
            TournamentEvent::record($t, $ev['type'], $ev['text'], $round, $ev['payload'], $ev['table_id'], $ev['player_id']);
        }
    }

    private function recordLevelUp(Tournament $t, int $round): void
    {
        $prev = $this->rounds->blindsForRound($t, $round);
        $next = $this->rounds->blindsForRound($t, $round + 1);
        if ($next['level'] > $prev['level']) {
            TournamentEvent::record($t, 'level_up', "Nivel {$next['level']}: ciegas {$next['small_blind']}/{$next['big_blind']}", $round + 1, $next);
        }
    }

    /** processEliminations deja textos pendientes para el log del héroe; sin héroe nadie los lee y solo harían crecer la configuración. */
    private function clearPendingEvents(Tournament $t): void
    {
        $t->refresh();
        $cfg = $t->config;
        if (isset($cfg['pending_events'])) {
            unset($cfg['pending_events']);
            $t->config = $cfg;
            $t->save();
        }
    }

    private function finish(Tournament $t, int $round): array
    {
        $t->players()->where('is_eliminated', false)->update(['placement' => 1]);
        $t->update(['status' => 'completed', 'ended_at' => now()]);
        $winner = $t->players()->where('placement', 1)->first();
        TournamentEvent::record($t, 'tournament_finished', 'Torneo terminado: ' . ($winner->name ?? '?') . ' gana', $round, ['winner_id' => $winner->id ?? null]);

        return ['rounds' => $round, 'finished' => true, 'winner' => $winner->name ?? null, 'players' => $t->players()->count()];
    }
}
