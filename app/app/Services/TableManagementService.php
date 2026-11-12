<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\Table;

class TableManagementService
{
    const MAX_PLAYERS_PER_TABLE = 6;

    /**
     * Distribuir jugadores en mesas cuando se inicia el torneo
     */
    public function distributePlayers(Tournament $tournament): void
    {
        $players = $tournament->players()->inRandomOrder()->get(); // asientos al azar

        if ($players->isEmpty()) {
            throw new \Exception('No players in tournament');
        }

        // Calcular cantidad de mesas necesarias
        $totalPlayers = $players->count();
        $numTables = $this->calculateTablesNeeded($totalPlayers);

        // Distribuir jugadores de forma pareja
        $playersPerTable = $this->distributePlayersEvenly($totalPlayers, $numTables);

        $playerIndex = 0;
        for ($tableNum = 1; $tableNum <= $numTables; $tableNum++) {
            $table = Table::create([
                'tournament_id' => $tournament->id,
                'table_number' => $tableNum,
                'max_players' => self::MAX_PLAYERS_PER_TABLE,
                'is_active' => true,
            ]);

            $playersForThisTable = $playersPerTable[$tableNum - 1] ?? 0;

            for ($seat = 1; $seat <= $playersForThisTable; $seat++) {
                if ($playerIndex < $players->count()) {
                    $table->players()->attach(
                        $players[$playerIndex]->id,
                        ['seat_number' => $seat, 'is_active' => true]
                    );
                    $playerIndex++;
                }
            }
        }
    }

    /**
     * Calcular cantidad óptima de mesas
     * Garantiza mínimo 4 jugadores por mesa (si es posible)
     * Ejemplo: 13 jugadores → 3 mesas (4+4+5)
     * Ejemplo: 9 jugadores → 2 mesas (4+5), NO 3 mesas (3+3+3)
     */
    private function calculateTablesNeeded(int $totalPlayers): int
    {
        $minPlayersPerTable = 4;

        if ($totalPlayers <= self::MAX_PLAYERS_PER_TABLE) {
            return 1;
        }

        // Buscar mínima cantidad de mesas donde cada una tenga al menos 4 jugadores
        for ($numTables = 1; $numTables <= $totalPlayers; $numTables++) {
            $minInThisConfig = (int) floor($totalPlayers / $numTables);
            $maxInThisConfig = (int) ceil($totalPlayers / $numTables);

            // Verificar si todos los jugadores se pueden distribuir entre 4 y 6 por mesa
            if ($minInThisConfig >= $minPlayersPerTable && $maxInThisConfig <= self::MAX_PLAYERS_PER_TABLE) {
                return $numTables;
            }
        }

        // Si no se puede garantizar mínimo 4, usar la mínima cantidad de mesas
        return (int) ceil($totalPlayers / self::MAX_PLAYERS_PER_TABLE);
    }

    /**
     * Distribuir jugadores de forma pareja entre mesas
     * Garantiza que cada mesa tenga entre 4 y 6 jugadores
     * Ejemplo: 13 jugadores en 3 mesas → [4, 4, 5]
     * Ejemplo: 14 jugadores en 3 mesas → [5, 5, 4]
     * Ejemplo: 20 jugadores en 4 mesas → [5, 5, 5, 5]
     */
    private function distributePlayersEvenly(int $totalPlayers, int $numTables): array
    {
        $basePerTable = (int) floor($totalPlayers / $numTables);
        $remainder = $totalPlayers % $numTables;

        $distribution = [];

        // Distribuir de forma pareja: el remainder se agrega a las últimas mesas
        for ($i = 0; $i < $numTables; $i++) {
            $distribution[$i] = $basePerTable + ($i >= ($numTables - $remainder) ? 1 : 0);
        }

        return $distribution;
    }

    /**
     * Reorganizar mesas cuando se elimina un jugador
     */
    public function reorganizeTablesAfterElimination(Tournament $tournament): void
    {
        $activePlayers = $tournament->players()
            ->where('is_eliminated', false)
            ->count();

        if ($activePlayers === 0) {
            // Todos eliminados
            $tournament->tables()->update(['is_active' => false]);
            return;
        }

        // Obtener mesas activas
        $tables = $tournament->activeTables()->get();
        $totalTablesNeeded = $this->calculateTablesNeeded($activePlayers);

        // Si necesitamos menos mesas, desactivar las extras
        if ($totalTablesNeeded < $tables->count()) {
            $tablesToDeactivate = $tables->skip($totalTablesNeeded);

            foreach ($tablesToDeactivate as $table) {
                $table->update(['is_active' => false]);
            }
        }

        // Redistribuir jugadores en mesas activas
        $this->balanceTables($tournament);
    }

    /**
     * Nivelar los jugadores entre mesas activas
     */
    private function balanceTables(Tournament $tournament): void
    {
        $activeTables = $tournament->activeTables()->get();
        $activePlayers = $tournament->players()
            ->where('is_eliminated', false)
            ->orderBy('position')
            ->get();

        if ($activePlayers->isEmpty()) {
            return;
        }

        // Limpiar mesas actuales
        foreach ($activeTables as $table) {
            $table->players()->detach();
        }

        // Redistribuir jugadores
        $playerIndex = 0;
        $tableIndex = 0;

        foreach ($activePlayers as $player) {
            $table = $activeTables[$tableIndex];
            $seatNumber = ($playerIndex % self::MAX_PLAYERS_PER_TABLE) + 1;

            $table->players()->attach(
                $player->id,
                ['seat_number' => $seatNumber, 'is_active' => true]
            );

            $playerIndex++;

            // Pasar a siguiente mesa si esta está llena
            if ($seatNumber === self::MAX_PLAYERS_PER_TABLE && $tableIndex < $activeTables->count() - 1) {
                $tableIndex++;
            }
        }
    }
}
