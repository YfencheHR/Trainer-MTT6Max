<?php

namespace App\Services\Import;

use App\Models\Bot;
use App\Models\BotHudStat;
use App\Models\TournamentPlayer;

/**
 * Retira los perfiles Pool de importaciones anteriores antes de recrearlos (importación inicial o "Recalcular desde cero").
 *
 * Un perfil que ya está en algún torneo NO se borra: se ARCHIVA (desaparece de listas y de la selección de torneos
 * nuevos, pero conserva sus estadísticas y rangos). Así los torneos anteriores a las copias congeladas siguen
 * funcionando y conservando el enlace a su perfil. Los perfiles que nadie usa sí se borran.
 */
class PoolBotRetirer
{
    /** @return array{archived:int, deleted:int} */
    public function retire(): array
    {
        $old = Bot::where('tipo', 'pool')
            ->whereIn('metadata->source', ['pt4_import', 'pt4_pool'])
            ->pluck('id');

        $used = TournamentPlayer::whereIn('bot_id', $old)->pluck('bot_id')->unique()->values();
        $unused = $old->diff($used)->values();

        if ($used->isNotEmpty()) {
            Bot::whereIn('id', $used)->update(['archived_at' => now()]);
        }
        if ($unused->isNotEmpty()) {
            BotHudStat::whereIn('bot_id', $unused)->delete();
            Bot::whereIn('id', $unused)->delete();
        }

        return ['archived' => $used->count(), 'deleted' => $unused->count()];
    }

    /** Cuántos torneos usan hoy perfiles Pool activos (los que se archivarían al recalcular). */
    public function tournamentsAffected(): int
    {
        $pool = Bot::where('tipo', 'pool')->whereIn('metadata->source', ['pt4_import', 'pt4_pool'])->pluck('id');

        return TournamentPlayer::whereIn('bot_id', $pool)->distinct()->count('tournament_id');
    }
}
