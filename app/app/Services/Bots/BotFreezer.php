<?php

namespace App\Services\Bots;

use App\Models\Bot;
use App\Models\BotBetSizeCount;
use App\Models\BotPreflopHandRange;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Congela un bot para un torneo: crea una COPIA ARCHIVADA con sus estadísticas, rangos por posición y tablas preflop
 * tal como están ahora, y el torneo juega con esa copia.
 *
 * Así recalcular una importación, reimportar (que actualiza estadísticas en el sitio), editar el perfil o borrarlo no
 * cambian ni rompen un torneo, en curso o terminado, y se puede reproducir con los mismos datos. (El cerebro ya se
 * congelaba aparte con BrainRepository::snapshotFor; la decisión lee además estadísticas y tablas del bot en vivo.)
 *
 * Las copias se reutilizan: dos torneos con un perfil idéntico comparten la misma copia (se identifica por una huella
 * de su contenido), de modo que no se acumulan datos por torneo.
 */
class BotFreezer
{
    private const BOT_FIELDS = [
        'nombre', 'tipo', 'perfil', 'vpip', 'pfr', 'tilt_risk', 'profesional', 'version_rango', 'descripcion',
        'estado_juego', 'brain', 'brain_version',
    ];

    public function __construct(private RivalPreflopTables $poolTables) {}

    /** Devuelve la copia congelada de $bot (la crea si no existe una idéntica). */
    public function frozenFor(Bot $bot): Bot
    {
        // Las tablas preflop de un Pool se generan bajo demanda: se asegura que existan antes de copiarlas
        if ($bot->isPool()) {
            $this->poolTables->forBot($bot);
        }

        $hash = $this->fingerprint($bot);
        $existing = Bot::withoutGlobalScope('active')
            ->whereNotNull('archived_at')
            ->where('metadata->frozen_hash', $hash)
            ->first();

        return $existing ?? DB::transaction(fn () => $this->copy($bot, $hash));
    }

    /** Huella del contenido que usa el juego: dos bots con el mismo contenido tienen la misma huella. */
    public function fingerprint(Bot $bot): string
    {
        $strip = fn (array $row) => Arr::except($row, ['id', 'bot_id', 'created_at', 'updated_at']);
        $meta = (array) $bot->metadata;
        unset($meta['history'], $meta['frozen_hash'], $meta['frozen_from']); // el histórico de importaciones no afecta a las decisiones

        $sorted = fn ($rows) => $rows->map(fn ($row) => $strip($row->attributesToArray()))
            ->sortBy(fn ($row) => json_encode($row))->values()->all();

        return sha1(json_encode([
            'bot' => $bot->only(self::BOT_FIELDS) + ['icono' => $bot->getRawOriginal('icono'), 'metadata' => $meta],
            'hud' => $sorted($bot->hudStats()->get()),
            'ranges' => $sorted($bot->ranges()->get()),
            'preflop' => $sorted($bot->allPreflopHandRanges()->get()),
            'bets' => $sorted(BotBetSizeCount::where('bot_id', $bot->id)->get()),
            'bet_prior' => DB::table('bot_bet_size_prior')->where('bot_id', $bot->id)->orderBy('street')->get(['street', 'air_share'])->map(fn ($r) => (array) $r)->all(),
            'pot_stats' => $this->potStats($bot)->map(fn ($r) => Arr::except($r, ['id', 'bot_id', 'created_at', 'updated_at']))->all(),
            'iso' => $this->isoStats($bot)->map(fn ($r) => Arr::except($r, ['id', 'bot_id', 'created_at', 'updated_at']))->all(),
            'limp_response' => $this->limpResponseStats($bot)->map(fn ($r) => Arr::except($r, ['id', 'bot_id', 'created_at', 'updated_at']))->all(),
        ]));
    }

