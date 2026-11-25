<?php

namespace App\Services\Bots;

use App\Models\Bot;
use App\Models\BotPreflopHandRange;
use App\Services\Bots\Brain\BotProfile;
use App\Services\Bots\Brain\PreflopRanking;

class RivalPreflopTables
{
    private const RANKS = 'AKQJT98765432';
    private const DB_POSITION = ['HJ' => 'MP'];
    private const PRIOR_OPPORTUNITIES = 12;
    /** Manos que nunca se foldean (mismas que audita bots:audit-ranges como error). */
    private const PREMIUM = ['AA', 'KK', 'QQ', 'AKs', 'AKo'];

    /** Sin estadística de PokerTracker: se continúa contra un 3-bet con esta fracción (la superior) de su rango de defensa. */
    private const COLD_CONTINUE_FALLBACK = 0.30;

    /** @var array<string, float> */
    private array $pushShareCache = [];

    private HandTypeTilt $tilt;

    public function __construct(private PreflopRanking $ranking, ?HandTypeTilt $tilt = null)
    {
        $this->tilt = $tilt ?? new HandTypeTilt();
    }

    public function hasHand(string $hand): bool
    {
        return in_array($hand, $this->hands(), true);
    }

    /**
     * Frecuencias de una mano. Con $bucket (tramo de stack) se usa la tabla de ese tramo; si no existe, o si la mano de la
     * tabla general fue editada a mano, manda la tabla general (lo editado a mano vale a cualquier stack).
     */
    public function frequenciesFor(Bot $bot, string $position, string $hand, ?string $bucket = null): ?array
    {
        $range = $bot->preflopHandRanges()
            ->where('position', $position)
            ->where('hand', $hand)
            ->first();

        if (! $range || $range->cold3_raise === null) { // sin generar (tabla anterior a la respuesta en frío): se regenera
            $this->forBot($bot);
            $range = $bot->preflopHandRanges()
                ->where('position', $position)
                ->where('hand', $hand)
                ->first();
        }

        if (! $range) {
            return null;
        }

        if ($bucket && $bucket !== 'all' && ! $range->is_manual) {
            $range = BotPreflopHandRange::where([
                'bot_id' => $bot->id, 'stack_bucket' => $bucket, 'position' => $position, 'hand' => $hand,
            ])->first() ?? $range;
        }

        return [
            'attack' => [
                'open' => $range->attack_open,
                'limp' => $range->attack_limp,
                'open_response' => [
                    'reraise' => $range->open_reraise,
                    'call' => $range->open_call,
                    'fold' => $range->open_fold,
                ],
                'limp_response' => [
                    'raise' => $range->limp_raise,
                    'push' => $range->limp_push,
                ],
            ],
            'defense' => [
                'three_bet' => $range->defense_three_bet,
                'call' => $range->defense_call,
                'fold' => $range->defense_fold,
            ],
            'cold' => [
                'vs_3bet' => ['raise' => (float) $range->cold3_raise, 'call' => (float) $range->cold3_call],
                'vs_4bet' => ['raise' => (float) $range->cold4_raise, 'call' => (float) $range->cold4_call],
            ],
        ];
    }

    /** Peso (en «oportunidades») de lo que hace la población en el suavizado de lo que hace un perfil con limpers delante. */
    private const ISO_PRIOR = 20.0;

    /** Anchura (en puntos de percentil) de la transición entre subir y no subir. */
    private const ISO_WIDTH = 6.0;

    /** @var array<int, array<string, array>> */
    private array $isoCache = [];

    /** @var array<string, array>|null */
    private ?array $isoPopulation = null;

    /**
     * Con limpers delante y nadie que haya subido: [% que sube (aísla), % que iguala] con ESTA mano, según lo que el perfil hace en esa posición
     * (PokerTracker: bot_iso_stats), suavizado hacia lo que hace la población con su muestra. Las mejores manos son las que suben. Desde la ciega
     * grande no hay fold ni call: lo que no sube pasa (la acción «call» del bot es el check). Null si no hay ningún dato de esa posición.
     *
     * @return array{raise: float, call: float}|null
     */
    public function isoFrequencies(Bot $bot, string $position, string $hand): ?array
    {
        $db = self::DB_POSITION[$position] ?? $position;
        $own = ($this->isoCache[$bot->id] ??= \Illuminate\Support\Facades\DB::table('bot_iso_stats')->where('bot_id', $bot->id)->get()->keyBy('position')->map(fn($r) => (array) $r)->all())[$db] ?? null;
        $pop = ($this->isoPopulation ??= \Illuminate\Support\Facades\DB::table('bot_iso_stats')
            ->selectRaw('position, sum(opportunities) as opportunities, sum(raises) as raises, sum(calls) as calls')->groupBy('position')->get()
            ->keyBy('position')->map(fn($r) => (array) $r)->all())[$db] ?? null;
        if ((! $own || $own['opportunities'] <= 0) && (! $pop || $pop['opportunities'] <= 0)) {
            return null;
        }
        $rate = function (string $column) use ($own, $pop): float {
            $base = $pop && $pop['opportunities'] > 0 ? $pop[$column] / $pop['opportunities'] : 0.0;
            if (! $own || $own['opportunities'] <= 0) {
                return $base;
            }

            return ($own[$column] + self::ISO_PRIOR * $base) / ($own['opportunities'] + self::ISO_PRIOR);
        };
        $raiseTarget = 100 * $rate('raises');
        $callTarget = $position === 'BB' ? 0.0 : 100 * $rate('calls');
        $percentile = $this->ranking->percentileForCode($hand);   // 0 = la mejor mano
        $ramp = fn(float $target): float => 100 * max(0.0, min(1.0, ($target - $percentile) / (2 * self::ISO_WIDTH) + 0.5));
        $raise = $ramp($raiseTarget);
        $call = $position === 'BB' ? 100.0 - $raise : max(0.0, min(100.0 - $raise, $ramp($raiseTarget + $callTarget) - $raise));

        return ['raise' => round($raise, 2), 'call' => round($call, 2)];
    }

    /**
     * Respuesta de quien NO abrió y se encuentra un 3-bet (2 subidas) o un 4-bet o más: la generada y guardada en su tabla.
     *
     * @return array{raise: float, call: float, fold: float} frecuencias en %
     */
    public function coldVsReraise(Bot $bot, string $position, string $hand, int $raises, ?string $bucket = null): array
    {
        $cold = $this->frequenciesFor($bot, $position, $hand, $bucket)['cold'][$raises >= 3 ? 'vs_4bet' : 'vs_3bet'] ?? null;
        if (! $cold) {
            return ['raise' => 0.0, 'call' => 0.0, 'fold' => 100.0];
        }

        return ['raise' => $cold['raise'], 'call' => $cold['call'], 'fold' => max(0.0, 100.0 - $cold['raise'] - $cold['call'])];
    }

    /**
     * Genera las tablas del bot y guarda las filas no manuales: la general ('all', la que se edita y devuelve este método) y
     * una por tramo de stack para los que el perfil tiene estadísticas (BotProfile::STACK_BUCKETS).
     */
    public function forBot(Bot $bot): array
    {
        $all = $this->generateAll($bot);
        $general = $this->persistGeneratedRanges($bot, $all['all'], $this->storedRanges($bot, 'all'), 'all');
        foreach (BotProfile::STACK_BUCKETS as $bucket => $_) {
            if (isset($all[$bucket])) {
                $this->persistGeneratedRanges($bot, $all[$bucket], $this->storedRanges($bot, $bucket), $bucket);
            }
        }

        return $general;
    }

