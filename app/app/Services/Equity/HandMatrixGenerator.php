<?php

namespace App\Services\Equity;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class HandMatrixGenerator
{
    protected EquitySimulationClient $client;
    protected string $storagePath = 'hand-matrices';

    // Stack depths a generar: 0-6%, 6-25, 25-50, 50-100, 100+
    protected array $stackDepths = [
        '0-6'   => 3,
        '6-25'  => 15,
        '25-50' => 37,
        '50-100'=> 75,
        '100+'  => 150,
    ];

    public function __construct(EquitySimulationClient $client)
    {
        $this->client = $client;
    }

    /**
     * Genera todas las tablas (ataque y defensa para cada profundidad)
     */
    public function generateAllTables(bool $force = false): array
    {
        Log::info('Starting hand matrix generation');

        $results = [
            'success' => true,
            'tables_generated' => 0,
            'errors' => [],
            'tables' => [],
        ];

        foreach ($this->stackDepths as $label => $depth) {
            try {
                Log::info("Generating tables for depth $label ($depth bb)");

                // Generar tabla de ataque
                $attackTable = $this->client->generateAttackTable($depth, 20);
                $this->saveTable('attack', $label, $attackTable);
                $results['tables'][$label]['attack'] = $attackTable;
                $results['tables_generated']++;

                // Generar tabla de defensa
                $defenseTable = $this->client->generateDefenseTable($depth, 15);
                $this->saveTable('defense', $label, $defenseTable);
                $results['tables'][$label]['defense'] = $defenseTable;
                $results['tables_generated']++;

                Log::info("Tables generated successfully for depth $label");
            } catch (\Exception $e) {
                $error = "Failed to generate tables for $label: " . $e->getMessage();
                Log::error($error);
                $results['errors'][] = $error;
                $results['success'] = false;
            }
        }

        // Generar archivo índice
        $this->saveIndex($results);

        Log::info('Hand matrix generation completed', [
            'tables_generated' => $results['tables_generated'],
            'errors_count' => count($results['errors']),
        ]);

        return $results;
    }

    /**
     * Guarda una tabla individual
     */
    protected function saveTable(string $type, string $depth, array $data): void
    {
        $filename = "{$this->storagePath}/{$type}_{$depth}.json";
        $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        Storage::put($filename, $content);
    }

    /**
     * Guarda índice maestro de tablas
     */
    protected function saveIndex(array $data): void
    {
        $index = [
            'generated_at' => now()->toIso8601String(),
            'version' => '1.0.0',
            'stack_depths' => array_keys($this->stackDepths),
            'summary' => [
                'total_tables' => $data['tables_generated'],
                'success' => $data['success'],
            ],
            'tables' => $data['tables'],
        ];

        $filename = "{$this->storagePath}/index.json";
        $content = json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        Storage::put($filename, $content);
    }

    /**
     * Carga una tabla existente
     */
    public function loadTable(string $type, string $depth): ?array
    {
        $filename = "{$this->storagePath}/{$type}_{$depth}.json";

        if (!Storage::exists($filename)) {
            return null;
        }

        $content = Storage::get($filename);
        return json_decode($content, true);
    }

    /**
     * Carga el índice maestro
     */
    public function loadIndex(): ?array
    {
        $filename = "{$this->storagePath}/index.json";

        if (!Storage::exists($filename)) {
            return null;
        }

        $content = Storage::get($filename);
        return json_decode($content, true);
    }

    /**
     * Obtiene rango de ataque para una profundidad
     */
    public function getAttackRange(string $depth): array
    {
        $table = $this->loadTable('attack', $depth);
        return $table['range'] ?? [];
    }

    /**
     * Obtiene rango de defensa para una profundidad
     */
    public function getDefenseRange(string $depth): array
    {
        $table = $this->loadTable('defense', $depth);
        return $table['range'] ?? [];
    }

    /**
     * Verifica si una mano entra en el rango de ataque
     */
    public function isInAttackRange(string $hand, string $depth): bool
    {
        $range = $this->getAttackRange($depth);
        return in_array($hand, $range);
    }

    /**
     * Verifica si una mano entra en el rango de defensa
     */
    public function isInDefenseRange(string $hand, string $depth): bool
    {
        $range = $this->getDefenseRange($depth);
        return in_array($hand, $range);
    }

    /**
     * Obtiene información detallada de una mano
     */
    public function getHandInfo(string $hand, string $type, string $depth): ?array
    {
        $table = $this->loadTable($type, $depth);
        return $table['tables'][$hand] ?? null;
    }

    /**
     * Obtiene recomendación de acción
     */
    public function getHandRecommendation(string $hand, string $situation, string $depth): array
    {
        $inAttack = $this->isInAttackRange($hand, $depth);
        $inDefense = $this->isInDefenseRange($hand, $depth);

        if ($situation === 'OPEN') {
            return [
                'action' => $inAttack ? 'OPEN' : 'FOLD',
                'in_range' => $inAttack,
            ];
        }

        if ($situation === 'DEFEND') {
            return [
                'action' => $inDefense ? 'DEFEND' : 'FOLD',
                'in_range' => $inDefense,
            ];
        }

        return [
            'action' => 'FOLD',
            'in_range' => false,
        ];
    }
}
