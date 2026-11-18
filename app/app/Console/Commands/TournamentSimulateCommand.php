<?php

namespace App\Console\Commands;

use App\Models\Bot;
use App\Models\Tournament;
use App\Services\Tournament\TournamentFactory;
use App\Services\Tournament\TournamentSimulator;
use Illuminate\Console\Command;

/**
 * Simulador sin cabeza: crea y juega torneos completos SOLO de bots, con el mismo sistema que el simulador normal pero sin
 * interfaz, para generar muestra con la que auditar las decisiones (bots:audit-postflop).
 *
 * Los torneos simulados llevan config.simulated = true: no se mezclan con los jugados y se pueden borrar con --purge.
 */
class TournamentSimulateCommand extends Command
{
    protected $signature = 'tournament:simulate
        {--tournaments=1 : cuántos torneos simular}
        {--players=18 : jugadores por torneo}
        {--stack=10000 : fichas iniciales}
        {--bb=100 : ciega grande inicial}
        {--speed=tipica : tipica | rapida | muy_rapida}
        {--hands-per-level= : manos por nivel (por defecto, el de la velocidad)}
        {--bots= : perfiles y pesos "id:peso,id:peso" (por defecto, todos los perfiles importados con el mismo peso)}
        {--max-rounds=3000 : tope de rondas por torneo}
        {--purge : borra todos los torneos simulados y termina}';

    protected $description = 'Simula torneos completos solo de bots, sin interfaz, para auditar las decisiones';

    public function handle(TournamentFactory $factory, TournamentSimulator $simulator): int
    {
        if ($this->option('purge')) {
            return $this->purge();
        }

        $pool = Bot::all()->filter(fn(Bot $b) => $b->isPool())->keyBy('id');
        $percentages = $this->percentages($pool);
        if (! $percentages) {
            $this->error('No hay perfiles importados con los que jugar: importa primero un historial de PokerTracker.');

            return self::FAILURE;
        }

        $speed = (string) $this->option('speed');
        if (! config("blind-levels.{$speed}")) {
            $this->error('Velocidad desconocida: ' . $speed);

            return self::FAILURE;
        }
        $settings = [
            'starting_stack' => (int) $this->option('stack'),
            'big_blind' => (int) $this->option('bb'),
            'speed' => $speed,
            'hands_per_level' => (int) ($this->option('hands-per-level') ?: config("blind-levels.{$speed}.hands_per_level")),
            'ante_by_level' => [0],
            'hand_types' => [1],
        ];

        $total = max(1, (int) $this->option('tournaments'));
        for ($i = 1; $i <= $total; $i++) {
            $bots = $factory->allocateBots($pool, $percentages, max(2, (int) $this->option('players')));
            $tournament = $factory->create($settings, $bots, null, false);
            $tournament->update(['config' => array_merge($tournament->config, ['simulated' => true])]);
            $factory->start($tournament);

            $started = microtime(true);
            $this->line("<info>[{$i}/{$total}]</info> {$tournament->title}: " . count($bots) . ' jugadores');
            $last = 0;
            $result = $simulator->run($tournament, function (int $round, int $alive, int $tables) use (&$last) {
                if ($round - $last >= 20) {
                    $last = $round;
                    $this->line("   ronda {$round}: {$alive} jugadores, {$tables} mesas");
                }
            }, (int) $this->option('max-rounds'));

            $hands = $tournament->hands()->count();
            $actions = \DB::table('hand_actions')->whereIn('tournament_hand_id', $tournament->hands()->select('id'))->count();
            $this->line(sprintf(
                '   %s en %d rondas · %d manos · %d acciones · %s s%s',
                $result['finished'] ? 'terminado (gana ' . $result['winner'] . ')' : 'NO terminó (tope de rondas)',
                $result['rounds'], $hands, $actions, round(microtime(true) - $started, 1), ''
            ));
        }

        $this->newLine();
        $this->line('Para auditar:  php artisan bots:audit-postflop --only-simulated');

        return self::SUCCESS;
    }

    /** @return array<int, int> id => peso */
    private function percentages($pool): array
    {
        if ($this->option('bots')) {
            $out = [];
            foreach (explode(',', (string) $this->option('bots')) as $pair) {
                [$id, $weight] = array_pad(explode(':', trim($pair)), 2, 1);
                if ($pool->has((int) $id)) {
                    $out[(int) $id] = max(1, (int) $weight);
                }
            }

            return $out;
        }

        return $pool->filter(fn(Bot $b) => ($b->metadata['source'] ?? null) === 'pt4_import' || ($b->metadata['source'] ?? null) === 'pt4_pool')
            ->mapWithKeys(fn(Bot $b) => [$b->id => 1])->all();
    }

    private function purge(): int
    {
        $simulated = Tournament::all()->filter(fn(Tournament $t) => (bool) data_get($t->config, 'simulated'));
        foreach ($simulated as $t) {
            $t->delete();
        }
        $this->info($simulated->count() . ' torneos simulados borrados.');

        return self::SUCCESS;
    }
}
