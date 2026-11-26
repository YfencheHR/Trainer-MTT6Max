<?php

namespace App\Console\Commands;

use App\Services\Import\Pt4Importer;
use App\Services\Replay\RealHandReplay;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class Pt4ImportCommand extends Command
{
    protected $signature = 'pt4:import {file? : zip o volcado .backup/.sql de PokerTracker} {--min-hands=20 : manos mínimas de un jugador para tenerlo en cuenta} {--bot-hands=200 : manos sumadas mínimas para crear un bot nuevo} {--skip-restore : usar la base temporal ya restaurada} {--keep : no borrar la base temporal al acabar} {--dry : calcular sin escribir} {--reset : olvidar lo ya importado y recalcular desde cero con esta copia} {--no-replay : no comprobar al terminar cuánto se parecen los perfiles a los jugadores reales} {--replay-decisions=600 : decisiones de la réplica de comprobación} {--replay-every=40 : la réplica usa 1 de cada N manos de la copia}';

    protected $description = 'Importa estadísticas de PokerTracker 4 a los bots desde una copia (sin conectar con PokerTracker)';

    public function handle(): int
    {
        $connection = DB::connection();
        $lockName = 'trainer-mtt:pt4-import';
        $lock = $connection->selectOne(
            'SELECT CASE WHEN pg_try_advisory_lock(hashtextextended(?, 0)) THEN 1 ELSE 0 END AS locked',
            [$lockName],
        );

        if ((int) ($lock->locked ?? 0) !== 1) {
            $this->error('Ya hay otra importación de PokerTracker en curso. Espera a que termine antes de iniciar otra.');

            return self::FAILURE;
        }

        $imp = new Pt4Importer(fn ($m) => $this->line($m));
        try {
            if (! $this->option('skip-restore')) {
                if (! $this->argument('file') || ! is_file($this->argument('file'))) {
                    $this->error('Indica el archivo (zip o volcado) a importar.');

                    return self::FAILURE;
                }
                $this->info($imp->restore($this->argument('file')).' tablas restauradas');
            }
            // Todo lo que escribe la importación en la base de datos va en UNA transacción: si falla a mitad (archivo dañado,
            // falta una columna...) se deshace entero y los perfiles anteriores quedan como estaban, en vez de perderse.
            $r = DB::transaction(fn () => $imp->import((int) $this->option('min-hands'), (bool) $this->option('dry'), (int) $this->option('bot-hands'), (bool) $this->option('reset')));
            // Con la importación ya guardada (y la copia aún sin borrar) se comprueba cuánto se parecen los perfiles nuevos a los jugadores reales:
            // el resultado queda en el registro de la importación, sin que haga falta ningún paso por consola.
            if (! $this->option('dry') && ! $this->option('no-replay') && ($r['profiles'] || $r['created'])) {
                $this->replayReport($imp);
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            try {
                if (! $this->option('keep') && ! $this->option('skip-restore')) {
                    $imp->drop();
                }
            } finally {
                $connection->select(
                    'SELECT pg_advisory_unlock(hashtextextended(?, 0))',
                    [$lockName],
                );
            }
        }

        if ($r['profiles']) {
            $this->table(['Perfil', 'Jugadores', 'Manos', 'VPIP', 'PFR', '3bet'], array_map(fn ($p) => [$p['bot'], $p['players'], $p['hands'], $p['vpip'], $p['pfr'], $p['three_bet']], $r['profiles']));
        }
        if ($r['created']) {
            $this->info('Bots nuevos (no encajan en ningún perfil):');
            $this->table(['Bot', 'Jugadores', 'Manos', 'VPIP', 'PFR', '3bet'], array_map(fn ($p) => [$p['bot'], $p['players'], $p['hands'], $p['vpip'], $p['pfr'], $p['three_bet']], $r['created']));
        }

        return self::SUCCESS;
    }

    /**
     * Réplica de una muestra de manos de la copia: cada jugada de un jugador real se presenta al perfil al que pertenece y se mide cuántas veces su
     * acción más probable coincide con lo que hizo la persona (todos con manos candidatas, sin el sesgo del showdown). Un fallo aquí nunca estropea la importación.
     */
    private function replayReport(Pt4Importer $imp): void
    {
        try {
            $replay = app(RealHandReplay::class);
            $byName = $replay->botsByPlayerName();
            if (! $byName) {
                return;
            }
            $this->newLine();
            $this->info('Comprobando cuánto se parecen los perfiles a los jugadores reales (réplica de una muestra de manos)…');
            $hands = (function () use ($imp) {
                foreach ($imp->tmp()->cursor('select history from tourney_hand_histories') as $row) {
                    yield $row->history;
                }
            })();
            $result = $replay->run($hands, $byName, [
                'limit' => (int) $this->option('replay-decisions'), 'every' => (int) $this->option('replay-every'), 'marginal' => true, 'candidates' => 16,
            ]);
            if (! $result['rows']) {
                $this->warn('La muestra no tiene decisiones comparables.');

                return;
            }
            $lines = RealHandReplay::summarize($result['rows']);
            $this->line('Decisiones comparadas: '.count($result['rows']).' · acierta la acción más probable en el '.$lines[0]['accuracy'].' % (± '.$lines[0]['ci'].')');
            $this->table(['Calle', 'Decisiones', 'Acierto', '± (95 %)'], array_map(
                fn ($l) => [$l['name'], $l['n'], $l['accuracy'].' %', $l['ci']],
                array_values(array_filter($lines, fn ($l) => str_starts_with($l['name'], 'calle ')))
            ));
        } catch (\Throwable $e) {
            $this->warn('No se pudo comprobar la réplica: '.$e->getMessage());
        }
    }
}