    private function copy(Bot $bot, string $hash): Bot
    {
        $copy = $bot->replicate();
        $copy->forceFill([
            'archived_at' => now(),
            'metadata' => array_merge((array) $bot->metadata, ['frozen_hash' => $hash, 'frozen_from' => $bot->id]),
        ])->save();

        foreach ($bot->hudStats as $row) {
            $row->replicate()->forceFill(['bot_id' => $copy->id])->save();
        }
        foreach ($bot->ranges as $row) {
            $row->replicate()->forceFill(['bot_id' => $copy->id])->save();
        }

        // Tablas preflop por mano (≈1.000 filas): inserción en bloque
        $now = now();
        $rows = $bot->allPreflopHandRanges()->get()->map(
            fn (BotPreflopHandRange $r) => array_merge(
                Arr::except($r->getAttributes(), ['id']),
                ['bot_id' => $copy->id, 'created_at' => $now, 'updated_at' => $now]
            )
        )->all();
        foreach (array_chunk($rows, 200) as $chunk) {
            BotPreflopHandRange::insert($chunk);
        }

        // Matriz de apuestas (conteos)
        $bets = BotBetSizeCount::where('bot_id', $bot->id)->get()->map(
            fn ($r) => ['bot_id' => $copy->id, 'street' => $r->street, 'tier' => $r->tier, 'bucket' => $r->bucket, 'n' => $r->n, 'created_at' => $now, 'updated_at' => $now]
        )->all();
        if ($bets) {
            BotBetSizeCount::insert($bets);
        }
        foreach (DB::table('bot_bet_size_prior')->where('bot_id', $bot->id)->get() as $prior) {
            DB::table('bot_bet_size_prior')->insert(['bot_id' => $copy->id, 'street' => $prior->street, 'air_share' => $prior->air_share, 'created_at' => $now, 'updated_at' => $now]);
        }

        // Lo que hace en botes limpeados / subidos por posición y stack (PokerTracker): lo lee el Perceiver con el id del bot que juega
        if ($limpResponse = $this->limpResponseStats($bot)->map(fn ($r) => array_merge(Arr::except($r, ['id']), ['bot_id' => $copy->id, 'created_at' => $now, 'updated_at' => $now]))->all()) {
            DB::table('bot_limp_response_stats')->insert($limpResponse);
        }
        if ($iso = $this->isoStats($bot)->map(fn ($r) => array_merge(Arr::except($r, ['id']), ['bot_id' => $copy->id, 'created_at' => $now, 'updated_at' => $now]))->all()) {
            DB::table('bot_iso_stats')->insert($iso);
        }
        foreach (array_chunk($this->potStats($bot)->map(fn ($r) => array_merge(Arr::except($r, ['id']), ['bot_id' => $copy->id, 'created_at' => $now, 'updated_at' => $now]))->all(), 200) as $chunk) {
            DB::table('bot_postflop_pot_stats')->insert($chunk);
        }

        return $copy;
    }

    /** Lo que hace el perfil tras limpear y recibir una subida, por posición (bot_limp_response_stats). */
    private function limpResponseStats(Bot $bot): Collection
    {
        return DB::table('bot_limp_response_stats')->where('bot_id', $bot->id)->orderBy('position')->get()->map(fn ($r) => (array) $r);
    }

    /** Lo que hace el perfil con limpers delante, por posición (bot_iso_stats). */
    private function isoStats(Bot $bot): Collection
    {
        return DB::table('bot_iso_stats')->where('bot_id', $bot->id)->orderBy('position')->get()->map(fn ($r) => (array) $r);
    }

    /** Filas de bot_postflop_pot_stats del bot, ordenadas para que la huella no dependa del orden de lectura. */
    private function potStats(Bot $bot): Collection
    {
        return DB::table('bot_postflop_pot_stats')->where('bot_id', $bot->id)
            ->orderBy('street')->orderBy('pot')->orderBy('position')->orderBy('stack_bucket')->get()->map(fn ($r) => (array) $r);
    }
}
