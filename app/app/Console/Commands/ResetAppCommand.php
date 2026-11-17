<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deja la aplicación como recién instalada: BORRA todos los datos de la base de datos y vuelve a cargar los datos base.
 *
 * Es una herramienta de mantenimiento (se lanza con `npm run reset-app` desde app/), no forma parte del uso normal.
 * Para que un descuido no destruya nada, enseña qué va a borrar y pide confirmación; sin ella (o sin terminal
 * interactiva) cancela. Con --force no pregunta.
 */
class ResetAppCommand extends Command
{
    protected $signature = 'app:reset {--force : No pedir confirmación}';

    protected $description = 'BORRA todos los datos de la base de datos y vuelve a cargar los datos base (seeders)';

    /** Lo que se le enseña al usuario antes de borrar: etiqueta => tabla. */
    private const SUMMARY = [
        'Bots' => 'bots',
        'Torneos' => 'tournaments',
        'Manos jugadas' => 'tournament_hands',
        'Usuarios' => 'users',
        'Manos de PokerTracker importadas' => 'pt4_imported_hands',
    ];

    public function handle(): int
    {
        $this->warn('Esto BORRA TODOS los datos de la aplicación y la deja como recién instalada:');
        foreach (self::SUMMARY as $label => $table) {
            $this->line(sprintf('  - %s: %s', $label, Schema::hasTable($table) ? number_format(DB::table($table)->count(), 0, ',', '.') : '0'));
        }
        $this->line('Se conservan únicamente los datos base (cartas, manos, bots primarios y su configuración).');

        if (! $this->option('force') && ! $this->confirm('¿Seguro que quieres borrarlo todo y empezar de cero?', false)) {
            $this->info('Cancelado: no se ha borrado nada.');

            return self::FAILURE;
        }

        $this->info('Borrando datos...');
        $this->call('db:wipe', ['--force' => true]);

        // Migraciones + datos base (la misma preparación que hace la aplicación al arrancar)
        $code = $this->call('db:initialize');
        if ($code === self::SUCCESS) {
            $this->info('Hecho: la aplicación está como recién instalada.');
        }

        return $code;
    }
}
