<?php

namespace App\Console\Commands;

use App\Services\Bots\Brain\BoardTextureStats;
use App\Services\Replay\RealHandReplay;
use Illuminate\Console\Command;

/**
 * Réplica de decisiones reales sobre un archivo de manos (ver RealHandReplay). La importación de PokerTracker ya la ejecuta sola al terminar
 * con una muestra; este comando sirve para repetirla con más manos o con otras opciones (desarrollo).
 */
class BotsReplayCommand extends Command
{
    protected $signature = 'bots:replay {file : TSV id_hand<TAB>history (volcado de tourney_hand_histories)}
        {--limit=1500 : decisiones a comparar}
        {--every=1 : usar 1 de cada N manos (por hash), para repartir la muestra por todo el archivo en lugar de quedarse con el principio}
        {--streets=preflop,flop,turn,river : calles a incluir}
        {--max-players=6 : manos con más jugadores se ignoran (la app es 6-max)}
        {--marginal : ignorar las cartas conocidas y tratar a TODOS con manos candidatas (calibración sin el sesgo del showdown: quien llega al showdown nunca foldea)}
        {--without-texture : no aplicar los multiplicadores por textura del board (para comparar)}
        {--candidates=24 : manos candidatas por jugador cuando no se conocen sus cartas (0 = solo decisiones con cartas conocidas)}
        {--out=ai/replay.json : resultado detallado (dentro de storage/app)}';

    protected $description = 'Compara las decisiones de los bots Pool con las que tomaron los jugadores reales a los que representan';

    public function handle(RealHandReplay $replay): int
    {
        $byName = $replay->botsByPlayerName();
        if (! $byName) {
            $this->error('No hay jugadores asignados a perfiles Pool: importa primero un historial de PokerTracker.');

            return self::FAILURE;
        }
        $this->info(count($byName).' jugadores reales asignados a un perfil Pool');
        BoardTextureStats::$disabled = (bool) $this->option('without-texture');

        $result = $replay->run($this->hands($this->argument('file')), $byName, [
            'limit' => (int) $this->option('limit'),
            'every' => (int) $this->option('every'),
            'streets' => array_filter(explode(',', (string) $this->option('streets'))),
            'max_players' => (int) $this->option('max-players'),
            'marginal' => (bool) $this->option('marginal'),
            'candidates' => (int) $this->option('candidates'),
        ]);
        if ($result['first_error']) {
            $this->warn('Error (en '.$result['errors'].' jugadores): '.$result['first_error']);
        }
        if (! $result['rows']) {
            $this->error('Sin decisiones comparables.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('Decisiones comparadas: '.count($result['rows']).($result['errors'] ? " ({$result['errors']} jugadores con error, ignorados)" : ''));
        $this->table(['Grupo', 'n', 'Acierta la más probable', '± (95 %)', 'Prob. media de lo real', 'Pérdida log', 'Siempre lo más común'], array_map(
            fn($l) => [$l['name'], $l['n'], $l['accuracy'].' %', $l['ci'], $l['probability'].' %', $l['loss'], $l['baseline'].' %'],
            RealHandReplay::summarize($result['rows'])
        ));
        file_put_contents(storage_path('app/'.$this->option('out')), json_encode($result['rows'], JSON_UNESCAPED_UNICODE));
        $this->line('Detalle: '.storage_path('app/'.$this->option('out')));

        return self::SUCCESS;
    }

    /** @return \Generator<int, string> el texto de cada mano del TSV */
    private function hands(string $file): \Generator
    {
        $fh = fopen($file, 'r');
        try {
            while (($line = fgets($fh)) !== false) {
                [, $text] = array_pad(explode("\t", rtrim($line, "\n"), 2), 2, '');
                yield $text;
            }
        } finally {
            fclose($fh);
        }
    }
}
