<?php

namespace App\Services\Bots\Brain;

use App\Models\Bot;
use App\Models\BotBrainSnapshot;
use App\Models\TournamentPlayer;

/**
 * Qué cerebro usa cada jugador.
 *
 *   jugador con snapshot  → el cerebro que tenía su bot cuando se creó el jugador (congelado)
 *                           + los cambios propios del jugador (brain_overrides: su "evolución")
 *   jugador sin snapshot  → el cerebro actual de su bot (jugadores antiguos)
 *
 * Los jugadores HEREDAN el cerebro del bot al crearse. Después pueden evolucionar por separado
 * editando solo su capa (brain_overrides) sin tocar al bot ni a los demás jugadores.
 */
class BrainRepository
{
    /** @var array<int, array> */
    private array $cache = [];

    public function __construct(private BrainAssembler $assembler)
    {
    }

    public function forPlayer(int $tournamentPlayerId): array
    {
        if (isset($this->cache[$tournamentPlayerId])) {
            return $this->cache[$tournamentPlayerId];
        }

        $tp = TournamentPlayer::with('bot.hudStats')->findOrFail($tournamentPlayerId);

        if ($tp->brain_snapshot_id) {
            $brain = BotBrainSnapshot::findOrFail($tp->brain_snapshot_id)->brain;
        } elseif ($tp->bot) {
            $brain = $this->assembler->forBot($tp->bot);
        } else {
            $brain = BrainTemplates::forType('tag');
        }

        $brain = $this->assembler->applyLayer($brain, $tp->brain_overrides ?? []);

        return $this->cache[$tournamentPlayerId] = $brain;
    }

    /** Congela el cerebro actual de un bot y devuelve el id del snapshot (reutiliza uno idéntico). */
    public function snapshotFor(Bot $bot): int
    {
        $brain = $this->assembler->forBot($bot);
        $hash = BrainRunner::hash($brain);

        return BotBrainSnapshot::firstOrCreate(
            ['hash' => $hash],
            ['bot_id' => $bot->id, 'brain' => $brain, 'created_at' => now()]
        )->id;
    }

    public function forget(?int $tournamentPlayerId = null): void
    {
        if ($tournamentPlayerId === null) {
            $this->cache = [];
        } else {
            unset($this->cache[$tournamentPlayerId]);
        }
    }
}
