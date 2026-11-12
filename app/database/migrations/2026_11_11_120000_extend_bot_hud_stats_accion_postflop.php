<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Amplía los valores permitidos de bot_hud_stats.accion con las filas postflop por calle, papel e IP/OOP:
 * {flop|turn|river}_{agg|def}_{ip|oop}  (agg = agresor que apuesta · def = defensor ante esa apuesta).
 */
return new class extends Migration
{
    private const BASE = ['opening', 'vs_open', 'vs_3bet', 'vs_4bet', 'defense_bb', 'cold_call', 'squeeze'];

    private function postflop(): array
    {
        $out = [];
        foreach (['flop', 'turn', 'river'] as $s) {
            foreach (['agg', 'def'] as $r) {
                foreach (['ip', 'oop'] as $p) {
                    $out[] = "{$s}_{$r}_{$p}";
                }
            }
        }

        return $out;
    }

    private function apply(array $values): void
    {
        $list = implode(', ', array_map(fn($v) => "'$v'::character varying", $values));
        DB::statement('ALTER TABLE bot_hud_stats DROP CONSTRAINT IF EXISTS bot_hud_stats_accion_check');
        DB::statement("ALTER TABLE bot_hud_stats ADD CONSTRAINT bot_hud_stats_accion_check CHECK ((accion)::text = ANY ((ARRAY[$list])::text[]))");
    }

    public function up(): void
    {
        $this->apply(array_merge(self::BASE, $this->postflop()));
    }

    public function down(): void
    {
        DB::table('bot_hud_stats')->whereIn('accion', $this->postflop())->delete();
        $this->apply(self::BASE);
    }
};