    private function storedRanges(Bot $bot, string $bucket)
    {
        return BotPreflopHandRange::where(['bot_id' => $bot->id, 'stack_bucket' => $bucket])
            ->get()
            ->keyBy(fn($row) => $row->position . ':' . $row->hand);
    }

    /**
     * Genera las tablas SIN guardarlas. Con $tilt = true (por defecto) el orden de las manos se ajusta al estilo del
     * rival según las manos que enseñó en showdown (HandTypeTilt); con false se usa el ranking genérico (línea base).
     * Con $bucket se generan las de ese tramo de stack (null = la tabla general del perfil).
     */
    public function generate(Bot $bot, bool $tilt = true, ?string $bucket = null): array
    {
        $profile = BotProfile::forBot($bot);
        $handStats = $this->handStats($bot);

        $tables = $this->build($bot, $profile, $handStats, []);
        $shifts = $tilt ? $this->tilt->learn($handStats->values(), $tables) : [];
        if ($bucket !== null) {
            return $this->bucketTables($bot, $profile, $handStats, $shifts, $bucket) ?? $tables;
        }

        return $shifts ? $this->build($bot, $profile, $handStats, $shifts) : $tables;
    }

    /**
     * La tabla general y la de cada tramo de stack con estadísticas, calculando una sola vez la inclinación por tipo de mano.
     *
     * @return array<string, array> 'all' => tablas, y tramo => tablas
     */
    private function generateAll(Bot $bot): array
    {
        $profile = BotProfile::forBot($bot);
        $handStats = $this->handStats($bot);

        $base = $this->build($bot, $profile, $handStats, []);
        $shifts = $this->tilt->learn($handStats->values(), $base);
        $out = ['all' => $shifts ? $this->build($bot, $profile, $handStats, $shifts) : $base];
        foreach (array_keys(BotProfile::STACK_BUCKETS) as $bucket) {
            $tables = $this->bucketTables($bot, $profile, $handStats, $shifts, $bucket);
            if ($tables !== null) {
                $out[$bucket] = $tables;
            }
        }

        return $out;
    }

    private function handStats(Bot $bot)
    {
        return $bot->preflopHandStats()
            ->get()
            ->keyBy(fn($row) => $row->posicion . ':' . $row->mano);
    }

    /**
     * Tablas de un tramo de stack: el mismo generador, con los porcentajes objetivo escalados por lo que cambia cada
     * estadística en ese tramo respecto al total del perfil (las de PokerTracker, ya suavizadas hacia la tendencia general).
     * Las frecuencias observadas por mano no se separan por stack, así que solo se mezclan en el tramo de stacks profundos.
     * Devuelve null si el perfil no tiene estadísticas de ese tramo.
     */
    private function bucketTables(Bot $bot, array $profile, $handStats, array $shifts, string $bucket): ?array
    {
        $general = $bot->generalStats();
        $stack = $bot->hudStats()->where('accion', BotProfile::STACK_ACTION)->where('rango_ciegas', $bucket)->first();
        if (! $general || ! $stack) {
            return null;
        }

        return $this->build($bot, $this->scaleProfile($profile, $general, $stack), $bucket === '40+' ? $handStats : collect(), $shifts);
    }

    /** Escala los objetivos del perfil por posición con el cociente tramo / total de cada estadística. */
    private function scaleProfile(array $profile, $general, $stack): array
    {
        // Objetivo de una estadística en el tramo: el valor por posición del perfil por el cociente tramo / total. Si el total es 0 no
        // hay nada que escalar (0 x lo que sea = 0): se fija con el valor propio del tramo, repartido por posición con $positional.
        $target = function (string $stat, float $current, float $positional = 1.0) use ($general, $stack): float {
            $overall = $general->{$stat};
            $bucket = $stack->{$stat};
            if ($bucket === null) {
                return $current;
            }
            if ($overall === null || (float) $overall <= 0) {
                return (float) $bucket * $positional;
            }

            return $current * max(0.2, min(5.0, (float) $bucket / (float) $overall));
        };
        $cap = fn(float $value, float $max = 100.0) => max(0.0, min($max, $value));

        foreach ($profile['positions'] as $position => &$p) {
            $p['open'] = $cap($target('rfi', $p['open']));
            $p['limp'] = $cap($target('limp', $p['limp']));
            $p['vs_open']['three_bet'] = $cap($target('three_bet', $p['vs_open']['three_bet'], BotProfile::THREE_F[$position]));
            // En las ciegas se defiende pagando según lo que se juega (VPIP), no con el "call en frío"
            $p['vs_open']['call'] = $cap($target(in_array($position, ['SB', 'BB'], true) ? 'vpip' : 'cold_call', $p['vs_open']['call']));

            $fold = $cap($target('fold_to_3bet', $p['vs_3bet']['fold']), 90.0);
            $reraise = $cap($target('four_bet', $p['vs_3bet']['reraise']));
            if ($fold + $reraise > 100.0) {
                $reraise = 100.0 - $fold;
            }
            $p['vs_3bet'] = ['fold' => $fold, 'reraise' => $reraise, 'call' => 100.0 - $fold - $reraise] + $p['vs_3bet'];

            foreach (['raise' => 'limp_raise', 'push' => 'limp_push'] as $key => $stat) {
                if ($p['limp_response'][$key] !== null) {
                    $p['limp_response'][$key] = $cap($target($stat, $p['limp_response'][$key]));
                }
            }
        }
        unset($p);

        foreach (['vpip', 'pfr', 'fold_to_4bet', 'cold3_fold', 'cold3_call', 'cold3_raise'] as $stat) {
            if ($stack->{$stat} !== null) {
                $profile['global'][$stat] = (float) $stack->{$stat};
            }
        }

        return $profile;
    }

    /**
     * Qué parte de las acciones son all-in con stack corto (hasta 20 bb), según el shove del propio perfil en ese tramo.
     *  - raise: parte de las SUBIDAS que son all-in = shove / PFR (máx. 1).
     *  - limp:  el shove que no se explica con las subidas se reparte entre el resto de manos con las que entra (VPIP - PFR),
     *           para el perfil que casi no sube pero va all-in (pagar un all-in, limp-shove).
     * En stacks más profundos no hay push/fold: 0.
     *
     * @return array{raise: float, limp: float}
     */
    public function pushShares(Bot $bot, ?string $bucket): array
    {
        $none = ['raise' => 0.0, 'limp' => 0.0];
        if (! in_array($bucket, ['0-10', '10-20'], true)) {
            return $none;
        }
        $key = $bot->id . ':' . $bucket;
        if (! isset($this->pushShareCache[$key])) {
            $stack = $bot->hudStats()->where('accion', BotProfile::STACK_ACTION)->where('rango_ciegas', $bucket)->first();
            if (! $stack || $stack->shove === null) {
                return $this->pushShareCache[$key] = $none;
            }
            $shove = (float) $stack->shove;
            $pfr = (float) ($stack->pfr ?? 0);
            $raise = $pfr > 0 ? min(1.0, $shove / $pfr) : 0.0;
            $entries = max(0.0, (float) ($stack->vpip ?? 0) - $pfr);
            $limp = $entries > 0 ? max(0.0, min(1.0, ($shove - $pfr * $raise) / $entries)) : 0.0;
            $this->pushShareCache[$key] = ['raise' => $raise, 'limp' => $limp];
        }

        return $this->pushShareCache[$key];
    }

