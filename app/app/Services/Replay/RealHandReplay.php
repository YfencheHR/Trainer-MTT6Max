<?php

namespace App\Services\Replay;

use App\Models\Bot;
use App\Services\Ai\HandHistoryParser;
use App\Services\Bots\Brain\BoardTexture;
use App\Services\Bots\Brain\BrainAssembler;
use App\Services\Bots\Brain\BrainRunner;
use Illuminate\Support\Facades\DB;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Throwable;

/**
 * Réplica de decisiones reales: cada jugada de un jugador real que pertenece a un perfil Pool se presenta al bot de ese perfil con el estado
 * real de la mano (RealHandContext), y se compara su decisión con lo que hizo la persona. Se compara la acción GRUESA (fold / check / call /
 * apuesta o subida): no se espera que el bot juegue igual, se mide cuánto se parece.
 *
 * Un historial solo trae las cartas del héroe y de quien llega al showdown, y quien llega al showdown nunca foldea. Para no sesgar, cuando no se
 * conocen las cartas se muestrean manos candidatas y se ponderan por lo plausibles que son dadas las jugadas anteriores del jugador (lo que el bot
 * habría hecho con cada una): la predicción de cada decisión es el promedio ponderado ANTES de saber lo que hizo.
 */
class RealHandReplay
{
    public const CLASSES = ['fold', 'check', 'call', 'aggr'];

    public function __construct(private HandHistoryParser $parser, private BrainRunner $runner, private BrainAssembler $assembler)
    {
    }

    /** @return array<string, Bot> jugador real → bot de su perfil vivo (con más manos si está en varios) */
    public function botsByPlayerName(): array
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

