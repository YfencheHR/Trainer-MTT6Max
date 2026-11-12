<?php

namespace App\Services\Tournament;

use App\Models\Table;
use App\Models\Tournament;
use Illuminate\Support\Facades\DB;

/**
 * Nivelación de mesas al final de cada ronda:
 *  1. Si sobran mesas (jugadores vivos / 6), se rompen las más cortas y sus jugadores se reparten.
 *  2. Se nivelan las mesas restantes (diferencia máxima de 1 jugador entre mesas).
 * El héroe nunca cambia de mesa; se mueven siempre jugadores bot.
 */
class TableBalanceService
{
    public const TABLE_SIZE = 6;

    /**
     * @return array<int, array{type:string,text:string,table_id:?int,player_id:?int,payload:array}>
     */
    public function rebalance(Tournament $tournament): array
    {
        $events = [];
        $heroId = $tournament->players()->where('is_bot', false)->value('id');

        // Mesas vacías fuera
        foreach ($this->activeTables($tournament) as $t) {
            if ($this->count($t) === 0) {
                $t->update(['is_active' => false]);
            }
        }

        $alive = $this->aliveCount($tournament);
        if ($alive === 0) {
            return $events;
        }
        $needed = max(1, (int) ceil($alive / self::TABLE_SIZE));

        // 1) Romper mesas sobrantes (la más corta que no sea la del héroe)
        while ($this->activeTables($tournament)->count() > $needed) {
            $tables = $this->activeTables($tournament);
            $candidate = $tables
                ->filter(fn(Table $t) => !$this->hasPlayer($t, $heroId))
                ->sortBy(fn(Table $t) => $this->count($t))
                ->first();
            if (!$candidate) {
                break;
            }

            $moved = [];
            $movedData = [];
            foreach ($this->players($candidate) as $p) {
                $target = $this->activeTables($tournament)
                    ->filter(fn(Table $t) => $t->id !== $candidate->id && $this->count($t) < self::TABLE_SIZE)
                    ->sortBy(fn(Table $t) => $this->count($t))
                    ->first();
                if (!$target) {
                    break;
                }
                $this->move($p->tournament_player_id, $candidate, $target);
                $moved[] = "{$p->name} → Mesa {$target->table_number}";
                $movedData[] = ['player_id' => $p->tournament_player_id, 'name' => $p->name, 'to_table_id' => $target->id, 'to_table' => $target->table_number];
            }
            $candidate->update(['is_active' => false]);
            $events[] = [
                'type' => 'table_break',
                'text' => "Mesa {$candidate->table_number} se rompe: " . implode(', ', $moved),
                'table_id' => $candidate->id,
                'player_id' => null,
                'payload' => ['table' => $candidate->table_number, 'moved' => $movedData],
            ];
        }

        // 2) Nivelar: mover bots de la mesa con más jugadores a la que menos tiene
        for ($i = 0; $i < 100; $i++) {
            $tables = $this->activeTables($tournament)->sortBy(fn(Table $t) => $this->count($t))->values();
            if ($tables->count() < 2) {
                break;
            }
            $min = $tables->first();
            $max = $tables->last();
            if ($this->count($max) - $this->count($min) < 2) {
                break;
            }

            $player = $this->players($max)
                ->filter(fn($p) => $p->tournament_player_id !== $heroId)
                ->sortByDesc('seat_number')
                ->first();
            if (!$player) {
                break;
            }
            $this->move($player->tournament_player_id, $max, $min);
            $events[] = [
                'type' => 'player_moved',
                'text' => "{$player->name} pasa de Mesa {$max->table_number} a Mesa {$min->table_number} (nivelación)",
                'table_id' => $min->id,
                'player_id' => $player->tournament_player_id,
                'payload' => ['from_table' => $max->table_number, 'to_table' => $min->table_number, 'reason' => 'balance'],
            ];
        }

        return $events;
    }

    // ------------------------------------------------------------ helpers

    private function activeTables(Tournament $t)
    {
        return Table::where('tournament_id', $t->id)->where('is_active', true)->orderBy('table_number')->get();
    }

    private function count(Table $t): int
    {
        return DB::table('table_players')->where('table_id', $t->id)->count();
    }

    private function aliveCount(Tournament $t): int
    {
        return $t->players()->where('is_eliminated', false)->where('current_stack', '>', 0)->count();
    }

    private function hasPlayer(Table $t, ?int $playerId): bool
    {
        return $playerId !== null
            && DB::table('table_players')->where('table_id', $t->id)->where('tournament_player_id', $playerId)->exists();
    }

    /** Jugadores sentados: tournament_player_id, seat_number, name. */
    private function players(Table $t)
    {
        return DB::table('table_players')
            ->join('tournament_players', 'tournament_players.id', '=', 'table_players.tournament_player_id')
            ->where('table_players.table_id', $t->id)
            ->orderBy('table_players.seat_number')
            ->get(['table_players.tournament_player_id', 'table_players.seat_number', 'tournament_players.name']);
    }

    private function move(int $playerId, Table $from, Table $to): void
    {
        $occupied = DB::table('table_players')->where('table_id', $to->id)->pluck('seat_number')->all();
        $seat = collect(range(1, self::TABLE_SIZE))->first(fn($s) => !in_array($s, $occupied, true));
        if ($seat === null) {
            throw new \RuntimeException("La mesa {$to->table_number} está llena");
        }

        DB::table('table_players')
            ->where('table_id', $from->id)
            ->where('tournament_player_id', $playerId)
            ->update(['table_id' => $to->id, 'seat_number' => $seat, 'updated_at' => now()]);
    }
}