    /** Desplazamiento de percentil por clase de mano que se aprende de las manos mostradas del bot (vacío = sin muestra). */
    public function tiltFor(Bot $bot): array
    {
        return $this->tilt->learn($bot->preflopHandStats()->get(), $this->generate($bot, false));
    }

    /** @param array<string, float> $classShifts desplazamiento de percentil por clase de mano (vacío = ranking genérico) */
    private function build(Bot $bot, array $profile, $handStats, array $classShifts): array
    {
        $tables = [];

        foreach (BotProfile::POSITIONS as $position) {
            $stats = $profile['positions'][$position];
            $global = $profile['global'];
            $hands = $this->hands();
            $percentiles = [];
            $weights = [];
            $samples = [];
            foreach ($hands as $hand) {
                $percentiles[$hand] = $this->ranking->percentileForCode($hand);
                $weights[$hand] = $this->combos($hand);
                $samples[$hand] = $handStats->get((self::DB_POSITION[$position] ?? $position) . ':' . $hand);
            }
            if ($classShifts) {
                $percentiles = $this->tilt->apply($percentiles, $weights, $classShifts);
            }

            $width = max(4.0, min(10.0, 5.0 + max(0.0, $global['vpip'] - $global['pfr']) / 10));
            $openTarget = $position === 'BB' ? 0.0 : $stats['open'];
            $limpTarget = $position === 'BB' ? 0.0 : $stats['limp'];
            $trapPrior = $limpTarget > 0
                ? $this->trapPrior($bot, $global, $percentiles, $openTarget, $width)
                : array_fill_keys($hands, 0.0);

            $openPrior = $this->softTop($percentiles, $openTarget, $width, $weights);
            foreach ($hands as $hand) {
                $sample = $samples[$hand];
                $openPrior[$hand] = min(
                    100.0 - $trapPrior[$hand],
                    $this->blendObserved(
                        $openPrior[$hand],
                        (int) ($sample->open_raises ?? 0),
                        (int) ($sample->open_opportunities ?? 0)
                    )
                );
            }
            $openCaps = array_map(fn($freq) => max(0.0, 100.0 - $freq), $trapPrior);
            $open = $this->calibrate($openPrior, $openTarget, $weights, $openCaps);

            $limpPrior = $this->softBand($percentiles, $openTarget, $openTarget + $limpTarget, $width, $weights);
            foreach ($hands as $hand) {
                $sample = $samples[$hand];
                $limpPrior[$hand] = $this->blendObserved(
                    min(100.0, $limpPrior[$hand] + $trapPrior[$hand]),
                    (int) ($sample->limps ?? 0),
                    (int) ($sample->open_opportunities ?? 0)
                );
            }
            $limpCaps = array_map(fn($freq) => max(0.0, 100.0 - $freq), $open);
            $limp = $this->calibrate($limpPrior, $limpTarget, $weights, $limpCaps);

            $threeBetTarget = $position === 'UTG' ? 0.0 : $stats['vs_open']['three_bet'];
            $callTarget = $position === 'UTG' ? 0.0 : $stats['vs_open']['call'];
            [$threeBet, $call] = $this->defenseRanges($hands, $percentiles, $weights, $samples, $threeBetTarget, $callTarget, $width);
            $cold = $this->coldResponses($hands, $percentiles, $weights, $samples, $threeBet, $call, $global);

            $response = $this->openResponses($stats, $hands, $percentiles, $weights, $open, $samples, $width);
            $limpResponse = $this->limpResponses($bot, $global, $stats['limp_response'], $hands, $percentiles, $weights, $limp, $samples, $width);

            $attack = [];
            $defense = [];
            foreach ($hands as $hand) {
                $attack[] = [
                    'hand' => $hand,
                    'percentile' => $percentiles[$hand],
                    'open' => $open[$hand],
                    'limp' => $limp[$hand],
                    'open_response' => [
                        'reraise' => $response['reraise'][$hand],
                        'call' => $response['call'][$hand],
                        'fold' => $response['fold'][$hand],
                    ],
                    'limp_response' => [
                        'raise' => $limpResponse['raise'][$hand],
                        'push' => $limpResponse['push'][$hand],
                    ],
                ];
                $defense[] = [
                    'hand' => $hand,
                    'percentile' => $percentiles[$hand],
                    'three_bet' => $threeBet[$hand],
                    'call' => $call[$hand],
                    'fold' => max(0.0, 100.0 - $threeBet[$hand] - $call[$hand]),
                    'cold3_raise' => $cold[0][$hand],
                    'cold3_call' => $cold[1][$hand],
                    'cold4_raise' => $cold[2][$hand],
                    'cold4_call' => $cold[3][$hand],
                ];
            }

            $tables[$position] = [
                'attack' => $attack,
                'defense' => $defense,
                'stats' => [
                    'open' => $this->weightedAverage($open, $weights),
                    'limp' => $this->weightedAverage($limp, $weights),
                    'three_bet' => $this->weightedAverage($threeBet, $weights),
                    'defense' => $this->weightedAverage($threeBet, $weights) + $this->weightedAverage($call, $weights),
                    'open_response' => [
                        'reraise' => $response['summary']['reraise'],
                        'call' => $response['summary']['call'],
                        'fold' => $response['summary']['fold'],
                    ],
                    'limp_response' => [
                        'raise' => $limpResponse['summary']['raise'],
                        'push' => $limpResponse['summary']['push'],
                    ],
                ],
            ];
        }

        return $tables;
    }

