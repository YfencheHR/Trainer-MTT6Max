<?php

use App\Config\IconConfig;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Devuelve a cada rival el icono de su tipo (setminer ⛏️, roca 🪨, nit 🤐, OMC 👴, TAG 🎯, LAG 🚀, calling station 📞,
 * maníaco 🤪, ballena 🐋…). Solo rellena los que no tienen: nunca pisa un icono elegido en el editor.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('bots')->where(fn($q) => $q->whereNull('icono')->orWhere('icono', ''))->get(['id', 'tipo', 'perfil']) as $bot) {
            DB::table('bots')->where('id', $bot->id)->update(['icono' => IconConfig::forTipo($bot->tipo, $bot->perfil)]);
        }
    }

    public function down(): void
    {
        // No se deshace: un icono asignado no es un dato que haya que borrar
    }
};
