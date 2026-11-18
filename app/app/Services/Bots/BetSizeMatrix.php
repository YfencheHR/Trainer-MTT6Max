<?php

namespace App\Services\Bots;

use App\Models\Bot;
use Illuminate\Support\Facades\DB;

/**
 * Matriz de apuestas de un perfil, a partir de los conteos que guarda el importador (bot_bet_size_counts) mezclados con los
 * de toda la población (bet_size_population): con pocas apuestas un perfil se parece a la población, y con muchas, a sí mismo.
 *
 * Por calle (flop / turn / river) devuelve:
 *  - share:  de cada 100 apuestas, cuántas se hacen con aire, pareja o mano fuerte (composición de su rango de apuesta);
 *  - bluff_share: la parte de aire (el farol, estimado con lo que llega a showdown: validado contra las manos del héroe,
 *    que se conocen todas, con un error de ~1 punto en el river);
 *  - sizes:  con cada tipo de mano, cómo reparte sus apuestas entre los tramos de tamaño (% del bote), suma 100;
 *  - n:      apuestas observadas del perfil en esa calle.
 */
class BetSizeMatrix
{
    /** Apuestas de un tipo de mano a partir de las cuales pesa tanto el perfil como la población al repartir los tamaños. */
    public const SHRINK_SIZES = 30;

    /** Apuestas de una calle a partir de las cuales pesa tanto el perfil como la población en la composición del rango. */
    public const SHRINK_SHARE = 100;

    /** @var array<int, array|null> */
    private array $cache = [];

    /** @var array<string, array<string, array<string, int>>>|null */
    private ?array $population = null;

    /** @return array<string, array{share: array<string,float>, bluff_share: float, sizes: array<string, array<string,float>>, n: int}>|null null si no hay datos */
    public function forBot(Bot $bot): ?array
    {
        if (array_key_exists($bot->id, $this->cache)) {
            return $this->cache[$bot->id];
        }

        $mine = $this->counts(DB::table('bot_bet_size_counts')->where('bot_id', $bot->id)->get());
        $pop = $this->population();
        if (! $mine && ! $pop) {
            return $this->cache[$bot->id] = null;
        }

        $out = [];
        foreach (array_keys(BetTier::STREETS) as $street) {
            $out[$street] = $this->street($mine[$street] ?? [], $pop[$street] ?? []);
        }

        return $this->cache[$bot->id] = $out;
    }

    /** Matriz de la población sola (la de referencia para comparar perfiles). */
    public function population(): array
    {
        return $this->population ??= $this->counts(DB::table('bet_size_population')->get());
    }

    /** @return array<string, array<string, array<string, int>>> calle => tipo => tramo => n */
    private function counts($rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[$row->street][$row->tier][$row->bucket] = (int) $row->n;
        }

        return $out;
    }

    private function street(array $mine, array $pop): array
    {
        $buckets = array_keys(BetTier::BUCKETS);
        $tierTotal = fn(array $byTier, string $tier) => array_sum($byTier[$tier] ?? []);

        $myTotal = array_sum(array_map(fn($tier) => $tierTotal($mine, $tier), BetTier::TIERS));
        $popTotal = array_sum(array_map(fn($tier) => $tierTotal($pop, $tier), BetTier::TIERS));

        // Composición del rango de apuesta
        $share = [];
        foreach (BetTier::TIERS as $tier) {
            $popShare = $popTotal > 0 ? $tierTotal($pop, $tier) / $popTotal : 1 / count(BetTier::TIERS);
            $share[$tier] = ($tierTotal($mine, $tier) + self::SHRINK_SHARE * $popShare) / ($myTotal + self::SHRINK_SHARE);
        }
        $sum = array_sum($share);

        // Tamaños con cada tipo de mano
        $sizes = [];
        foreach (BetTier::TIERS as $tier) {
            $popTier = $tierTotal($pop, $tier);
            $myTier = $tierTotal($mine, $tier);
            foreach ($buckets as $bucket) {
                $popFreq = $popTier > 0 ? ($pop[$tier][$bucket] ?? 0) / $popTier : 1 / count($buckets);
                $sizes[$tier][$bucket] = 100 * (($mine[$tier][$bucket] ?? 0) + self::SHRINK_SIZES * $popFreq) / ($myTier + self::SHRINK_SIZES);
            }
        }

        return [
            'share' => array_map(fn($v) => round(100 * $v / $sum, 2), $share),
            'bluff_share' => round(100 * $share['air'] / $sum, 2),
            'sizes' => array_map(fn($row) => array_map(fn($v) => round($v, 2), $row), $sizes),
            'n' => $myTotal,
        ];
    }
}
