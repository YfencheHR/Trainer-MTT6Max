<?php

namespace App\Console\Commands;

use App\Services\Import\Pt4Importer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class Pt4ImportCommand extends Command
{
    protected $signature = 'pt4:import {file? : zip o volcado .backup/.sql de PokerTracker} {--min-hands=20 : manos mínimas de un jugador para tenerlo en cuenta} {--bot-hands=200 : manos sumadas mínimas para crear un bot nuevo} {--skip-restore : usar la base temporal ya restaurada} {--keep : no borrar la base temporal al acabar} {--dry : calcular sin escribir} {--reset : olvidar lo ya importado y recalcular desde cero con esta copia}';

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

        $imp = new Pt4Importer(fn($m) => $this->line($m));
        try {
            if (!$this->option('skip-restore')) {
                if (!$this->argument('file') || !is_file($this->argument('file'))) {
                    $this->error('Indica el archivo (zip o volcado) a importar.');

                    return self::FAILURE;
                }
                $this->info($imp->restore($this->argument('file')) . ' tablas restauradas');
            }
            $r = $imp->import((int) $this->option('min-hands'), (bool) $this->option('dry'), (int) $this->option('bot-hands'), (bool) $this->option('reset'));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            try {
                if (!$this->option('keep') && !$this->option('skip-restore')) {
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
            $this->table(['Perfil', 'Jugadores', 'Manos', 'VPIP', 'PFR', '3bet'], array_map(fn($p) => [$p['bot'], $p['players'], $p['hands'], $p['vpip'], $p['pfr'], $p['three_bet']], $r['profiles']));
        }
        if ($r['created']) {
            $this->info('Bots nuevos (no encajan en ningún perfil):');
            $this->table(['Bot', 'Jugadores', 'Manos', 'VPIP', 'PFR', '3bet'], array_map(fn($p) => [$p['bot'], $p['players'], $p['hands'], $p['vpip'], $p['pfr'], $p['three_bet']], $r['created']));
        }

        return self::SUCCESS;
    }
}