    private function persistGeneratedRanges(Bot $bot, array $tables, $storedRanges, string $bucket = 'all'): array
    {
        $generatedRows = [];
        $now = now();

        foreach ($tables as $position => &$table) {
            foreach ($table['attack'] as $index => &$attack) {
                $defense = &$table['defense'][$index];
                $range = $storedRanges->get($position . ':' . $attack['hand']);
                if ($range && $range->is_manual) {
                    $attack['open'] = $range->attack_open;
                    $attack['limp'] = $range->attack_limp;
                    $attack['open_response'] = [
                        'reraise' => $range->open_reraise,
                        'call' => $range->open_call,
                        'fold' => $range->open_fold,
                    ];
                    $attack['limp_response'] = [
                        'raise' => $range->limp_raise,
                        'push' => $range->limp_push,
                    ];
                    $defense['three_bet'] = $range->defense_three_bet;
                    $defense['call'] = $range->defense_call;
                    $defense['fold'] = $range->defense_fold;
                    foreach (['cold3_raise', 'cold3_call', 'cold4_raise', 'cold4_call'] as $field) {
                        $defense[$field] = $range->{$field} ?? $defense[$field];
                    }
                    $attack['manual'] = $defense['manual'] = true;
                } else {
                    $row = [
                        'bot_id' => $bot->id,
                        'stack_bucket' => $bucket,
                        'position' => $position,
                        'hand' => $attack['hand'],
                        'attack_open' => round($attack['open'], 2),
                        'attack_limp' => round($attack['limp'], 2),
                        'open_reraise' => round($attack['open_response']['reraise'], 2),
                        'open_call' => round($attack['open_response']['call'], 2),
                        'open_fold' => round($attack['open_response']['fold'], 2),
                        'limp_raise' => round($attack['limp_response']['raise'], 2),
                        'limp_push' => round($attack['limp_response']['push'], 2),
                        'defense_three_bet' => round($defense['three_bet'], 2),
                        'defense_call' => round($defense['call'], 2),
                        'defense_fold' => round($defense['fold'], 2),
                        'cold3_raise' => round($defense['cold3_raise'], 2),
                        'cold3_call' => round($defense['cold3_call'], 2),
                        'cold4_raise' => round($defense['cold4_raise'], 2),
                        'cold4_call' => round($defense['cold4_call'], 2),
                        'is_manual' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $changed = ! $range;
                    if ($range) {
                        foreach ([
                            'attack_open',
                            'attack_limp',
                            'open_reraise',
                            'open_call',
                            'open_fold',
                            'limp_raise',
                            'limp_push',
                            'defense_three_bet',
                            'defense_call',
                            'defense_fold',
                            'cold3_raise',
                            'cold3_call',
                            'cold4_raise',
                            'cold4_call',
                        ] as $field) {
                            if ($range->{$field} === null || round((float) $range->{$field}, 2) !== $row[$field]) {
                                $changed = true;
                                break;
                            }
                        }
                    }
                    if ($changed) {
                        $generatedRows[] = $row;
                    }
                    $attack['manual'] = $defense['manual'] = false;
                }
                unset($defense);
            }
            unset($attack);
            $table['stats'] = $this->summarize($table);
        }
        unset($table);

        foreach (array_chunk($generatedRows, 100) as $chunk) {
            BotPreflopHandRange::upsert(
                $chunk,
                ['bot_id', 'stack_bucket', 'position', 'hand'],
                [
                    'attack_open',
                    'attack_limp',
                    'open_reraise',
                    'open_call',
                    'open_fold',
                    'limp_raise',
                    'limp_push',
                    'defense_three_bet',
                    'defense_call',
                    'defense_fold',
                    'cold3_raise',
                    'cold3_call',
                    'cold4_raise',
                    'cold4_call',
                    'is_manual',
                    'updated_at',
                ]
            );
        }

        return $tables;
    }

    private function summarize(array $table): array
    {
        $weights = [];
        $openWeights = [];
        $limpWeights = [];
        $open = $limp = $threeBet = $defense = [];
        $reraise = $openCall = $openFold = $limpRaise = $limpPush = [];

        foreach ($table['attack'] as $index => $attack) {
            $hand = $attack['hand'];
            $weights[$hand] = $this->combos($hand);
            $openWeights[$hand] = $weights[$hand] * $attack['open'];
            $limpWeights[$hand] = $weights[$hand] * $attack['limp'];
            $open[$hand] = $attack['open'];
            $limp[$hand] = $attack['limp'];
            $reraise[$hand] = $attack['open_response']['reraise'];
            $openCall[$hand] = $attack['open_response']['call'];
            $openFold[$hand] = $attack['open_response']['fold'];
            $limpRaise[$hand] = $attack['limp_response']['raise'];
            $limpPush[$hand] = $attack['limp_response']['push'];
            $threeBet[$hand] = $table['defense'][$index]['three_bet'];
            $defense[$hand] = $table['defense'][$index]['three_bet'] + $table['defense'][$index]['call'];
        }

        return [
            'open' => $this->weightedAverage($open, $weights),
            'limp' => $this->weightedAverage($limp, $weights),
            'three_bet' => $this->weightedAverage($threeBet, $weights),
            'defense' => $this->weightedAverage($defense, $weights),
            'open_response' => [
                'reraise' => $this->weightedAverage($reraise, $openWeights),
                'call' => $this->weightedAverage($openCall, $openWeights),
                'fold' => $this->weightedAverage($openFold, $openWeights),
            ],
            'limp_response' => [
                'raise' => $this->weightedAverage($limpRaise, $limpWeights),
                'push' => $this->weightedAverage($limpPush, $limpWeights),
            ],
        ];
    }

    /**
     * Respuesta de quien NO ha actuado y se encuentra un 3-bet (cold3) o un 4-bet o más (cold4).
     *
     * Lo que sale de las estadísticas de PokerTracker (cold3_call + cold3_raise = % de veces que sigue; la parte que sube sobre
     * el total que sigue) fija CUÁNTO continúa, y la fuerza de la mano decide CON QUÉ: las más fuertes primero dentro de su rango
     * de defensa ante una apertura, y subir antes que pagar. Después se mezcla lo observado por mano (calls y subidas vistos),
     * que es lo que hace que un perfil que mete AKo muchas veces lo haga aquí. Contra un 4-bet se parte de lo anterior y se
     * deja la fracción que no foldea ante un 4-bet (fold_to_4bet del perfil). AA y KK (los dos pares más fuertes) nunca foldean.
     *
     * @return array{0: array, 1: array, 2: array, 3: array} [cold3_raise, cold3_call, cold4_raise, cold4_call] por mano
     */
    private function coldResponses(array $hands, array $percentiles, array $weights, $samples, array $threeBet, array $call, array $global): array
    {
        $zero = array_fill_keys($hands, 0.0);
        $defendFreq = $defendWeight = [];
        foreach ($hands as $hand) {
            $defendFreq[$hand] = min(100.0, $threeBet[$hand] + $call[$hand]);
            $defendWeight[$hand] = $weights[$hand] * $defendFreq[$hand] / 100.0;
        }
        $total = array_sum($weights);
        $defendMass = 100.0 * array_sum($defendWeight) / $total;
        if ($defendMass <= 0) {
            return [$zero, $zero, $zero, $zero];
        }

        if (isset($global['cold3_call'], $global['cold3_raise'])) {
            $continue = $global['cold3_call'] + $global['cold3_raise'];
            $raiseShare = $continue > 0 ? $global['cold3_raise'] / $continue : 0.0;
        } else { // sin dato de PokerTracker: la parte superior de su defensa, subiendo según lo que ya sube al defender
            $continue = self::COLD_CONTINUE_FALLBACK * $defendMass;
            $threeMass = $callMass = 0.0;
            foreach ($hands as $hand) {
                $threeMass += $weights[$hand] * $threeBet[$hand];
                $callMass += $weights[$hand] * $call[$hand];
            }
            $raiseShare = ($threeMass + $callMass) > 0 ? $threeMass / ($threeMass + $callMass) : 0.0;
        }
        $continue = min($continue, $defendMass);

        $strongestFirst = array_map(fn($p) => -$p, $percentiles);
        $fraction = $this->fillByOrder($strongestFirst, 100.0 * $continue / $defendMass, $defendWeight, $percentiles);
        $continueFreq = $continueWeight = [];
        foreach ($hands as $hand) {
            $continueFreq[$hand] = $defendFreq[$hand] * $fraction[$hand] / 100.0;
            $continueWeight[$hand] = $weights[$hand] * $continueFreq[$hand] / 100.0;
        }
        $raiseFraction = $this->fillByOrder($strongestFirst, 100.0 * $raiseShare, $continueWeight, $percentiles);
        $raise = [];
        foreach ($hands as $hand) {
            $raise[$hand] = $continueFreq[$hand] * $raiseFraction[$hand] / 100.0;
        }

        $guaranteed = $percentiles['KK'] ?? 0.0;
        foreach ($hands as $hand) {
            $sample = $samples[$hand];
            $opportunities = (int) ($sample->cold3_opportunities ?? 0);
            if ($opportunities > 0) {
                $continueFreq[$hand] = $this->blendObserved($continueFreq[$hand], (int) $sample->cold3_calls + (int) $sample->cold3_raises, $opportunities);
                $raise[$hand] = $this->blendObserved($raise[$hand], (int) $sample->cold3_raises, $opportunities);
            }
            if ($percentiles[$hand] <= $guaranteed) {
                $continueFreq[$hand] = 100.0;
            }
            $raise[$hand] = min($raise[$hand], $continueFreq[$hand]);
        }

        // Contra un 4-bet: de los que siguen, los más fuertes que no foldean ante un 4-bet; los que suben mantienen su proporción.
        $stayShare = max(0.0, min(100.0, 100.0 - (float) ($global['fold_to_4bet'] ?? 60.0)));
        $continueWeight = [];
        foreach ($hands as $hand) {
            $continueWeight[$hand] = $weights[$hand] * $continueFreq[$hand] / 100.0;
        }
        $stay = $this->fillByOrder($strongestFirst, $stayShare, $continueWeight, $percentiles);
        $continue4 = $raise4 = [];
        foreach ($hands as $hand) {
            $continue4[$hand] = $percentiles[$hand] <= $guaranteed ? 100.0 : $continueFreq[$hand] * $stay[$hand] / 100.0;
            $raise4[$hand] = min($continue4[$hand], $raise[$hand] * $stay[$hand] / 100.0);
        }

        $cold3Call = $cold4Call = [];
        foreach ($hands as $hand) {
            $cold3Call[$hand] = max(0.0, $continueFreq[$hand] - $raise[$hand]);
            $cold4Call[$hand] = max(0.0, $continue4[$hand] - $raise4[$hand]);
        }

        return [$raise, $cold3Call, $raise4, $cold4Call];
    }

    /**
     * Defensa contra una apertura: 3-bet y call.
     *
     * - El 3-bet es polarizado: la mayor parte sale de las manos MÁS FUERTES (valor) y una parte, que crece con el % de
     *   3-bet, de manos con bloqueadores y buena jugabilidad (A5s-A2s, suited conectadas), no de las siguientes en fuerza.
     * - El call se elige por jugabilidad (pares, suited, conectadas) entre las manos que NO se resubirían: un call en
     *   frío no son las siguientes manos del ranking, son las que se juegan bien sin iniciativa.
     * - Una mano más fuerte que las que ya se foldean casi siempre nunca se foldea: el resto va a call (slowplay).
     *
     * @return array{0: array<string,float>, 1: array<string,float>} [three_bet, call]
     */
    private function defenseRanges(array $hands, array $percentiles, array $weights, $samples, float $threeBetTarget, float $callTarget, float $width): array
    {
        if ($threeBetTarget <= 0 && $callTarget <= 0) { // sin nadie delante (UTG): no hay defensa
            $zero = array_fill_keys($hands, 0.0);

            return [$zero, $zero];
        }
        // Margen en la estadística (como StatBounds): la defensa cubre al menos todas las manos tan fuertes como la peor PREMIUM (AA-KK-QQ-AK, con las que el ranking pone por delante, JJ-TT...).
        $premiumBoundary = max(array_map(fn($h) => $percentiles[$h], self::PREMIUM));
        $minDefense = 0.0;
        foreach ($hands as $hand) {
            if ($percentiles[$hand] <= $premiumBoundary) {
                $minDefense += $weights[$hand];
            }
        }
        $minDefense = 100.0 * $minDefense / array_sum($weights);
        if ($threeBetTarget + $callTarget > 0 && $threeBetTarget + $callTarget < $minDefense) {
            $threeBetTarget = max($threeBetTarget, $minDefense);
        }
        $bluffShare = $threeBetTarget >= 5.0 ? min(0.4, 0.15 + $threeBetTarget * 0.03) : 0.0;
        $valueTarget = $threeBetTarget * (1.0 - $bluffShare);
        $value = $this->softTop($percentiles, $valueTarget, $width, $weights);

        $bluff = array_fill_keys($hands, 0.0);
        $neverBluff = array_fill_keys($hands, false);
        if ($bluffShare > 0) {
            $scores = [];
            $candidateWeights = [];
            foreach ($hands as $hand) {
                $score = $this->bluffScore($hand);
                $inValueZone = $percentiles[$hand] <= $valueTarget + $width / 2;
                // Con un 3-bet muy alto (Maníaco: 46-54 %) los faroles buenos (A5s-A2s, conectadas suited) no bastan: también resube manos con
                // algo de jugabilidad (todo menos el 30 % más débil). Antes el techo era ~35 % y el perfil resubía menos de lo que marca su estadística.
                $wideBluff = $threeBetTarget >= 20.0 && $score <= 0 && $percentiles[$hand] <= 70.0;
                if ($wideBluff) {
                    $score = 1.0;
                }
                $scores[$hand] = $inValueZone ? -1000.0 : $score;
                $neverBluff[$hand] = $score <= 0 && ! $inValueZone;
                $candidateWeights[$hand] = ($score > 0 && ! $inValueZone) ? $weights[$hand] : 0.0;
            }
            $bluffPercentiles = $this->cumulativePercentiles($scores, $candidateWeights, array_sum($weights));
            $bluff = $this->softTop($bluffPercentiles, $threeBetTarget - $valueTarget, $width, $weights);
            $caps = array_map(fn($never) => $never ? 0.0 : 75.0, $neverBluff);
            $bluff = $this->calibrate($bluff, $threeBetTarget - $valueTarget, $weights, $caps);
        }

        $threeBet = [];
        foreach ($hands as $hand) {
            $sample = $samples[$hand];
            $threeBet[$hand] = $this->blendObserved(
                min(100.0, $value[$hand] + $bluff[$hand]),
                (int) ($sample->three_bets ?? 0),
                (int) ($sample->vs_open_opportunities ?? 0)
            );
        }
        $threeBet = $this->calibrate($threeBet, $threeBetTarget, $weights, array_map(fn($never) => $never ? 0.0 : 100.0, $neverBluff));

        $callWeights = [];
        $defendScores = [];
        foreach ($hands as $hand) {
            $callWeights[$hand] = $weights[$hand] * (1.0 - $threeBet[$hand] / 100.0);
            $defendScores[$hand] = $this->defendScore($hand, $percentiles[$hand]);
        }
        $callPercentiles = $this->cumulativePercentiles($defendScores, $callWeights, array_sum($weights), false);
        $callCaps = array_map(fn($freq) => 100.0 - $freq, $threeBet);
        $sumWeights = array_sum($weights);

        // closeGaps pasa a call las manos fuertes que el orden por jugabilidad dejaba en fold, y eso SUMA al call objetivo (+4-5 puntos en
        // casi todos los perfiles). Se descuenta lo añadido y se vuelve a repartir (2-3 vueltas bastan).
        $target = $callTarget;
        $closed = [];
        for ($i = 0; $i < 4; $i++) {
            $call = $this->softTop($callPercentiles, $target, $width, $weights);
            $call = $this->calibrate($call, $target, $weights, $callCaps);
            $closed = $this->closeGaps($hands, $percentiles, $threeBet, $call, $premiumBoundary);
            $total = 0.0;
            foreach ($hands as $hand) {
                $total += $weights[$hand] * $closed[$hand];
            }
            $excess = $total / $sumWeights - $callTarget;
            if ($target <= 0.0 || abs($excess) < 0.75) {
                break;
            }
            $target = max(0.0, $target - $excess);
        }

        return [$threeBet, $closed];
    }

    /**
     * Ninguna mano más fuerte que la más fuerte que se foldea (>= 50 %) debería foldearse: lo que falte para 100 %
     * (el 3-bet y el call se calibran por separado y dejan huecos) se pasa a call.
     *
     * @param array<string,float> $primary   frecuencia de la acción agresiva (3-bet / 4-bet)
     * @param array<string,float> $secondary frecuencia de call (se modifica)
     * @return array<string,float>
     */
    /**
     * Reparte un % objetivo llenando por orden de valor (mayor primero, a igualdad la mano más débil) hasta el tope de cada
     * mano: frecuencias de 0 / 100 salvo en el borde. A diferencia de calibrate (que desplaza todo por igual y deja una
     * fracción en las manos fuertes), no "filtra" fold ni nada a las manos que el orden pone en el otro extremo.
     *
     * @param array<string,float> $values      prioridad de cada mano (mayor = antes)
     * @param array<string,float> $percentiles  fuerza (mayor = más débil) para desempatar
     * @param array<string,float>|null $caps    tope de cada mano
     * @return array<string,float>
     */
    private function fillByOrder(array $values, float $target, array $weights, array $percentiles, ?array $caps = null): array
    {
        $order = array_keys($values);
        usort($order, fn($a, $b) => [$values[$b], $percentiles[$b]] <=> [$values[$a], $percentiles[$a]]);

        $remaining = max(0.0, min(100.0, $target)) / 100.0 * array_sum($weights);
        $out = array_fill_keys(array_keys($values), 0.0);
        foreach ($order as $hand) {
            $weight = (float) ($weights[$hand] ?? 0.0);
            if ($weight <= 0 || $remaining <= 0) {
                continue;
            }
            $freq = min($caps[$hand] ?? 100.0, 100.0, 100.0 * $remaining / $weight);
            $out[$hand] = $freq;
            $remaining -= $weight * $freq / 100.0;
        }

        return $out;
    }

    /**
     * Regresión isotónica (PAV): ajusta los valores para que no decrezcan al pasar de la mano más fuerte a la más débil,
     * conservando la media ponderada. Solo cuentan las manos con peso > 0.
     *
     * @param array<string,float> $values
     * @param array<string,float> $percentiles menor = más fuerte
     * @param array<string,float> $weights
     * @return array<string,float>
     */
    private function isotonic(array $values, array $percentiles, array $weights): array
    {
        $order = array_values(array_filter(array_keys($values), fn($h) => ($weights[$h] ?? 0) > 0));
        usort($order, fn($a, $b) => $percentiles[$a] <=> $percentiles[$b]);

        $blocks = [];
        foreach ($order as $hand) {
            $blocks[] = ['hands' => [$hand], 'weight' => (float) $weights[$hand], 'sum' => $values[$hand] * $weights[$hand]];
            while (count($blocks) > 1) {
                $last = count($blocks) - 1;
                if ($blocks[$last - 1]['sum'] / $blocks[$last - 1]['weight'] <= $blocks[$last]['sum'] / $blocks[$last]['weight']) {
                    break;
                }
                $merged = ['hands' => array_merge($blocks[$last - 1]['hands'], $blocks[$last]['hands']), 'weight' => $blocks[$last - 1]['weight'] + $blocks[$last]['weight'], 'sum' => $blocks[$last - 1]['sum'] + $blocks[$last]['sum']];
                array_splice($blocks, $last - 1, 2, [$merged]);
            }
        }
        foreach ($blocks as $block) {
            foreach ($block['hands'] as $hand) {
                $values[$hand] = $block['sum'] / $block['weight'];
            }
        }

        return $values;
    }

    private function closeGaps(array $hands, array $percentiles, array $primary, array $secondary, float $premiumBoundary): array
    {
        $boundary = null;
        foreach ($hands as $hand) {
            if (100.0 - $primary[$hand] - $secondary[$hand] >= 50.0) {
                $boundary = $boundary === null ? $percentiles[$hand] : min($boundary, $percentiles[$hand]);
            }
        }
        // Las PREMIUM (y todo lo que el ranking pone por delante) tampoco se foldean aunque sean ellas el borde.
        $boundary = $boundary === null ? null : max($boundary, $premiumBoundary + 0.001);
        if ($boundary === null) {
            $boundary = $premiumBoundary + 0.001;
        }
        foreach ($hands as $hand) {
            if ($percentiles[$hand] < $boundary) {
                $secondary[$hand] = max($secondary[$hand], 100.0 - $primary[$hand]);
            }
        }

        return $secondary;
    }

    /**
     * Qué tan buena es una mano como FAROL de 3-bet/4-bet (mayor = mejor): bloqueadores de la nuts (A5s-A2s) y manos
     * suited conectadas, que si las llaman juegan bien. Pares y manos sin suited ni bloqueador no son faroles (<= 0).
     */
    public static function bluffScore(string $hand): float
    {
        if (strlen($hand) === 2) {
            return 0.0;
        }
        $hi = strpos(self::RANKS, $hand[0]);
        $lo = strpos(self::RANKS, $hand[1]);
        $suited = $hand[2] === 's';
        $gap = $lo - $hi - 1;

        if ($suited && $hi === 0 && $lo >= 9) {          // A5s-A2s
            return 100.0 - ($lo - 9) * 3.0;
        }
        if ($suited && $gap <= 1 && $hi >= 4 && $lo <= 10) { // T9s-54s (conectadas y con un hueco)
            return 75.0 - $gap * 6.0 - abs($hi - 5) * 1.5;
        }
        if ($suited && $hi === 1 && $lo >= 5 && $lo <= 8) {  // K9s-K6s
            return 50.0 - ($lo - 5) * 2.0;
        }
        if ($suited && $hi === 0 && $lo >= 5) {              // A9s-A6s
            return 45.0;
        }
        if (! $suited && $hi === 0 && $lo >= 9) {            // A5o-A2o
            return 30.0 - ($lo - 9);
        }

        return 0.0;
    }

    /**
     * Orden para llamar sin iniciativa (menor = mejor): el percentil de fuerza, con bonus a las manos que realizan su
     * equity (pares y suited) y castigo a las offsuit, que se dominan y se juegan mal fuera de posición.
     */
    private function defendScore(string $hand, float $percentile): float
    {
        if (strlen($hand) === 2) {
            return $percentile - 3.0;
        }

        return $percentile + ($hand[2] === 's' ? -3.0 : 5.0);
    }

    /**
     * Percentil ABSOLUTO (en % de las 1326 combinaciones) de cada mano según un orden y unos pesos: % de combos que
     * hay delante contando la mitad de la propia. $higherBetter indica si una puntuación mayor es mejor.
     *
     * @param array<string,float> $scores
     * @param array<string,float> $weights
     * @return array<string,float>
     */
    private function cumulativePercentiles(array $scores, array $weights, float $total, bool $higherBetter = true): array
    {
        $order = array_keys($scores);
        usort($order, fn($a, $b) => $higherBetter ? $scores[$b] <=> $scores[$a] : $scores[$a] <=> $scores[$b]);

        $out = [];
        $before = 0.0;
        foreach ($order as $hand) {
            $weight = (float) ($weights[$hand] ?? 0.0);
            $out[$hand] = 100.0 * ($before + $weight / 2) / max(1.0, $total);
            $before += $weight;
        }

        return $out;
    }

    private function openResponses(array $stats, array $hands, array $percentiles, array $weights, array $open, $samples, float $width): array
    {
        $targets = $stats['vs_3bet'];
        $responseWeights = [];
        foreach ($hands as $hand) {
            $responseWeights[$hand] = $weights[$hand] * $open[$hand];
        }
        if (array_sum($responseWeights) <= 0) {
            $zero = array_fill_keys($hands, 0.0);

            return ['reraise' => $zero, 'call' => $zero, 'fold' => $zero, 'summary' => ['reraise' => 0.0, 'call' => 0.0, 'fold' => 0.0]];
        }

        // Los % de respuesta a un 3-bet son % de SU rango de apertura, no de todas las manos: el orden de fuerza
        // se mide dentro de ese rango (con el percentil global, un Nit que abre el 12 % no llegaba nunca al umbral
        // y la calibración repartía el fold por igual entre todas las manos, AA incluido).
        $within = $this->percentilesWithin($percentiles, $responseWeights);
        // El 4-bet también es polarizado: valor arriba y, si es amplio, farsoles con bloqueadores (A5s...) de su propio rango.
        $reraiseTarget = $targets['reraise'];
        $bluffShare = $reraiseTarget >= 4.0 ? min(0.35, 0.1 + $reraiseTarget * 0.02) : 0.0;
        $valueTarget = $reraiseTarget * (1.0 - $bluffShare);
        $reraise = $this->softTop($within, $valueTarget, $width, $responseWeights);
        if ($bluffShare > 0) {
            $scores = [];
            $candidateWeights = [];
            $caps = [];
            foreach ($hands as $hand) {
                $score = $this->bluffScore($hand);
                $inValueZone = $within[$hand] <= $valueTarget + $width / 2;
                $usable = $score > 0 && ! $inValueZone && $responseWeights[$hand] > 0;
                $scores[$hand] = $usable ? $score : -1000.0;
                $candidateWeights[$hand] = $usable ? $responseWeights[$hand] : 0.0;
                $caps[$hand] = $usable ? 60.0 : 0.0;
            }
            $bluffPercentiles = $this->cumulativePercentiles($scores, $candidateWeights, array_sum($responseWeights));
            $bluff = $this->softTop($bluffPercentiles, $reraiseTarget - $valueTarget, $width, $responseWeights);
            $bluff = $this->calibrate($bluff, $reraiseTarget - $valueTarget, $responseWeights, $caps);
            foreach ($hands as $hand) {
                $reraise[$hand] = min(100.0, $reraise[$hand] + $bluff[$hand]);
            }
        }
        $fold = $this->softBottom($within, $targets['fold'], $width, $responseWeights);
        foreach ($hands as $hand) {
            $sample = $samples[$hand];
            $opportunities = (int) ($sample->vs_3bet_opportunities ?? 0);
            if ($opportunities > 0) {
                $reraise[$hand] = $this->blendObserved($reraise[$hand], (int) $sample->open_reraises, $opportunities);
                $fold[$hand] = $this->blendObserved($fold[$hand], (int) $sample->open_folds, $opportunities);
            }
        }

        $reraise = $this->calibrate($reraise, $targets['reraise'], $responseWeights);
        // Margen en la estadística: el % de fold a un 3-bet deja siempre continuar con AA-KK-QQ-AK que estén en su rango de apertura.
        $premiumBoundary = max(array_map(fn($h) => $percentiles[$h], self::PREMIUM));
        $premiumWeight = 0.0;
        foreach ($hands as $hand) {
            if ($percentiles[$hand] <= $premiumBoundary) {
                $premiumWeight += $responseWeights[$hand];
            }
        }
        $minContinue = 100.0 * $premiumWeight / array_sum($responseWeights);
        $foldTarget = min($targets['fold'], 100.0 - $targets['reraise']);
        $foldTarget = min($foldTarget, 100.0 - $minContinue);
        // Lo observado puede dar a una mano fuerte más fold que a una débil con pocas muestras: se exige que el fold no baje al empeorar la mano.
        $fold = $this->isotonic($fold, $percentiles, $responseWeights);
        $fold = $this->fillByOrder($fold, $foldTarget, $responseWeights, $percentiles, array_map(fn($freq) => 100 - $freq, $reraise));
        // Una mano más fuerte que las que se foldean casi siempre no se foldea (el hueco va a call).
        $boundary = null;
        foreach ($hands as $hand) {
            if ($responseWeights[$hand] > 0 && $fold[$hand] >= 50.0) {
                $boundary = $boundary === null ? $percentiles[$hand] : min($boundary, $percentiles[$hand]);
            }
        }
        $boundary = max($boundary ?? 0.0, $premiumBoundary + 0.001);
        foreach ($hands as $hand) {
            if ($percentiles[$hand] < $boundary) {
                $fold[$hand] = 0.0;
            }
        }
        $call = [];
        foreach ($hands as $hand) {
            $call[$hand] = max(0.0, 100.0 - $reraise[$hand] - $fold[$hand]);
        }

        return [
            'reraise' => $reraise,
            'call' => $call,
            'fold' => $fold,
            'summary' => [
                'reraise' => $this->weightedAverage($reraise, $responseWeights),
                'call' => $this->weightedAverage($call, $responseWeights),
                'fold' => $this->weightedAverage($fold, $responseWeights),
            ],
        ];
    }

    private function limpResponses(Bot $bot, array $global, array $targets, array $hands, array $percentiles, array $weights, array $limp, $samples, float $width): array
    {
        [$fallbackRaise, $fallbackPush] = $this->fallbackLimpResponse($bot, $global);
        $raiseTarget = $targets['raise'] ?? $fallbackRaise;
        $pushTarget = $targets['push'] ?? $fallbackPush;
        $responseWeights = [];
        foreach ($hands as $hand) {
            $responseWeights[$hand] = $weights[$hand] * $limp[$hand];
        }
        if (array_sum($responseWeights) <= 0) {
            $zero = array_fill_keys($hands, 0.0);

            return ['raise' => $zero, 'push' => $zero, 'summary' => ['raise' => 0.0, 'push' => 0.0]];
        }
        // Igual que en openResponses: los % son del rango con el que limpeó, así que se ordena dentro de ese rango.
        $within = $this->percentilesWithin($percentiles, $responseWeights);
        $raise = $this->softTop($within, $raiseTarget, $width, $responseWeights);
        $push = $this->softTop($within, $pushTarget, $width, $responseWeights);
        foreach ($hands as $hand) {
            $sample = $samples[$hand];
            $opportunities = (int) ($sample->limp_raise_opportunities ?? 0);
            if ($opportunities > 0) {
                $raise[$hand] = $this->blendObserved($raise[$hand], (int) $sample->limp_raises, $opportunities);
                $push[$hand] = $this->blendObserved($push[$hand], (int) $sample->limp_pushes, $opportunities);
            }
        }
        $raise = $this->calibrate($raise, $raiseTarget, $responseWeights);
        $push = $this->calibrate($push, $pushTarget, $responseWeights, array_map(fn($freq) => 100 - $freq, $raise));

        return [
            'raise' => $raise,
            'push' => $push,
            'summary' => [
                'raise' => $this->weightedAverage($raise, $responseWeights),
                'push' => $this->weightedAverage($push, $responseWeights),
            ],
        ];
    }

    private function fallbackLimpResponse(Bot $bot, array $global): array
    {
        $gap = max(0.0, $global['vpip'] - $global['pfr']);
        $base = match ($bot->templateTipo()) {
            'setminer', 'roca' => [1.0, 2.0],
            'nit' => [3.0, 3.0],
            'omc' => [1.0, 12.0],
            'tag' => [5.0, 1.0],
            'lag' => [9.0, 2.0],
            'calling_station' => [1.0, 7.0],
            'maniaco' => [10.0, 5.0],
            'ballena' => [1.0, 10.0],
            default => [4.0, 2.0],
        };

        return [
            min(25.0, $base[0] + $gap * 0.08),
            min(25.0, $base[1] + $gap * 0.12),
        ];
    }

    private function trapPrior(Bot $bot, array $global, array $percentiles, float $openTarget, float $width): array
    {
        $base = match ($bot->templateTipo()) {
            'setminer', 'roca' => 2.0,
            'nit' => 3.0,
            'omc', 'ballena' => 12.0,
            'tag' => 5.0,
            'lag', 'maniaco' => 8.0,
            'calling_station' => 6.0,
            default => 4.0,
        };
        $topRange = $this->softTop($percentiles, min(5.0, max(1.0, $openTarget)), $width);
        $gapAdjustment = min(5.0, max(0.0, $global['vpip'] - $global['pfr']) / 10);
        $prior = [];
        foreach ($percentiles as $hand => $percentile) {
            $prior[$hand] = min(25.0, ($base + $gapAdjustment) * $topRange[$hand] / 100);
        }

        return $prior;
    }

    /**
     * Percentil de cada mano DENTRO de un rango ponderado: 0 = la más fuerte del rango, 100 = la más débil.
     * Es el % acumulado de peso (combos × frecuencia) de las manos más fuertes, contando la mitad de la propia.
     * Las manos fuera del rango (peso 0) quedan donde les toca, sin influir.
     *
     * @param array<string, float> $percentiles percentil global de fuerza (menor = más fuerte)
     * @param array<string, float> $weights     peso de cada mano dentro del rango
     * @return array<string, float>
     */
    private function percentilesWithin(array $percentiles, array $weights): array
    {
        $total = array_sum($weights);
        if ($total <= 0) {
            return $percentiles;
        }

        $hands = array_keys($percentiles);
        usort($hands, fn($a, $b) => $percentiles[$a] <=> $percentiles[$b]);

        $within = [];
        $before = 0.0;
        foreach ($hands as $hand) {
            $weight = (float) ($weights[$hand] ?? 0.0);
            $within[$hand] = 100.0 * ($before + $weight / 2) / $total;
            $before += $weight;
        }

        return $within;
    }

    private function softBand(array $percentiles, float $start, float $end, float $width, ?array $weights = null): array
    {
        $upper = $this->softTop($percentiles, min(100.0, $end), $width, $weights);
        $lower = $this->softTop($percentiles, min(100.0, $start), $width, $weights);
        $band = [];
        foreach ($percentiles as $hand => $_) {
            $band[$hand] = max(0.0, $upper[$hand] - $lower[$hand]);
        }

        return $band;
    }

    private function softTop(array $percentiles, float $target, float $width, ?array $weights = null): array
    {
        $target = max(0.0, min(100.0, $target));
        $values = [];
        foreach ($percentiles as $hand => $percentile) {
            $values[$hand] = max(0.0, min(100.0, 50.0 + ($target - $percentile) * 100.0 / $width));
        }

        return $this->calibrate($values, $target, $weights ?? $this->handWeights($percentiles));
    }

    private function softBottom(array $percentiles, float $target, float $width, array $weights): array
    {
        return array_map(
            fn($value) => 100.0 - $value,
            $this->softTop($percentiles, 100.0 - max(0.0, min(100.0, $target)), $width, $weights)
        );
    }

    private function calibrate(array $values, float $target, array $weights, ?array $caps = null): array
    {
        $target = max(0.0, min(100.0, $target));
        $low = -100.0;
        $high = 100.0;
        for ($i = 0; $i < 42; $i++) {
            $shift = ($low + $high) / 2;
            $adjusted = [];
            foreach ($values as $hand => $value) {
                $cap = $caps[$hand] ?? 100.0;
                $adjusted[$hand] = max(0.0, min($cap, $value + $shift));
            }
            if ($this->weightedAverage($adjusted, $weights) < $target) {
                $low = $shift;
            } else {
                $high = $shift;
            }
        }

        $shift = ($low + $high) / 2;
        foreach ($values as $hand => $value) {
            $values[$hand] = max(0.0, min($caps[$hand] ?? 100.0, $value + $shift));
        }

        return $values;
    }

    private function blendObserved(float $prior, int $successes, int $opportunities): float
    {
        if ($opportunities <= 0) {
            return $prior;
        }

        $observed = 100.0 * max(0, min($opportunities, $successes)) / $opportunities;
        $confidence = $opportunities / ($opportunities + self::PRIOR_OPPORTUNITIES);

        return $prior * (1.0 - $confidence) + $observed * $confidence;
    }

    private function weightedAverage(array $values, array $weights): float
    {
        $weightedSum = 0.0;
        $weightSum = 0.0;
        foreach ($values as $hand => $value) {
            $weight = max(0.0, (float) ($weights[$hand] ?? 0));
            $weightedSum += $value * $weight;
            $weightSum += $weight;
        }

        return $weightSum > 0 ? $weightedSum / $weightSum : 0.0;
    }

    private function handWeights(array $percentiles): array
    {
        $weights = [];
        foreach ($percentiles as $hand => $_) {
            $weights[$hand] = $this->combos($hand);
        }

        return $weights;
    }

    private function combos(string $hand): int
    {
        return strlen($hand) === 2 ? 6 : (str_ends_with($hand, 's') ? 4 : 12);
    }

    private function hands(): array
    {
        $hands = [];
        $ranks = str_split(self::RANKS);

        foreach ($ranks as $row => $high) {
            foreach ($ranks as $column => $low) {
                if ($row === $column) {
                    $hands[] = $high . $low;
                } elseif ($row < $column) {
                    $hands[] = $high . $low . 's';
                } else {
                    $hands[] = $low . $high . 'o';
                }
            }
        }

        return $hands;
    }
}
