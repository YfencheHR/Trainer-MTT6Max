<?php

namespace App\Console\Commands;

use App\Services\Equity\EquitySimulationClient;
use App\Services\Equity\HandMatrixGenerator;
use Illuminate\Console\Command;

class GenerateHandMatrixTables extends Command
{
    protected $signature = 'equity:generate-tables {--force : Force regeneration even if tables exist}';
    protected $description = 'Genera tablas de ataque y defensa para todos los stack depths';

    protected EquitySimulationClient $client;
    protected HandMatrixGenerator $generator;

    public function __construct(EquitySimulationClient $client, HandMatrixGenerator $generator)
    {
        parent::__construct();
        $this->client = $client;
        $this->generator = $generator;
    }

    public function handle()
    {
        $this->info('🎯 Iniciando generación de tablas hand-matrix...');
        $this->line('');

        // Verificar conexión con servicio Python
        $this->info('⏳ Verificando conexión con Python service...');
        if (!$this->client->healthCheck()) {
            $this->error('❌ Python service no está disponible en http://python-service:5000');
            $this->line('   Asegúrate que el contenedor python-service está corriendo.');
            return Command::FAILURE;
        }
        $this->info('✅ Python service disponible');
        $this->line('');

        // Generar tablas
        $this->info('🔄 Generando tablas (esto puede tomar varios minutos)...');
        $this->line('');

        try {
            $results = $this->generator->generateAllTables($this->option('force'));

            // Mostrar resultados
            $this->info('📊 Resumen de generación:');
            $this->table(
                ['Profundidad', 'Ataque', 'Defensa', 'Estado'],
                $this->formatResults($results)
            );

            $this->line('');
            if ($results['success']) {
                $this->info('✅ Generación completada exitosamente');
                $this->info("   Total tablas: {$results['tables_generated']}");
                $this->line('');
                $this->info('📁 Tablas guardadas en: storage/hand-matrices/');
                $this->line('');
                return Command::SUCCESS;
            } else {
                $this->error('⚠️ Generación completada con errores:');
                foreach ($results['errors'] as $error) {
                    $this->error("  - $error");
                }
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('❌ Error durante la generación: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    protected function formatResults(array $results): array
    {
        $rows = [];
        foreach ($results['tables'] ?? [] as $depth => $tables) {
            $status = '✅';
            $rows[] = [
                $depth,
                'Generado',
                'Generado',
                $status,
            ];
        }
        return $rows;
    }
}
