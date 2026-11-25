<?php

namespace App\Console\Commands;

use App\Models\Bot;
use App\Services\Ai\HandHistoryParser;
use App\Services\Bots\Brain\BrainAssembler;
use App\Services\Bots\Brain\BrainRunner;
use App\Services\Replay\RealHandContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Réplica de decisiones reales: cada jugada de un jugador real (con sus cartas conocidas: el héroe o quien llegó al showdown) se presenta al
 * bot del perfil Pool al que pertenece ese jugador, con el estado real de la mano, y se compara su decisión con lo que hizo la persona.
 *
 * Se compara la acción GRUESA (fold / check / call / apuesta o subida). No se espera que el bot juegue igual: se mide cuánto se parece.
 */
class BotsReplayCommand extends Command
{
    protected $signature = 'bots:replay {file : TSV id_hand<TAB>history (volcado de tourney_hand_histories)}
        {--limit=1500 : decisiones a comparar}
        {--streets=preflop,flop,turn,river : calles a incluir}
        {--max-players=6 : manos con más jugadores se ignoran (la app es 6-max)}
        {--without-texture : no aplicar los multiplicadores por textura del board (para comparar)}
        {--candidates=24 : manos candidatas por jugador cuando no se conocen sus cartas (0 = solo decisiones con cartas conocidas)}
        {--out=ai/replay.json : resultado detallado (dentro de storage/app)}';

    protected $description = 'Compara las decisiones de los bots Pool con las que tomaron los jugadores reales a los que representan';

    private const CLASSES = ['fold', 'check', 'call', 'aggr'];

    public function handle(HandHistoryParser $parser, BrainRunner $runner, BrainAssembler $assembler): int
    {
        $byName = $this->botsByPlayerName();
        if (! $byName) {
            $this->error('No hay jugadores asignados a perfiles Pool: importa primero un historial de PokerTracker.');

            return self::FAILURE;
        }
        $this->info(count($byName).' jugadores reales asignados a un perfil Pool');
        $builder = new RealHandContext($byName);
        $streets = array_filter(explode(',', (string) $this->option('streets')));
        $limit = (int) $this->option('limit');
        $maxPlayers = (int) $this->option('max-players');
        $candidates = max(0, (int) $this->option('candidates'));
        \App\Services\Bots\Brain\BoardTextureStats::$disabled = (bool) $this->option('without-texture');

        $fh = fopen($this->argument('file'), 'r');
        $rows = [];
        $errors = 0;
        $brains = [];
        $seed = 1;
        while (($line = fgets($fh)) !== false && count($rows) < $limit) {
            [, $text] = array_pad(explode("\t", rtrim($line, "\n"), 2), 2, '');
            $hand = $parser->parse($text);
            if (! $hand || count($hand['players']) > $maxPlayers) {
                continue;
            }
            foreach (array_unique(array_column($hand['actions'], 'player')) as $name) {
                if (! isset($byName[$name]) || ! $this->touches($hand, $name, $streets)) {
                    continue;
                }
                try {
                    $got = $this->replayPlayer($hand, $name, $byName[$name], $builder, $runner, $brains, $assembler, $streets, $candidates, $seed);
                } catch (Throwable $e) {
                    $errors++;
                    if ($errors <= 3) {
                        $this->warn('Error: '.$e->getMessage().' @ '.basename($e->getFile()).':'.$e->getLine());
                    }

                    continue;
                }
                foreach ($got as $row) {
                    $rows[] = $row;
                }
                if (count($rows) >= $limit) {
                    break;
                }
            }
        }
        fclose($fh);

        if (! $rows) {
            $this->error('Sin decisiones comparables ('.$errors.' errores).');

            return self::FAILURE;
        }
        $this->summary($rows, $errors);
        file_put_contents(storage_path('app/'.$this->option('out')), json_encode($rows, JSON_UNESCAPED_UNICODE));
        $this->line('Detalle: '.storage_path('app/'.$this->option('out')));

        return self::SUCCESS;
    }

    /** @return array<string, Bot> jugador real → bot de su perfil (el más reciente y con más manos si está en varios) */
    private function botsByPlayerName(): array
    {
        $rows = DB::table('bot_source_players as sp')->join('bots', 'bots.id', '=', 'sp.bot_id')
            ->whereNull('bots.archived_at')->where('bots.tipo', 'pool')
            ->orderBy('sp.hands')->get(['sp.player_name', 'sp.bot_id']);   // el último gana: más manos
        $bots = Bot::with('hudStats')->whereIn('id', $rows->pluck('bot_id')->unique())->get()->keyBy('id');
        $out = [];
        foreach ($rows as $r) {
            if (isset($bots[$r->bot_id])) {
                $out[$r->player_name] = $bots[$r->bot_id];
            }
        }

        return $out;
    }

    private function coarse(string $action): string
    {
        return in_array($action, ['fold', 'check', 'call'], true) ? $action : 'aggr';
    }

