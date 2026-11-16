<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class InitializeDatabaseCommand extends Command
{
    protected $signature = 'db:initialize';
    protected $description = 'Initialize database with migrations and seeds';

    public function handle()
    {
        $this->info('🔄 Inicializando base de datos...');

        $this->info('📋 Ejecutando migraciones...');
        $this->call('migrate', ['--force' => true]);

        $this->info('🌱 Ejecutando seeders...');
        $this->call('db:seed', ['--force' => true]);

        $this->info('✅ Base de datos inicializada exitosamente!');
        $this->info('📊 Datos cargados:');
        $this->info('   - Bots: ' . \App\Models\Bot::count());
        $this->info('   - Made Hands: ' . \App\Models\MadeHand::count());
        $this->info('   - Cards: ' . \App\Models\Card::count());
    }
}
