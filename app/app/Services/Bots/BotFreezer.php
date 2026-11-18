<?php

namespace App\Services\Bots;

use App\Models\Bot;
use App\Models\BotBetSizeCount;
use App\Models\BotPreflopHandRange;
use Illuminate\Support\Arr;
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

    public function __construct(private RivalPreflopTables $poolTables)
    {
    }

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

        return $existing ?? DB::transaction(fn() => $this->copy($bot, $hash));
    }

    /** Huella del contenido que usa el juego: dos bots con el mismo contenido tienen la misma huella. */
    public function fingerprint(Bot $bot): string
    {
        $strip = fn(array $row) => Arr::except($row, ['id', 'bot_id', 'created_at', 'updated_at']);
        $meta = (array) $bot->metadata;
        unset($meta['history'], $meta['frozen_hash'], $meta['frozen_from']); // el histórico de importaciones no afecta a las decisiones

        $sorted = fn($rows) => $rows->map(fn($row) => $strip($row->attributesToArray()))
            ->sortBy(fn($row) => json_encode($row))->values()->all();

        return sha1(json_encode([
            'bot' => $bot->only(self::BOT_FIELDS) + ['icono' => $bot->getRawOriginal('icono'), 'metadata' => $meta],
            'hud' => $sorted($bot->hudStats()->get()),
            'ranges' => $sorted($bot->ranges()->get()),
            'preflop' => $sorted($bot->allPreflopHandRanges()->get()),
            'bets' => $sorted(BotBetSizeCount::where('bot_id', $bot->id)->get()),
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
            fn(BotPreflopHandRange $r) => array_merge(
                Arr::except($r->getAttributes(), ['id']),
                ['bot_id' => $copy->id, 'created_at' => $now, 'updated_at' => $now]
            )
        )->all();
        foreach (array_chunk($rows, 200) as $chunk) {
            BotPreflopHandRange::insert($chunk);
        }

        // Matriz de apuestas (conteos)
        $bets = BotBetSizeCount::where('bot_id', $bot->id)->get()->map(
            fn($r) => ['bot_id' => $copy->id, 'street' => $r->street, 'tier' => $r->tier, 'bucket' => $r->bucket, 'n' => $r->n, 'created_at' => $now, 'updated_at' => $now]
        )->all();
        if ($bets) {
            BotBetSizeCount::insert($bets);
        }

        return $copy;
    }
}
