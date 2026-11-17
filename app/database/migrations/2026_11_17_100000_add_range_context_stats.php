<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stats necesarias para derivar la matriz de rangos de un rival (Rivales en Acción - Rangos) según
 * la acción concreta y lo que pasó antes:
 *
 *  1. bot_preflop_hand_stats: nodos preflop que faltaban (call contra apertura, squeeze, respuesta a 4-bet,
 *     aislamiento de limpers). Son contadores por mano inicial y posición, como los existentes.
 *  2. bot_preflop_vs_opener_stats: cómo cambia la respuesta ante una apertura según la POSICIÓN DEL ABRIDOR.
 *  3. bot_hud_stats.accion: filas postflop por tipo de bote preflop
 *     {flop|turn|river}_{agg|def}_{ip|oop}_{limp|srp|3bp|4bp}
 */
return new class extends Migration
{
    private const BASE = ['opening', 'vs_open', 'vs_3bet', 'vs_4bet', 'defense_bb', 'cold_call', 'squeeze'];

    private const HAND_COLUMNS = [
        'vs_open_calls',
        'squeeze_opportunities',
        'squeezes',
        'vs_4bet_opportunities',
        'vs_4bet_calls',
        'vs_4bet_raises',
        'vs_4bet_folds',
        'vs_limp_opportunities',
        'vs_limp_raises',
        'vs_limp_calls',
    ];

    private function postflopAcciones(bool $withPot): array
    {
        $out = [];
        foreach (['flop', 'turn', 'river'] as $s) {
            foreach (['agg', 'def'] as $r) {
                foreach (['ip', 'oop'] as $p) {
                    if (!$withPot) {
                        $out[] = "{$s}_{$r}_{$p}";
                        continue;
                    }
                    foreach (['limp', 'srp', '3bp', '4bp'] as $pot) {
                        $out[] = "{$s}_{$r}_{$p}_{$pot}";
                    }
                }
            }
        }

        return $out;
    }

    private function applyAcciones(array $values): void
    {
        $list = implode(', ', array_map(fn($v) => "'$v'::character varying", $values));
        DB::statement('ALTER TABLE bot_hud_stats DROP CONSTRAINT IF EXISTS bot_hud_stats_accion_check');
        DB::statement("ALTER TABLE bot_hud_stats ADD CONSTRAINT bot_hud_stats_accion_check CHECK ((accion)::text = ANY ((ARRAY[$list])::text[]))");
    }

    public function up(): void
    {
        Schema::table('bot_preflop_hand_stats', function (Blueprint $table) {
            foreach (self::HAND_COLUMNS as $column) {
                $table->bigInteger($column)->default(0);
            }
        });

        Schema::create('bot_preflop_vs_opener_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained()->cascadeOnDelete();
            $table->string('posicion', 3);      // posición de quien responde
            $table->string('vs_posicion', 3);   // posición de quien abrió
            $table->bigInteger('opportunities')->default(0); // veces que se enfrentó a esa apertura
            $table->bigInteger('three_bets')->default(0);
            $table->bigInteger('calls')->default(0);
            $table->bigInteger('folds')->default(0);
            $table->timestamps();
            $table->unique(['bot_id', 'posicion', 'vs_posicion']);
        });

        $this->applyAcciones(array_merge(self::BASE, $this->postflopAcciones(false), $this->postflopAcciones(true)));
    }

    public function down(): void
    {
        DB::table('bot_hud_stats')->whereIn('accion', $this->postflopAcciones(true))->delete();
        $this->applyAcciones(array_merge(self::BASE, $this->postflopAcciones(false)));

        Schema::dropIfExists('bot_preflop_vs_opener_stats');

        Schema::table('bot_preflop_hand_stats', function (Blueprint $table) {
            $table->dropColumn(self::HAND_COLUMNS);
        });
    }
};
