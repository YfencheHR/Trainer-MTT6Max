<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * bot_hud_stats guarda las estadísticas por tramo de stack como filas accion = 'stack' y rango_ciegas = '0-10' | '10-20' |
 * '20-40' | '40+'. La tabla restringe ambos campos con CHECK, así que se amplían (insertando los valores nuevos en la
 * lista que ya tiene cada restricción, para no depender de cómo se creara).
 */
return new class extends Migration
{
    private function extend(string $constraint, string $marker, string $inject): void
    {
        $row = DB::selectOne(
            "select pg_get_constraintdef(oid) as def from pg_constraint where conname = ? and conrelid = 'bot_hud_stats'::regclass",
            [$constraint]
        );
        if (! $row || str_contains($row->def, $marker)) {
            return;
        }

        $def = preg_replace('/ARRAY\[/', 'ARRAY[' . $inject, $row->def, 1);
        DB::statement("ALTER TABLE bot_hud_stats DROP CONSTRAINT {$constraint}");
        DB::statement("ALTER TABLE bot_hud_stats ADD CONSTRAINT {$constraint} {$def}");
    }

    public function up(): void
    {
        $this->extend('bot_hud_stats_accion_check', "'stack'", "('stack'::character varying)::text, ");
        $this->extend(
            'bot_hud_stats_rango_ciegas_check',
            "'0-10'",
            "'0-10'::character varying, '10-20'::character varying, '20-40'::character varying, '40+'::character varying, "
        );
    }

    public function down(): void
    {
        DB::table('bot_hud_stats')->where('accion', 'stack')->delete();
        // Las restricciones ampliadas se dejan: aceptan los valores antiguos y no estorban.
    }
};