    /** Distribución del bot (en %) a clases gruesas. check_raise, trampa y slowplay son un check en esta decisión. */
    private function coarseDistribution(array $dist, bool $facing): array
    {
        $out = array_fill_keys(self::CLASSES, 0.0);
        $total = array_sum($dist) ?: 1;
        foreach ($dist as $action => $pct) {
            $class = in_array($action, ['check', 'check_raise', 'slowplay', 'trap'], true) ? 'check' : $this->coarse($action);
            // Sin apuesta que igualar el motor traduce fold y call a check; ante una apuesta no se puede pasar: pasar es igualar
            if (! $facing && in_array($class, ['fold', 'call'], true)) {
                $class = 'check';
            } elseif ($facing && $class === 'check') {
                $class = 'call';
            }
            $out[$class] += $pct / $total;
        }

        return $out;
    }

    /** ¿Tiene este jugador alguna acción en las calles pedidas? */
    private function touches(array $hand, string $name, array $streets): bool
    {
        foreach ($hand['actions'] as $a) {
            if ($a['player'] === $name && in_array($a['street'], $streets, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Repite todas las decisiones de un jugador en una mano. Con sus cartas conocidas se le pregunta al bot con ellas; si no se conocen, con
     * unas manos candidatas ponderadas por lo plausibles que son dadas sus jugadas anteriores (lo que el bot habría hecho con cada una):
     * la predicción de cada decisión es el promedio ponderado ANTES de saber lo que hizo, y después se actualizan los pesos con la jugada real.
     *
     * @return array<int, array> una fila por decisión en las calles pedidas
     */
    private function replayPlayer(array $hand, string $name, Bot $bot, RealHandContext $builder, BrainRunner $runner, array &$brains, BrainAssembler $assembler, array $streets, int $candidates, int &$seed): array
    {
        $brain = $brains[$bot->id] ??= $assembler->forBot($bot);
        $player = null;
        foreach ($hand['players'] as $p) {
            if ($p['name'] === $name) {
                $player = $p;
            }
        }
        $known = $player['hole_cards'] ?? null;
        if ($known) {
            $cands = [$known];
        } elseif ($candidates > 0) {
            $cands = $this->randomHands($hand, $name, $candidates, $seed);
        } else {
            return [];
        }
        $weights = array_fill(0, count($cands), 1 / count($cands));
        $rows = [];
        foreach ($hand['actions'] as $i => $a) {
            if ($a['player'] !== $name) {
                continue;
            }
            $facing = $a['to_call'] > 0;
            $dists = [];
            foreach ($cands as $k => $cards) {
                $ctx = $builder->build($hand, $i, $cards);
                if (! $ctx) {
                    $dists[$k] = null;

                    continue;
                }
                $result = $runner->decide($ctx, $brain, $seed++);
                $dists[$k] = $this->coarseDistribution($result['trace']['distribution'] ?? [$result['action'] => 100], $facing);
            }
            $real = $this->coarse($a['action']);
            $pred = array_fill_keys(self::CLASSES, 0.0);
            $total = 0.0;
            foreach ($dists as $k => $d) {
                if ($d) {
                    $total += $weights[$k];
                    foreach ($d as $class => $p) {
                        $pred[$class] += $weights[$k] * $p;
                    }
                }
            }
            if ($total <= 0) {
                continue;
            }
            $pred = array_map(fn ($p) => $p / $total, $pred);
            if (in_array($a['street'], $streets, true)) {
                $ess = 1 / max(1e-9, array_sum(array_map(fn ($w) => $w * $w, $weights)));
                $rows[] = [
                    'street' => $a['street'], 'spot' => $facing ? 'ante apuesta' : 'primero en hablar', 'pot' => $this->potType($hand, $i),
                    'real' => $real, 'dist' => $pred, 'bot' => $bot->nombre, 'player' => $name, 'known' => (bool) $known, 'ess' => round($ess, 1),
                    'stack_bb' => round($a['stack_before'] / max(1, (int) $hand['big_blind']), 1),
                    'texture' => \App\Services\Bots\Brain\BoardTexture::classify($a['board'] ?? []),
                    'hand' => $known ? implode(' ', $known).' | '.implode(' ', $a['board'] ?? []) : null,
                ];
            }
            // La jugada real es evidencia: las manos con las que el bot la habría hecho ganan peso
            foreach ($dists as $k => $d) {
                $weights[$k] *= $d ? max($d[$real], 0.02) : 0.02;
            }
            $sum = array_sum($weights);
            $weights = $sum > 0 ? array_map(fn ($w) => $w / $sum, $weights) : array_fill(0, count($cands), 1 / count($cands));
        }

        return $rows;
    }

    /** Manos de dos cartas al azar que no choquen con el board ni con las cartas conocidas de la mano. @return array<int, array<int,string>> */
    private function randomHands(array $hand, string $name, int $count, int $seed): array
    {
        $dead = array_map(fn ($c) => (string) $c, $hand['board'] ?? []);
        foreach ($hand['players'] as $p) {
            if ($p['name'] !== $name && ! empty($p['hole_cards'])) {
                $dead = array_merge($dead, $p['hole_cards']);
            }
        }
        $deck = [];
        foreach (str_split('AKQJT98765432') as $rank) {
            foreach (['♠', '♥', '♦', '♣'] as $suit) {
                if (! in_array($rank.$suit, $dead, true)) {
                    $deck[] = $rank.$suit;
                }
            }
        }
        mt_srand(crc32($hand['hand_id'].$name) ^ $seed);
        $out = [];
        $seen = [];
        $guard = 0;
        while (count($out) < $count && $guard++ < $count * 20) {
            $pair = [$deck[mt_rand(0, count($deck) - 1)], $deck[mt_rand(0, count($deck) - 1)]];
            sort($pair);
            if ($pair[0] === $pair[1] || isset($seen[$pair[0].$pair[1]])) {
                continue;
            }
            $seen[$pair[0].$pair[1]] = true;
            $out[] = $pair;
        }

        return $out;
    }

    private function subset(string $name, array $rows, bool $known): ?array
    {
        $part = array_values(array_filter($rows, fn ($r) => $r['known'] === $known));

        return $part ? $this->line_($name, $part) : null;
    }

    /** limpeado / subida simple / 3-bet o más, según las subidas preflop anteriores a esta acción. */
    private function potType(array $hand, int $index): string
    {
        if ($hand['actions'][$index]['street'] === 'preflop') {
            // Qué se encuentra el jugador preflop: nadie delante, limpers, una apertura o un 3-bet o más
            $raises = 0;
            $limpers = 0;
            foreach (array_slice($hand['actions'], 0, $index) as $prev) {
                if ($prev['street'] !== 'preflop') {
                    continue;
                }
                if (in_array($prev['action'], ['raise', 'bet'], true)) {
                    $raises++;
                } elseif ($prev['action'] === 'call' && $raises === 0) {
                    $limpers++;
                }
            }

            return $raises >= 2 ? 'ante 3-bet o más' : ($raises === 1 ? 'ante una apertura' : ($limpers > 0 ? 'ante limpers' : 'abrir'));
        }
        $raises = 0;
        foreach ($hand['actions'] as $a) {
            if ($a['street'] === 'preflop' && in_array($a['action'], ['raise', 'bet'], true)) {
                $raises++;
            }
        }

        return $raises === 0 ? 'limpeado' : ($raises === 1 ? 'subida simple' : '3-bet o más');
    }

    private function summary(array $rows, int $errors): void
    {
        $this->newLine();
        $this->line('Decisiones comparadas: '.count($rows).($errors ? " ({$errors} con error, ignoradas)" : ''));
        $this->table(['Grupo', 'n', 'Acierta la más probable', 'Prob. media de lo real', 'Pérdida log', 'Siempre lo más común'], array_map(
            fn ($g) => $g,
            array_merge(
                [$this->line_('TODAS', $rows)],
                array_filter([
                    $this->subset('  con cartas conocidas', $rows, true),
                    $this->subset('  con cartas muestreadas', $rows, false),
                ]),
                $this->groups($rows, fn ($r) => $r['street'], 'calle'),
                $this->groups($rows, fn ($r) => $r['street'].' · '.$r['spot'].' · '.$r['pot'], ''),
            )
        ));
    }

    private function groups(array $rows, \Closure $key, string $label): array
    {
        $by = [];
        foreach ($rows as $r) {
            $by[$key($r)][] = $r;
        }
        $order = ['preflop' => 0, 'flop' => 1, 'turn' => 2, 'river' => 3];
        uksort($by, fn ($a, $b) => ($order[explode(' ', $a)[0]] ?? 9) <=> ($order[explode(' ', $b)[0]] ?? 9) ?: strcmp($a, $b));

        return array_values(array_filter(array_map(fn ($k) => count($by[$k]) >= 15 ? $this->line_(($label ? $label.' ' : '').$k, $by[$k]) : null, array_keys($by))));
    }

    private function line_(string $name, array $rows): array
    {
        $n = count($rows);
        $hit = 0;
        $prob = 0.0;
        $loss = 0.0;
        $count = array_fill_keys(self::CLASSES, 0);
        foreach ($rows as $r) {
            arsort($r['dist']);
            $hit += array_key_first($r['dist']) === $r['real'] ? 1 : 0;
            $p = $r['dist'][$r['real']];
            $prob += $p;
            $loss += -log(max($p, 0.02));
            $count[$r['real']]++;
        }

        return [$name, $n, round(100 * $hit / $n).' %', round(100 * $prob / $n).' %', round($loss / $n, 3), round(100 * max($count) / $n).' %'];
    }
}
