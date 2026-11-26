<?php

namespace App\Console\Commands;

use App\Models\Bot;
use App\Models\Card;
use App\Models\MadeHand;
use Illuminate\Console\Command;

/**
 * Puesta en marcha de la base de datos. Se ejecuta SOLA al arrancar el contenedor (docker-compose.yml): nadie
 * tiene que lanzarla a mano, ni al instalar ni al actualizar.
 *
 * Es idempotente y no toca datos del usuario:
 *  - aplica las migraciones pendientes;
 *  - siembra los datos base (cartas, manos, bots primarios...) solo si aún no existen.
 */
class InitializeDatabaseCommand extends Command
{
    protected $signature = 'db:initialize';

    protected $description = 'Aplica las migraciones pendientes y siembra los datos base si la base de datos está vacía';

    public function handle(): int
    {
        $this->info('Inicializando base de datos...');

        $this->call('migrate', ['--force' => true]);

        // Los datos de referencia (la baraja) marcan que la siembra ya se hizo: si existen, no se vuelve a sembrar
        // (duplicaría bots y manos) aunque el usuario haya borrado o creado bots.
        if (Card::count() === 0) {
            $this->info('Cargando datos base (primera vez)...');
            $this->call('db:seed', ['--force' => true]);
        } else {
            $this->info('Datos base ya cargados: no se vuelve a sembrar.');
        }

        $this->info('Base de datos lista. Bots: '.Bot::count().' · Manos hechas: '.MadeHand::count().' · Cartas: '.Card::count());

        return self::SUCCESS;
    }
}
