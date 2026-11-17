<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El nombre de un bot era único en toda la tabla. Un perfil archivado conserva su nombre y una copia congelada tiene el
 * mismo nombre que su original, así que la unicidad pasa a exigirse solo entre bots ACTIVOS (no archivados).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE bots DROP CONSTRAINT IF EXISTS bots_nombre_unique');
        DB::statement('DROP INDEX IF EXISTS bots_nombre_unique');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS bots_nombre_active_unique ON bots (nombre) WHERE archived_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS bots_nombre_active_unique');
        DB::statement('ALTER TABLE bots ADD CONSTRAINT bots_nombre_unique UNIQUE (nombre)');
    }
};