    /**
     * @param  iterable<string>  $hands    texto de cada mano (PokerStars / Winamax)
     * @param  array<string, Bot>  $byName  jugador real → bot de su perfil
     * @param  array{limit?: int, streets?: array<int, string>, max_players?: int, marginal?: bool, candidates?: int, every?: int}  $options
     *         every: 1 de cada N manos (por hash de la mano), para no quedarse con el principio del archivo
     * @return array{rows: array<int, array>, errors: int, first_error: ?string}
     */
    public function run(iterable $hands, array $byName, array $options = []): array
    {
        $limit = (int) ($options['limit'] ?? 1500);
        $streets = $options['streets'] ?? ['preflop', 'flop', 'turn', 'river'];
        $maxPlayers = (int) ($options['max_players'] ?? 6);
        $marginal = (bool) ($options['marginal'] ?? false);
        $candidates = max(0, (int) ($options['candidates'] ?? 24));
        $every = max(1, (int) ($options['every'] ?? 1));
        $builder = new RealHandContext($byName);
        $rows = [];
        $errors = 0;
        $firstError = null;
        $brains = [];
        $seed = 1;

        foreach ($hands as $text) {
            if (count($rows) >= $limit) {
                break;
            }
            if ($every > 1 && crc32($text) % $every !== 0) {
                continue;
            }
            $hand = $this->parser->parse($text);
            if (! $hand || count($hand['players']) > $maxPlayers) {
                continue;
            }
            foreach (array_unique(array_column($hand['actions'], 'player')) as $name) {
                if (! isset($byName[$name]) || ! $this->touches($hand, $name, $streets)) {
                    continue;
                }
                try {
                    $got = $this->replayPlayer($hand, $name, $byName[$name], $builder, $brains, $streets, $candidates, $marginal, $seed);
                } catch (Throwable $e) {
                    $errors++;
                    $firstError ??= $e->getMessage().' @ '.basename($e->getFile()).':'.$e->getLine();

                    continue;
                }
                array_push($rows, ...$got);
                if (count($rows) >= $limit) {
                    break;
                }
            }
        }

        return ['rows' => $rows, 'errors' => $errors, 'first_error' => $firstError];
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

    /** @return array<int, array> una fila por decisión del jugador en las calles pedidas */
    private function replayPlayer(array $hand, string $name, Bot $bot, RealHandContext $builder, array &$brains, array $streets, int $candidates, bool $marginal, int &$seed): array
    {
        $brain = $brains[$bot->id] ??= $this->assembler->forBot($bot);
        $player = null;
        foreach ($hand['players'] as $p) {
            if ($p['name'] === $name) {
                $player = $p;
            }
        }
        $known = $marginal ? null : ($player['hole_cards'] ?? null);
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
            $scenarios = [];
            $sources = [];
            $asked = [];
            $size = null;
            foreach ($cands as $k => $cards) {
                $ctx = $builder->build($hand, $i, $cards);
                if (! $ctx) {
                    $dists[$k] = null;

                    continue;
                }
                $result = $this->runner->decide($ctx, $brain, $seed++);
                $dists[$k] = $this->coarseDistribution($result['trace']['distribution'] ?? [$result['action'] => 100], $facing);
                $scenarios[] = $result['trace']['preflop_strategy']['scenario'] ?? null;
                $features = $result['trace']['features'] ?? [];
                $sources[] = $features['stats_source'] ?? null;
                $asked[$k] = isset($features['own_f2bet']) ? (float) $features['own_f2bet'] : null;   // lo que PIDE la estadística del perfil ante una apuesta
                $size ??= $features['bet_vs_pot'] ?? null;
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
            $pred = array_map(fn($p) => $p / $total, $pred);
            if (in_array($a['street'], $streets, true)) {
                $ess = 1 / max(1e-9, array_sum(array_map(fn($w) => $w * $w, $weights)));
                $rows[] = [
                    'street' => $a['street'], 'spot' => $facing ? 'ante apuesta' : 'primero en hablar', 'pot' => $this->potType($hand, $i),
                    'real' => $real, 'dist' => $pred, 'bot' => $bot->nombre, 'player' => $name, 'known' => (bool) $known, 'ess' => round($ess, 1),
                    'stack_bb' => round($a['stack_before'] / max(1, (int) $hand['big_blind']), 1),
                    'position' => $a['position'] ?? null,
                    'texture' => BoardTexture::classify($a['board'] ?? []),
                    'scenario' => $this->mostFrequent($scenarios),
                    'source' => $this->mostFrequent($sources),
                    'asked_fold' => ($askedValues = array_filter($asked, fn($v) => $v !== null)) ? round(array_sum($askedValues) / count($askedValues), 1) : null,
                    'bet_vs_pot' => $size,
                    'hand' => $known ? implode(' ', $known).' | '.implode(' ', $a['board'] ?? []) : null,
                ];
            }
            // La jugada real es evidencia: las manos con las que el bot la habría hecho ganan peso
            foreach ($dists as $k => $d) {
                $weights[$k] *= $d ? max($d[$real], 0.02) : 0.02;
            }
            $sum = array_sum($weights);
            $weights = $sum > 0 ? array_map(fn($w) => $w / $sum, $weights) : array_fill(0, count($cands), 1 / count($cands));
        }

        return $rows;
    }

    /** El valor más repetido (null si no hay ninguno). */
    private function mostFrequent(array $values): ?string
    {
        $counts = array_count_values(array_filter($values));
        if (! $counts) {
            return null;
        }
        arsort($counts);

        return (string) array_key_first($counts);
    }

    /** Manos de dos cartas al azar que no choquen con el board ni con las cartas conocidas de la mano. @return array<int, array<int, string>> */
    private function randomHands(array $hand, string $name, int $count, int $seed): array
    {
        $dead = array_map(fn($c) => (string) $c, $hand['board'] ?? []);
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
        // Generador propio: no se toca el estado global del proceso (mt_srand)
        $rng = new Randomizer(new Mt19937(crc32($hand['hand_id'].$name) ^ $seed));
        $out = [];
        $seen = [];
        $guard = 0;
        while (count($out) < $count && $guard++ < $count * 20) {
            $pair = [$deck[$rng->getInt(0, count($deck) - 1)], $deck[$rng->getInt(0, count($deck) - 1)]];
            sort($pair);
            if ($pair[0] === $pair[1] || isset($seen[$pair[0].$pair[1]])) {
                continue;
            }
            $seen[$pair[0].$pair[1]] = true;
            $out[] = $pair;
        }

        return $out;
    }

    /** Qué se encuentra el jugador: preflop (nadie delante, limpers, una apertura, 3-bet o más) o el tipo de bote preflop. */
    private function potType(array $hand, int $index): string
    {
        if ($hand['actions'][$index]['street'] === 'preflop') {
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

    /**
     * Resumen por grupos (todas, con cartas conocidas / muestreadas, por calle y por situación). Con menos de 15 decisiones un grupo no se muestra.
     * `ci` es el intervalo de confianza al 95 % del acierto, en puntos (aproximación normal; las decisiones de una misma mano están correlacionadas,
     * así que es un mínimo).
     *
     * @return array<int, array{name: string, n: int, accuracy: float, ci: float, probability: float, loss: float, baseline: float}>
     */
    public static function summarize(array $rows): array
    {
        if (! $rows) {
            return [];
        }
        $known = fn(bool $k) => array_values(array_filter($rows, fn($r) => $r['known'] === $k));
        $lines = [self::line('TODAS', $rows)];
        foreach ([['  con cartas conocidas', $known(true)], ['  con cartas muestreadas', $known(false)]] as [$name, $part]) {
            if ($part) {
                $lines[] = self::line($name, $part);
            }
        }

        return array_merge(
            $lines,
            self::groups($rows, fn($r) => $r['street'], 'calle '),
            self::groups($rows, fn($r) => $r['street'].' · '.$r['spot'].' · '.$r['pot'], ''),
        );
    }

    private static function groups(array $rows, \Closure $key, string $label): array
    {
        $by = [];
        foreach ($rows as $r) {
            $by[$key($r)][] = $r;
        }
        $order = ['preflop' => 0, 'flop' => 1, 'turn' => 2, 'river' => 3];
        uksort($by, fn($a, $b) => ($order[explode(' ', $a)[0]] ?? 9) <=> ($order[explode(' ', $b)[0]] ?? 9) ?: strcmp($a, $b));
        $out = [];
        foreach ($by as $name => $group) {
            if (count($group) >= 15) {
                $out[] = self::line($label.$name, $group);
            }
        }

        return $out;
    }

    private static function line(string $name, array $rows): array
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
        $accuracy = $hit / $n;

        return [
            'name' => $name, 'n' => $n, 'accuracy' => round(100 * $accuracy, 1), 'ci' => round(196 * sqrt($accuracy * (1 - $accuracy) / $n), 1),
            'probability' => round(100 * $prob / $n, 1), 'loss' => round($loss / $n, 3), 'baseline' => round(100 * max($count) / $n, 1),
        ];
    }
}
