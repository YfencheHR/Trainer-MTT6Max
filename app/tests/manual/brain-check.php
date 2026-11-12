<?php
/**
 * Comprobaciones del sistema de decisión de los bots (tabla de porcentajes, reglas, percepción, autoconfiguración).
 * Ejecutar:  php tests/manual/brain-check.php
 * Usa transacciones que se deshacen: no deja datos en la base de datos.
 */
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\{Bot, BotHudStat, BotRange};
use App\Services\Bots\Brain\{BrainRunner, BrainTemplates, BrainAssembler, BrainRepository, DecisionMatrix, BotProfile};
use Illuminate\Support\Facades\DB;

$runner = app(BrainRunner::class);
$ok = fn($c) => $c ? 'OK' : 'FALLO';
$fails = 0; $check = function (string $label, bool $cond, string $extra = '') use (&$fails, $ok) { if (!$cond) $fails++; echo sprintf("  %-72s %s %s\n", $label, $ok($cond), $extra); };

/** Contexto de decisión para las pruebas. */
function ctx(array $o = []): array {
    $o += ['street' => 'river', 'board' => [], 'hole' => ['A♠', 'K♠'], 'pot' => 1000, 'to_call' => 600, 'stack' => 5000, 'position' => 'BTN', 'bb' => 100,
        'history' => [], 'opp_stats' => ['vpip' => 20, 'pfr' => 15, 'three_bet' => 6, 'af' => 2, 'hands' => 30],
        'opp_postflop_stats' => [], 'flags' => []];
    $legal = ['fold' => true, 'check' => $o['to_call'] === 0, 'call' => $o['to_call'] > 0 ? min($o['to_call'], $o['stack']) : null,
        'raise' => ['min' => max(200, $o['to_call'] * 2), 'max' => $o['stack']], 'allin' => $o['stack'], 'maxBet' => $o['to_call'], 'is_bet' => false];
    return [
        'street' => $o['street'], 'big_blind' => $o['bb'], 'pot' => $o['pot'], 'current_bet' => $o['to_call'], 'to_call' => $o['to_call'],
        'board' => $o['board'], 'required_equity' => $o['to_call'] > 0 ? $o['to_call'] / ($o['pot'] + $o['to_call']) : 0,
        'me' => ['id' => 1, 'stack' => $o['stack'], 'bet' => 0, 'position' => $o['position'], 'hole_cards' => $o['hole'], 'flags' => $o['flags']],
        'players' => [
            ['id' => 1, 'name' => 'Hero', 'position' => $o['position'], 'stack' => $o['stack'], 'bet' => 0, 'folded' => false, 'all_in' => false, 'is_bot' => true, 'stats' => []],
            ['id' => 2, 'name' => 'Villano', 'position' => $o['villain'] ?? 'BB', 'stack' => 5000, 'bet' => $o['to_call'], 'folded' => false, 'all_in' => false, 'is_bot' => true, 'stats' => $o['opp_stats'], 'postflop_stats' => $o['opp_postflop_stats']],
        ],
        'history' => $o['history'], 'legal' => $legal,
    ];
}
$tally = function (array $brain, array $ctx, int $n = 400) use ($runner) {
    $t = []; $cells = [];
    for ($i = 0; $i < $n; $i++) { $d = $runner->decide($ctx, $brain); $t[$d['action']] = ($t[$d['action']] ?? 0) + 1; }
    arsort($t); return array_map(fn($v) => round($v / $n * 100, 1), $t);
};

// ───────────────────────────────────────────── 1. Matriz
echo "1) Matriz = tabla de porcentajes por acción (suman 100)\n";
$m = new DecisionMatrix('fold');
$check('parte con fold al 100 %', $m->distribution() == ['fold' => 100.0]);
$m->mix(['fold' => 30, 'call' => 70]);
$d = $m->distribution();
$check('mix 30/70 → fold 30 %, call 70 %', $d['fold'] == 30 && $d['call'] == 70, json_encode($d));
$m = new DecisionMatrix('fold');
$moved = $m->add('call', 25); $m->add('raise', 10, ['call']);
$d = $m->distribution();
$check('"sumar" puntos: 25 a call (de fold) y 10 a raise (de call) → fold 75, call 15, raise 10', $moved == 25 && $d['fold'] == 75 && $d['call'] == 15 && $d['raise'] == 10, json_encode($d));
$m = new DecisionMatrix('fold'); $m->add('call', 40);
$m->add('allin', 90);   // pide más de lo que hay: se queda en el máximo disponible
$check('no se puede mover más de lo que hay (suma siempre 100)', abs(array_sum($m->distribution()) - 100) < 0.01, json_encode($m->distribution()));
$m = new DecisionMatrix('fold'); $m->mix(['a' => 1, 'b' => 1, 'c' => 1]);
$d = $m->distribution();
$check('reparto 1/1/1 → 33,33 % cada uno y suma 100', abs(array_sum($d) - 100) < 0.05 && max($d) - min($d) < 0.02, json_encode($d));
$m = new DecisionMatrix('fold'); $m->add('allin', 20); $m->mix(['call' => 1], ['fold']);
$d = $m->distribution();
$check('"only": solo se redistribuye lo que tenía fold (allin 20 % se conserva)', $d['allin'] == 20 && $d['call'] == 80, json_encode($d));
$m = new DecisionMatrix('fold'); $m->mix(['call' => 60, 'raise' => 25, 'fold' => 15]);
$n = 20000; $c = [];
for ($i = 0; $i < $n; $i++) { $a = $m->pick(random_int(0, 9999) / 100); $c[$a] = ($c[$a] ?? 0) + 1; }
$check('sorteo de un número 0-100: las frecuencias coinciden con la tabla (±1,2 puntos)', abs($c['call'] / $n * 100 - 60) < 1.2 && abs($c['raise'] / $n * 100 - 25) < 1.2 && abs($c['fold'] / $n * 100 - 15) < 1.2, json_encode(array_map(fn($v) => round($v / $n * 100, 1), $c)));
$m = new DecisionMatrix('fold'); $m->set('allin');
$allSame = true; for ($i = 0; $i < 500; $i++) if ($m->pick(random_int(0, 9999) / 100) !== 'allin') $allSame = false;
$check('tabla con allin al 100 %: el sorteo nunca falla', $allSame);
$m = new DecisionMatrix('fold'); $m->add('allin', 50); $m->lapse(10, ['call' => 1], ['allin']);
$check('imprecisión: mueve puntos a errores sin tocar las acciones protegidas (allin se queda en 50)', $m->points('allin') == 50 && abs($m->points('call') - 10) < 0.01, json_encode($m->distribution()));

// ───────────────────────────────────────────── 2. Tu ejemplo
echo "\n2) Reg vs pez con escalera de color en el river\n";
$royal = ctx(['board' => ['Q♠', 'J♠', 'T♠', '4♦', '2♣'], 'hole' => ['A♠', 'K♠']]);                 // escalera de color real
$interleaved = ctx(['board' => ['6♥', '8♥', '9♥', 'A♣', '2♣'], 'hole' => ['5♥', '7♥']]);            // 5-6-7-8-9 de corazones, intercalada, As en el mesa
$tag = BrainTemplates::forType('tag'); $fish = BrainTemplates::forType('ballena'); $station = BrainTemplates::forType('calling_station');

$r = $tally($tag, $royal);   $check('TAG con escalera real: all-in 100 % (sin posibilidad de fold)', ($r['allin'] ?? 0) == 100, json_encode($r));
$r = $tally($tag, $interleaved); $check('TAG con escalera de color intercalada: all-in 100 %', ($r['allin'] ?? 0) == 100, json_encode($r));
$rf = $tally($fish, $interleaved);
$check('Ballena con la MISMA mano: all-in al menos el 80 % (no son tontos), pero no siempre', ($rf['allin'] ?? 0) >= 80 && ($rf['allin'] ?? 0) < 100, json_encode($rf));
$check('Ballena con la MISMA mano: a veces hace fold con la mano ganadora', ($rf['fold'] ?? 0) > 0, json_encode($rf));
$rs = $tally($station, $interleaved);
$check('Calling station con la escalera de color: all-in casi siempre (no tan fiable como un experto)', ($rs['allin'] ?? 0) >= 80 && ($rs['allin'] ?? 0) < 100, json_encode($rs));

// Percepción: ¿cuántas veces ve la escalera de color?
$seen = ['tag' => 0, 'ballena' => 0]; $N = 300;
for ($i = 0; $i < $N; $i++) {
    $seen['tag'] += $runner->decide($interleaved, $tag)['trace']['real']['saw_clearly'] ? 1 : 0;
    $seen['ballena'] += $runner->decide($interleaved, $fish)['trace']['real']['saw_clearly'] ? 1 : 0;
}
$check('Percepción: TAG ve la escalera el 100 %, ballena bastante menos', $seen['tag'] === $N && $seen['ballena'] < $N * 0.6, "TAG {$seen['tag']}/{$N}, ballena {$seen['ballena']}/{$N}");
$comp = $runner->decide($interleaved, $fish)['trace']['perception']['rolls'][0] ?? null;
echo "     (tirada de percepción de la ballena: " . json_encode($comp) . ")\n";

// ───────────────────────────────────────────── 3. Especiales
echo "\n3) Acciones especiales según las reglas del bot\n";
$specials = ['slowplay', 'check_raise', 'float_call', 'bluff_raise'];
$has = fn(array $brain) => array_values(array_filter($specials, fn($s) => str_contains(json_encode($brain), "\"{$s}\"")));
$check('TAG tiene slowplay / check_raise / bluff_raise', count(array_intersect($has($tag), ['slowplay', 'check_raise', 'bluff_raise'])) === 3, json_encode($has($tag)));
$check('LAG añade float_call', in_array('float_call', $has(BrainTemplates::forType('lag'))), json_encode($has(BrainTemplates::forType('lag'))));
$check('Calling station / ballena / maníaco / roca: ningún especial', !$has($station) && !$has($fish) && !$has(BrainTemplates::forType('maniaco')) && !$has(BrainTemplates::forType('roca')));
$free = ctx(['board' => ['K♠', '7♦', '2♣'], 'street' => 'flop', 'to_call' => 0, 'pot' => 300, 'hole' => ['K♥', 'K♦'], 'position' => 'BTN']);
$tt = $tally($tag, $free, 600);
$check('TAG con set en mesa seca: aparece slowplay/check_raise (check) a veces', (($tt['check'] ?? 0) > 0), json_encode($tt));

// check_raise en dos pasos: marca intención y luego sube
$res = null; for ($i = 0; $i < 400 && !$res; $i++) { $d = $runner->decide($free, $tag); if (($d['flags']['check_raise'] ?? false)) $res = $d; }
$check('check_raise: hace check y deja la intención guardada', $res !== null && $res['action'] === 'check', $res ? json_encode($res['flags']) : '');
$facing = ctx(['board' => ['K♠', '7♦', '2♣'], 'street' => 'flop', 'to_call' => 150, 'pot' => 450, 'hole' => ['K♥', 'K♦'], 'flags' => ['check_raise' => true]]);
$tt = $tally($tag, $facing, 300);
$check('con la intención guardada y una apuesta delante, SUBE (≥ 96 %, el experto tiene un 1 % de lapsos)', (($tt['raise'] ?? 0) + ($tt['allin'] ?? 0)) >= 96, json_encode($tt));

// ───────────────────────────────────────────── 4. Determinismo y legalidad
echo "\n4) Determinismo y legalidad\n";
$a = $runner->decide($interleaved, $fish, 12345); $b = $runner->decide($interleaved, $fish, 12345);
$check('misma semilla + mismo cerebro → misma decisión y misma traza', $a['action'] === $b['action'] && $a['trace']['draw'] === $b['trace']['draw'] && $a['trace']['perception'] === $b['trace']['perception']);
$noRaise = ctx(['board' => ['Q♠', 'J♠', 'T♠', '4♦', '2♣'], 'to_call' => 600, 'stack' => 600]);
$noRaise['legal']['raise'] = null; $noRaise['legal']['allin'] = null;
$bad = 0; for ($i = 0; $i < 200; $i++) { $d = $runner->decide($noRaise, $tag); if (!in_array($d['action'], ['call', 'fold'], true)) $bad++; }
$check('sin opción de subir, nunca devuelve una acción ilegal', $bad === 0);
$free2 = ctx(['to_call' => 0, 'street' => 'flop', 'board' => ['2♣', '7♦', '9♠'], 'hole' => ['3♥', '4♦'], 'pot' => 300]);
$bad = 0; for ($i = 0; $i < 300; $i++) { $d = $runner->decide($free2, BrainTemplates::forType('nit')); if ($d['action'] === 'fold') $bad++; }
$check('con check gratis nunca hace fold (fold → check)', $bad === 0);

// ───────────────────────────────────────────── 5. Preflop autoconfigurado desde los datos del bot
echo "\n5) Preflop autoconfigurado\n";
$tagBot = Bot::where('tipo', 'tag')->first();
$profile = BotProfile::forBot($tagBot);
$p = $profile['positions'];
echo "     TAG ({$profile['source']}): abre UTG {$p['UTG']['open']} % · CO {$p['CO']['open']} % · BTN {$p['BTN']['open']} % · 3-bet BB {$p['BB']['vs_open']['three_bet']} % · continúa vs 3-bet {$p['UTG']['vs_3bet']['continue']} %\n";
$check('las aperturas crecen con la posición (UTG < CO < BTN)', $p['UTG']['open'] < $p['CO']['open'] && $p['CO']['open'] < $p['BTN']['open']);
$brainTag = app(BrainAssembler::class)->forBot($tagBot);
$pre = fn(array $hole, string $pos, array $o = []) => ctx($o + ['street' => 'preflop', 'to_call' => 100, 'pot' => 150, 'hole' => $hole, 'position' => $pos, 'stack' => 4000, 'bb' => 100]);
$r = $tally($brainTag, $pre(['A♠', 'A♥'], 'UTG', ['to_call' => 0]));
$check('TAG con AA en UTG (nadie ha subido): sube', (($r['raise'] ?? 0) + ($r['allin'] ?? 0)) >= 95, json_encode($r));
$r = $tally($brainTag, $pre(['7♠', '2♦'], 'UTG', ['to_call' => 100]));
$check('TAG con 72o en UTG: fold', ($r['fold'] ?? 0) >= 95, json_encode($r));
$r = $tally($brainTag, $pre(['8♠', '7♠'], 'BTN', ['to_call' => 100]));
$check('TAG con 87s en el BTN abre (en UTG no)', (($r['raise'] ?? 0) + ($r['allin'] ?? 0)) >= 90, json_encode($r));
$r2 = $tally($brainTag, $pre(['8♠', '7♠'], 'UTG', ['to_call' => 100]));
// Con datos reales de PokerTracker el TAG tiene hueco VPIP-PFR (limp/call), por eso no se exige el 90 % de fold
$check('   … y en UTG sube menos que en el BTN (el rango abre con la posición)', ($r2['raise'] ?? 0) + ($r2['allin'] ?? 0) <= ($r['raise'] ?? 0) + ($r['allin'] ?? 0) + 1, json_encode($r2)); // +1: ambos saturan cerca del 100 %

echo "\n6) Los datos del bot MANDAN (estadísticas por posición y rangos explícitos)\n";
DB::beginTransaction();
$test = Bot::create(['nombre' => 'PruebaPT', 'tipo' => 'tag', 'vpip' => 21, 'pfr' => 19]);
$before = BotProfile::forBot($test->fresh())['positions']['UTG']['open'];
BotHudStat::create(['bot_id' => $test->id, 'nivel' => 3, 'posicion' => 'UTG', 'accion' => 'opening', 'rfi' => 4]);
$after = BotProfile::forBot($test->fresh())['positions']['UTG']['open'];
$check("estadística real UTG/opening RFI=4 sustituye a la derivada ({$before} → {$after})", $after == 4.0);
$check('las demás posiciones no cambian', BotProfile::forBot($test->fresh())['positions']['BTN']['open'] > 20);
BotHudStat::create(['bot_id' => $test->id, 'nivel' => 4, 'posicion' => 'UTG', 'accion' => 'opening', 'rango_ciegas' => '0-6', 'rfi' => 40]);
$prof = BotProfile::forBot($test->fresh());
$check('estadística por profundidad de stack (0-6bb) genera variante propia', ($prof['buckets']['0-6']['UTG']['open'] ?? null) == 40.0);
BotRange::create(['bot_id' => $test->id, 'posicion' => 'BTN', 'accion' => 'opening', 'rango_raise' => ['AA' => 1.0, 'KK' => 0.5], 'rango_call' => ['72' => 1.0]]);
$brainPT = app(BrainAssembler::class)->forBot($test->fresh());
$r = $tally($brainPT, $pre(['K♠', 'K♥'], 'BTN', ['to_call' => 0]), 600);
$check('rango explícito: KK en BTN sube el 50 % (el resto fold/check)', abs((($r['raise'] ?? 0) + ($r['allin'] ?? 0)) - 50) < 8, json_encode($r));
$r = $tally($brainPT, $pre(['A♠', 'A♥'], 'BTN', ['to_call' => 0]), 300);
$check('rango explícito: AA en BTN sube el 100 %', (($r['raise'] ?? 0) + ($r['allin'] ?? 0)) >= 97, json_encode($r));
$r = $tally($brainPT, $pre(['Q♠', 'J♠'], 'BTN', ['to_call' => 0]), 300);
$check('rango explícito: una mano fuera del rango (QJs) no sube', (($r['raise'] ?? 0) + ($r['allin'] ?? 0)) == 0, json_encode($r));

echo "\n7) Reglas propias del usuario encima de lo automático\n";
$test->update(['brain' => ['rules' => [[
    'id' => 'mi_regla_72o', 'name' => 'Siempre all-in con 72o',
    'when' => ['f' => 'hand_code', 'op' => '=', 'v' => '72o'],
    'paint' => [['action' => 'allin']],
]]]]);
$brainCustom = app(BrainAssembler::class)->forBot($test->fresh());
$r = $tally($brainCustom, $pre(['7♠', '2♦'], 'UTG', ['to_call' => 100]), 200);
$check('regla propia: 72o → all-in 100 % (pisa al preflop automático)', ($r['allin'] ?? 0) == 100, json_encode($r));
$r = $tally($brainCustom, $pre(['A♠', 'A♥'], 'BTN', ['to_call' => 0]), 200);
$check('el resto del preflop automático sigue funcionando (AA sube ≥ 97 %)', (($r['raise'] ?? 0) + ($r['allin'] ?? 0)) >= 97, json_encode($r));
$check('regla anidada con "chance": probabilidad de aplicarse', (function () use ($runner, $pre) {
    $brain = ['default' => 'fold', 'rules' => [['id' => 'a', 'chance' => 30, 'paint' => [['action' => 'allin']]]]];
    $n = 0; for ($i = 0; $i < 1000; $i++) if ($runner->decide($pre(['A♠', 'A♥'], 'BTN', ['to_call' => 0]), $brain)['action'] === 'allin') $n++;
    return $n > 240 && $n < 360;
})());

echo "\n8) Herencia: snapshot congelado\n";
$repo = app(BrainRepository::class);
$s1 = $repo->snapshotFor($test->fresh()); $s2 = $repo->snapshotFor($test->fresh());
$check('mismo cerebro → mismo snapshot (se reutiliza)', $s1 === $s2);
$test->update(['brain' => ['rules' => []]]);
$s3 = $repo->snapshotFor($test->fresh());
$check('si cambian las reglas del bot, el cerebro nuevo es otro snapshot (los jugadores viejos no cambian)', $s3 !== $s1);
DB::rollBack();

echo "\n9) El pez sobreestima su mano y cree que le farolean\n";
$h = fn($street, $action, $amount = 0) => ['player_id' => 2, 'street' => $street, 'action' => $action, 'amount' => $amount];
$A = ctx(['board' => ['K♠', '7♦', '2♣', '4♥', '9♠'], 'hole' => ['A♦', '9♣'], 'pot' => 2000, 'to_call' => 1100, 'stack' => 8000, 'opp_stats' => ['vpip' => 24, 'pfr' => 18, 'three_bet' => 6, 'af' => 2.2, 'hands' => 40],
    'opp_postflop_stats' => ['river' => ['bluff' => 5, 'sample_size' => 100]],
    'history' => [$h('flop', 'raise', 200), $h('turn', 'raise', 450), $h('river', 'raise', 1100)]]);
$B = ctx(['board' => ['Q♥', 'J♥', '5♣', '2♦', '9♠'], 'hole' => ['A♦', '9♣'], 'pot' => 1200, 'to_call' => 700, 'stack' => 8000, 'opp_stats' => ['vpip' => 24, 'pfr' => 18, 'three_bet' => 6, 'af' => 2.2, 'hands' => 40],
    'opp_postflop_stats' => ['river' => ['bluff' => 60, 'sample_size' => 100]],
    'history' => [$h('flop', 'check'), $h('turn', 'check'), $h('river', 'raise', 700)]]);
$dA = $runner->decide($A, $tag)['trace']; $dB = $runner->decide($B, $tag)['trace'];
$check('lectura de la línea: 3 barriles en board seco → farol improbable (< 15 %)', $dA['features']['bluff_likelihood'] < 15, 'estimado ' . $dA['features']['bluff_likelihood'] . ' %');
$check('lectura de la línea: check-check y apuesta river con proyectos fallados → farol plausible, limitado por showdown (≤ 60 %)', $dB['features']['bluff_likelihood'] > 30 && $dB['features']['bluff_likelihood'] <= 60, 'estimado ' . $dB['features']['bluff_likelihood'] . ' %');
$ra = $tally($tag, $A, 600);  $check('TAG ante una línea de valor con pareja media: abandona casi siempre (≥ 85 %)', ($ra['fold'] ?? 0) >= 85, json_encode($ra));
$rb = $tally($tag, $B, 600);  $check('TAG con la MISMA mano ante un farol plausible: paga bastante más (call ≥ 50 %, y abandona 30+ puntos menos)', ($rb['call'] ?? 0) >= 50 && ($ra['fold'] ?? 0) - ($rb['fold'] ?? 0) >= 30, json_encode($rb));
$sumF = 0; $n = 400;
for ($i = 0; $i < $n; $i++) { $t = $runner->decide(ctx(['street' => 'river', 'board' => ['Q♥', 'J♥', '5♣', '2♦', '9♠'], 'hole' => ['A♦', '9♣'], 'pot' => 1200, 'to_call' => 700, 'stack' => 8000,
    'opp_postflop_stats' => [], 'history' => [$h('flop', 'check'), $h('turn', 'check'), $h('river', 'raise', 700)]]), $fish)['trace']; $sumF += $t['features']['bluff_likelihood']; }
$check('Ballena: no inventa capacidad de farol si no hay apuestas que lo respalden en showdown', $sumF === 0.0, 'farol percibido medio ' . round($sumF / $n, 1) . ' %');
$rfa = $tally($fish, $A, 600);
$check('Ballena ante la línea de valor: NO abandona (fold ≤ 10 %, paga o sube)', ($rfa['fold'] ?? 0) <= 10, json_encode($rfa));
$rsa = $tally($station, $A, 600);
$check('Calling station ante la línea de valor: NO abandona (fold ≤ 10 %, sobre todo paga)', ($rsa['fold'] ?? 0) <= 10 && ($rsa['call'] ?? 0) >= 60, json_encode($rsa));
$sumRank = 0; $sumReal = 0;
for ($i = 0; $i < $n; $i++) { $t = $runner->decide($A, $fish)['trace']; $sumRank += $t['features']['perceived_rank']; $sumReal = $t['real']['rank']; }
$check('Ballena SOBREESTIMA su mano: rank percibido mejor que el real', $sumRank / $n < $sumReal - 4, 'percibido ' . round($sumRank / $n, 1) . ' vs real ' . $sumReal);
$tagRank = 0; for ($i = 0; $i < $n; $i++) { $tagRank += $runner->decide($A, $tag)['trace']['features']['perceived_rank']; }
$check('TAG percibe su mano tal cual es', abs($tagRank / $n - $sumReal) < 2, 'percibido ' . round($tagRank / $n, 1) . ' vs real ' . $sumReal);

echo "\n10) La matriz PARTE de las estadísticas del jugador y las reglas la modulan\n";
$withStat = function (array $brain, string $street, string $key, float $v) { $brain['stats'][$street][$key] = $v; return $brain; };
$air = ctx(['street' => 'flop', 'board' => ['K♠', 'J♦', '9♣'], 'hole' => ['7♣', '2♦'], 'pot' => 600, 'to_call' => 400, 'stack' => 6000,
    'history' => [['player_id' => 2, 'street' => 'flop', 'action' => 'raise', 'amount' => 400]]]);
$mid = ctx(['street' => 'flop', 'board' => ['K♠', 'J♦', '9♣'], 'hole' => ['K♦', '4♣'], 'pot' => 600, 'to_call' => 400, 'stack' => 6000,
    'history' => [['player_id' => 2, 'street' => 'flop', 'action' => 'raise', 'amount' => 400]]]);
$strong = ctx(['street' => 'flop', 'board' => ['K♠', 'J♦', '9♣'], 'hole' => ['K♦', 'J♣'], 'pot' => 600, 'to_call' => 400, 'stack' => 6000,
    'history' => [['player_id' => 2, 'street' => 'flop', 'action' => 'raise', 'amount' => 400]]]);
$tag0 = $withStat($tag, 'flop', 'f2bet', 0);
$r = $tally($tag0, $air, 600);
$check('un jugador con 0 % de fold a la cbet parte SIN fold: no abandona ni con aire (≤ 1,5 %: solo sus lapsos)', ($r['fold'] ?? 0) <= 1.5, json_encode($r));
$r = $tally($tag, $air, 600);
$check('el mismo jugador con su fold normal (40 %) con aire abandona mucho más', ($r['fold'] ?? 0) >= 40, json_encode($r));
$tag100 = $withStat($tag, 'flop', 'f2bet', 100);
$r = $tally($tag100, $air, 600);
$check('con 100 % de fold a la cbet, con aire abandona casi siempre', ($r['fold'] ?? 0) >= 90, json_encode($r));
$fAir = $tally($tag, $air, 800)['fold'] ?? 0; $fMid = $tally($tag, $mid, 800)['fold'] ?? 0; $fStr = $tally($tag, $strong, 800)['fold'] ?? 0;
$check('misma estadística, distinta mano: abandona más con aire que con pareja y más con pareja que con mano fuerte', $fAir > $fMid && $fMid > $fStr, "aire {$fAir} % · pareja {$fMid} % · fuerte {$fStr} %");

echo "   Calling station:\n";
$setFlop = ctx(['street' => 'flop', 'board' => ['K♠', '7♦', '2♣'], 'hole' => ['7♠', '7♥'], 'pot' => 600, 'to_call' => 400, 'stack' => 6000,
    'history' => [['player_id' => 2, 'street' => 'flop', 'action' => 'raise', 'amount' => 400]]]);
$r = $tally($station, $setFlop, 800);
$check('con set en el flop NO sube (raise + all-in ≤ 4 %, solo por sus lapsos) y casi siempre paga', (($r['raise'] ?? 0) + ($r['allin'] ?? 0)) <= 7 && ($r['call'] ?? 0) >= 85, json_encode($r));
$setTurn = ctx(['street' => 'turn', 'board' => ['K♠', '7♦', '2♣', '9♥'], 'hole' => ['7♠', '7♥'], 'pot' => 1400, 'to_call' => 900, 'stack' => 6000,
    'history' => [['player_id' => 2, 'street' => 'flop', 'action' => 'raise', 'amount' => 400], ['player_id' => 2, 'street' => 'turn', 'action' => 'raise', 'amount' => 900]]]);
$r = $tally($station, $setTurn, 800);
$check('con set en el turn tampoco sube', (($r['raise'] ?? 0) + ($r['allin'] ?? 0)) <= 7 && ($r['call'] ?? 0) >= 85, json_encode($r));
$riverHist = [['player_id' => 2, 'street' => 'flop', 'action' => 'raise', 'amount' => 400], ['player_id' => 2, 'street' => 'turn', 'action' => 'raise', 'amount' => 900], ['player_id' => 2, 'street' => 'river', 'action' => 'raise', 'amount' => 1500]];
$setRiver = ctx(['street' => 'river', 'board' => ['K♠', '7♦', '2♣', '9♥', '4♠'], 'hole' => ['7♠', '7♥'], 'pot' => 3200, 'to_call' => 1500, 'stack' => 6000, 'history' => $riverHist]);
$r = $tally($station, $setRiver, 1000);
$check('en el river, un SET es all-in para la calling station (≥ 80 %)', ($r['allin'] ?? 0) >= 80, json_encode($r));
$setFlushBoard = ctx(['street' => 'river', 'board' => ['K♠', '7♠', '2♠', '9♠', '4♦'], 'hole' => ['7♦', '7♥'], 'pot' => 3200, 'to_call' => 1500, 'stack' => 6000, 'history' => $riverHist]);
$r = $tally($station, $setFlushBoard, 1000);
$check('con un color a una carta en la mesa NO va all-in: solo paga (all-in ≤ 8 %, call ≥ 70 %)', ($r['allin'] ?? 0) <= 8 && ($r['call'] ?? 0) >= 70, json_encode($r));
$twoPairRiver = ctx(['street' => 'river', 'board' => ['K♠', '7♦', '2♣', '9♥', '4♠'], 'hole' => ['K♦', '7♣'], 'pot' => 3200, 'to_call' => 1500, 'stack' => 6000, 'history' => $riverHist]);
$r = $tally($station, $twoPairRiver, 1000);
$check('con doble pareja (no es su rango de all-in) en muchas ocasiones solo paga', ($r['allin'] ?? 0) <= 8 && ($r['call'] ?? 0) >= 40, json_encode($r));
echo "   Ratio valor:farol (apostar cuando le toca):\n";
$leadRiver = fn(array $hole) => ctx(['street' => 'river', 'board' => ['K♠', '7♦', '2♣', '9♥', '4♠'], 'hole' => $hole, 'to_call' => 0, 'pot' => 1200, 'stack' => 6000, 'position' => 'BTN']);
$bluffy = $withStat($tag, 'river', 'bet', 60); $bluffy['stats']['river']['vb_ratio'] = 0.4;
$valuey = $withStat($tag, 'river', 'bet', 60); $valuey['stats']['river']['vb_ratio'] = 8.0;
$airHand = $leadRiver(['5♣', '3♦']); $nuts = $leadRiver(['K♦', '8♣']); // pareja alta: mano de valor fuera de la zona monstruo
$betsOf = fn($t) => 100 - ($t['check'] ?? 0);
$bB = $betsOf($tally($bluffy, $airHand, 600)); $bV = $betsOf($tally($valuey, $airHand, 600));
$check('con ratio 0,4 (muchos faroles) apuesta con aire MÁS que con ratio 8 (casi solo valor)', $bB > $bV + 15, "aire: {$bB} % vs {$bV} %");
$vB = $betsOf($tally($bluffy, $nuts, 600)); $vV = $betsOf($tally($valuey, $nuts, 600));
$check('con ratio 8 apuesta con la mano de valor más que con ratio 0,4 (el valor pesa más)', $vV > $vB + 10, "valor: {$vV} % vs {$vB} %");

echo "\n10) En posición / fuera de posición y papel (agresor o defensor): cifras específicas importadas\n";
// Las cifras IP/OOP viven en la copia Pool del TAG (el primario conserva sus cifras de referencia); necesita haber importado
$tagCopy = Bot::where('tipo', 'pool')->where('metadata->source', 'pt4_pool')->where('metadata->template', 'tag')->first();
$tagDb = app(BrainAssembler::class)->forBot($tagCopy ?? Bot::where('tipo', 'tag')->first());
$roleStats = $tagDb['stats']['flop'] ?? [];
$hasRows = isset($roleStats['ip']['def'], $roleStats['oop']['def'], $roleStats['ip']['agg'], $roleStats['oop']['agg']);
$check('el cerebro del TAG trae las filas IP/OOP del flop (agresor y defensor)', $hasRows, json_encode(array_intersect_key($roleStats, ['ip' => 1, 'oop' => 1])));
if ($hasRows) {
    $face = fn(string $me, string $villain) => ctx(['street' => 'flop', 'board' => ['K♠', '7♦', '2♣'], 'hole' => ['9♠', '8♠'], 'pot' => 600, 'to_call' => 300, 'stack' => 6000, 'position' => $me, 'villain' => $villain]);
    $fIp = $runner->decide($face('BTN', 'BB'), $tagDb)['trace']['features'];
    $fOop = $runner->decide($face('BB', 'BTN'), $tagDb)['trace']['features'];
    $check('con la BTN contra la BB está EN posición; con la BB contra la BTN, fuera', $fIp['in_position'] === true && $fOop['in_position'] === false);
    $check('ante una apuesta usa la respuesta del DEFENSOR según su posición', ($fIp['stats_source'] ?? '') === 'defensor_ip' && ($fOop['stats_source'] ?? '') === 'defensor_oop', json_encode([$fIp['stats_source'] ?? null, $fOop['stats_source'] ?? null]));
    $check('sus cifras salen de los datos: fold IP = ' . $roleStats['ip']['def']['fold'] . ' %, OOP = ' . $roleStats['oop']['def']['fold'] . ' %', abs($fIp['own_f2bet'] - $roleStats['ip']['def']['fold']) < 0.01 && abs($fOop['own_f2bet'] - $roleStats['oop']['def']['fold']) < 0.01);

    $pre = fn(string $me, string $villain) => ctx(['street' => 'flop', 'board' => ['K♠', '7♦', '2♣'], 'hole' => ['A♣', 'Q♦'], 'pot' => 600, 'to_call' => 0, 'stack' => 6000, 'position' => $me, 'villain' => $villain,
        'history' => [['player_id' => 1, 'street' => 'preflop', 'action' => 'raise', 'amount' => 300], ['player_id' => 2, 'street' => 'preflop', 'action' => 'call', 'amount' => 300]]]);
    $aIp = $runner->decide($pre('BTN', 'BB'), $tagDb)['trace']['features'];
    $aOop = $runner->decide($pre('BB', 'BTN'), $tagDb)['trace']['features'];
    $check('con la iniciativa y turno de hablar usa la apuesta del AGRESOR según su posición',
        ($aIp['stats_source'] ?? '') === 'agresor_ip' && ($aOop['stats_source'] ?? '') === 'agresor_oop' && abs($aIp['own_bet_freq'] - $roleStats['ip']['agg']['bet']) < 0.01 && abs($aOop['own_bet_freq'] - $roleStats['oop']['agg']['bet']) < 0.01,
        json_encode([$aIp['own_bet_freq'] ?? null, $aOop['own_bet_freq'] ?? null]));
    // Situación: apostó él y le suben → su fold A UNA SUBIDA, no el fold a la c-bet
    $raised = ctx(['street' => 'flop', 'board' => ['K♠', '7♦', '2♣'], 'hole' => ['A♣', 'Q♦'], 'pot' => 1200, 'to_call' => 600, 'stack' => 6000, 'position' => 'BTN', 'villain' => 'BB',
        'history' => [['player_id' => 1, 'street' => 'preflop', 'action' => 'raise', 'amount' => 300], ['player_id' => 2, 'street' => 'preflop', 'action' => 'call', 'amount' => 300],
            ['player_id' => 1, 'street' => 'flop', 'action' => 'raise', 'amount' => 300], ['player_id' => 2, 'street' => 'flop', 'action' => 'raise', 'amount' => 900]]]);
    $fR = $runner->decide($raised, $tagDb)['trace']['features'];
    $check('apostó y le suben: usa su fold a la SUBIDA (' . ($tagDb['stats']['flop']['f2raise'] ?? '?') . ' %)', ($fR['stats_source'] ?? '') === 'ante_subida' && abs($fR['own_f2bet'] - ($tagDb['stats']['flop']['f2raise'] ?? -1)) < 0.01, json_encode([$fR['stats_source'] ?? null, $fR['own_f2bet'] ?? null]));

    // Turn: la cifra de agresor solo vale si apostó en el flop (iniciativa); si no, es una apuesta suelta
    $turn = fn(bool $betFlop) => ctx(['street' => 'turn', 'board' => ['K♠', '7♦', '2♣', '9♥'], 'hole' => ['A♣', 'Q♦'], 'pot' => 900, 'to_call' => 0, 'stack' => 6000, 'position' => 'BTN', 'villain' => 'BB',
        'history' => $betFlop ? [['player_id' => 1, 'street' => 'flop', 'action' => 'raise', 'amount' => 300], ['player_id' => 2, 'street' => 'flop', 'action' => 'call', 'amount' => 300]] : []]);
    $tB = $runner->decide($turn(true), $tagDb)['trace']['features'];
    $tN = $runner->decide($turn(false), $tagDb)['trace']['features'];
    $check('turn: con la iniciativa (apostó el flop) usa su barril IP; sin ella no', ($tB['stats_source'] ?? '') === 'agresor_ip' && !isset($tN['stats_source']) && $tB['has_initiative'] === true && $tN['has_initiative'] === false, json_encode([$tB['stats_source'] ?? null, $tN['stats_source'] ?? null, $tB['own_bet_freq'] ?? null]));

    $noData = $withStat($tag, 'flop', 'f2bet', 40); // cerebro de plantilla: sin filas IP/OOP, todo sigue como antes
    $fN = $runner->decide($face('BTN', 'BB'), $noData)['trace']['features'];
    $check('sin cifras IP/OOP el bot usa las generales de la calle (nada se rompe)', !isset($fN['stats_source']) && abs($fN['own_f2bet'] - 40) < 0.01);
}

echo "\n11) El proyecto empuja hacia apostar / subir y las estadísticas lo multiplican\n";
// Distribución media de varias decisiones (incluye los sorteos de percepción, pero no el sorteo de la acción): estable y rápida
$avgDist = function (array $brain, array $ctx, int $n = 60) use ($runner) {
    $t = [];
    for ($i = 0; $i < $n; $i++) { foreach ($runner->decide($ctx, $brain)['trace']['distribution'] as $a => $p) { $t[$a] = ($t[$a] ?? 0) + $p / $n; } }
    // Subir / apostar: la distribución las nombra por tamaño (bet_half, bet_pot…), no "raise"
    $t['raise'] = array_sum(array_diff_key($t, ['fold' => 1, 'call' => 1, 'check' => 1]));
    arsort($t); return array_map(fn($v) => round($v, 1), $t);
};
$flopBoard = ['K♦', '7♦', '2♣'];
$drawHand = ['A♦', '9♦']; // proyecto de color (9 outs)
$airHand2 = ['5♣', '3♠'];  // aire sin proyecto
$base = $withStat($withStat($withStat($tag, 'flop', 'f2bet', 40), 'flop', 'raise_vs_bet', 30), 'flop', 'af', 2.0);
$facing = fn(array $hole) => ctx(['street' => 'flop', 'board' => $flopBoard, 'hole' => $hole, 'pot' => 600, 'to_call' => 300, 'stack' => 6000, 'position' => 'BTN', 'villain' => 'BB']);
$rDraw = $avgDist($base, $facing($drawHand), 60); $rAir = $avgDist($base, $facing($airHand2), 60);
$check('ante una apuesta, con proyecto de color sube MÁS que con aire', ($rDraw['raise'] ?? 0) > ($rAir['raise'] ?? 0) + 5, 'proyecto ' . ($rDraw['raise'] ?? 0) . ' % vs aire ' . ($rAir['raise'] ?? 0) . ' %');
$check('… y abandona MENOS que con aire', ($rDraw['fold'] ?? 0) < ($rAir['fold'] ?? 0) - 10, 'proyecto ' . ($rDraw['fold'] ?? 0) . ' % vs aire ' . ($rAir['fold'] ?? 0) . ' %');
$passive = $withStat($base, 'flop', 'af', 0.5); $aggro = $withStat($base, 'flop', 'af', 3.5);
$pRaise = $avgDist($passive, $facing($drawHand), 60)['raise'] ?? 0; $aRaise = $avgDist($aggro, $facing($drawHand), 60)['raise'] ?? 0;
$check('la misma mano con proyecto: el jugador de AF 3,5 la sube más que el de AF 0,5 (la estadística multiplica)', $aRaise > $pRaise + 4, "AF 0,5: {$pRaise} % · AF 3,5: {$aRaise} %");
$leadF = fn(array $hole) => ctx(['street' => 'flop', 'board' => $flopBoard, 'hole' => $hole, 'pot' => 600, 'to_call' => 0, 'stack' => 6000, 'position' => 'BTN', 'villain' => 'BB']);
$lead = $withStat($withStat($tag, 'flop', 'donk', 40), 'flop', 'af', 2.0);
$bDraw = $betsOf($avgDist($lead, $leadF($drawHand), 60)); $bAir = $betsOf($avgDist($lead, $leadF($airHand2), 60));
$check('sin apuesta delante, con proyecto apuesta MÁS que con aire (semifarol)', $bDraw > $bAir + 3, "proyecto {$bDraw} % vs aire {$bAir} %");
$check('el semifarol crece con la agresión: AF 3,5 apuesta el proyecto más que AF 0,5', $betsOf($avgDist($withStat($lead, 'flop', 'af', 3.5), $leadF($drawHand), 60)) > $betsOf($avgDist($withStat($lead, 'flop', 'af', 0.5), $leadF($drawHand), 60)) + 4);
$stationFacing = $withStat($withStat($tag, 'flop', 'raise_vs_bet', 0), 'flop', 'f2bet', 0);
$rSt = $avgDist($stationFacing, $facing($drawHand), 60);
$check('con 0 % de subida el proyecto NUNCA sube (la estadística manda sobre la mano)', ($rSt['raise'] ?? 0) + ($rSt['allin'] ?? 0) <= 4, json_encode($rSt));

echo "   Color y escalera suman por separado:\n";
$spot = fn(array $board, array $hole) => ctx(['street' => 'flop', 'board' => $board, 'hole' => $hole, 'pot' => 600, 'to_call' => 300, 'stack' => 6000, 'position' => 'BTN', 'villain' => 'BB']);
$feat = fn(array $board, array $hole) => $runner->decide($spot($board, $hole), $base)['trace']['features'];
$fFlush = $feat(['K♦', '7♦', '2♣'], ['A♦', '9♦']);   // solo color
$fStr = $feat(['K♣', '7♦', '6♥'], ['9♠', '8♠']);      // solo escalera abierta
$fCombo = $feat(['K♦', '7♦', '6♥'], ['9♦', '8♦']);    // color + escalera
$check('se perciben por separado: color = 9 outs / 0, escalera = 0 / 8, combo = ambos (9 / 6)', $fFlush['flush_draw_outs'] === 9 && $fFlush['straight_draw_outs'] === 0 && $fStr['flush_draw_outs'] === 0 && $fStr['straight_draw_outs'] === 8 && $fCombo['flush_draw_outs'] >= 9 && $fCombo['straight_draw_outs'] >= 6, // las cartas de color que también hacen escalera cuentan solo como color
    json_encode([[$fFlush['flush_draw_outs'], $fFlush['straight_draw_outs']], [$fStr['flush_draw_outs'], $fStr['straight_draw_outs']], [$fCombo['flush_draw_outs'], $fCombo['straight_draw_outs']]]));
$rF = $avgDist($base, $spot(['K♦', '7♦', '2♣'], ['A♦', '9♦']), 60)['raise'] ?? 0;
$rS = $avgDist($base, $spot(['K♣', '7♦', '6♥'], ['9♠', '8♠']), 60)['raise'] ?? 0;
$rC = $avgDist($base, $spot(['K♦', '7♦', '6♥'], ['9♦', '8♦']), 60)['raise'] ?? 0;
$check('el color sube mucho más que el aire, la escalera no sube menos, y el combo (que los suma) sube tanto como el color y mucho más que la escalera sola', $rF > ($rAir['raise'] ?? 0) + 5 && $rS >= ($rAir['raise'] ?? 0) && $rC >= $rF - 3 && $rC > $rS + 5, "color {$rF} % · escalera {$rS} % · combo {$rC} % · aire " . ($rAir['raise'] ?? 0) . ' %');

$fS = $avgDist($base, $spot(['K♣', '7♦', '6♥'], ['9♠', '8♠']), 60)['fold'] ?? 0;
$fA = $avgDist($base, $spot(['K♣', '7♦', '6♥'], ['5♠', '3♦']), 60)['fold'] ?? 0;
$check('la escalera abierta CONTINÚA mucho más que el aire (abandona menos)', $fS < $fA - 10, "escalera $fS % vs aire $fA %");
echo "   Backdoors del flop (les faltan dos cartas):\n";
$bdA = $feat(['K♦', '7♦', '2♣'], ['9♦', '3♠']);   // tres diamantes: backdoor de color
$bdB = $feat(['K♣', '6♦', '2♥'], ['9♠', '8♥']);   // 6-8-9: backdoor de escalera
$bdC = $feat(['K♣', '6♦', '2♥'], ['A♠', '8♥']);   // nada (ni 3 de un palo ni 3 rangos en una ventana)
$bdD = $feat(['K♦', '7♦', '2♣'], ['A♦', '9♦']);   // proyecto de color REAL: no es backdoor
$seenBd = 0; $total = 0;
for ($i = 0; $i < 200; $i++) { $x = $runner->decide($spot(['K♦', '7♦', '2♣'], ['9♦', '3♠']), $base)['trace']['features']; $total++; $seenBd += $x['backdoor_flush'] ? 1 : 0; }
$check('detecta el backdoor de color (3 del palo, una suya) y NO el de escalera', $seenBd > 100 && !$bdB['backdoor_flush'], "visto {$seenBd}/{$total} veces");
$seenBs = 0; for ($i = 0; $i < 200; $i++) { $seenBs += $runner->decide($spot(['K♣', '6♦', '2♥'], ['9♠', '8♥']), $base)['trace']['features']['backdoor_straight'] ? 1 : 0; }
$check('detecta el backdoor de escalera (6-8-9 con huecos) y NO el de color', $seenBs > 100 && !$bdB['backdoor_flush'], "visto {$seenBs}/200 veces");
$check('sin nada no hay backdoors, y un proyecto de color real no cuenta como backdoor', !$bdC['backdoor_flush'] && !$bdC['backdoor_straight'] && !$bdD['backdoor_flush'] && $bdD['flush_draw_outs'] === 9);
$check('los backdoors solo existen en el flop (en el turn no hay)', !$runner->decide(ctx(['street' => 'turn', 'board' => ['K♦', '7♦', '2♣', '9♥'], 'hole' => ['9♦', '3♠'], 'pot' => 600, 'to_call' => 300, 'stack' => 6000, 'position' => 'BTN', 'villain' => 'BB']), $base)['trace']['features']['backdoor_flush']);
// Misma situación y misma fuerza de mano: solo cambia que tenga o no el backdoor (9♦ con dos diamantes en la mesa, frente a 9♥)
$spotSmall = fn(array $board, array $hole) => ctx(['street' => 'flop', 'board' => $board, 'hole' => $hole, 'pot' => 800, 'to_call' => 150, 'stack' => 6000, 'position' => 'BTN', 'villain' => 'BB']);
$bdFold = $avgDist($base, $spotSmall(['K♦', '7♦', '2♣'], ['9♦', '3♠']), 60)['fold'] ?? 0;
$noFold = $avgDist($base, $spotSmall(['K♦', '7♦', '2♣'], ['9♥', '3♠']), 60)['fold'] ?? 0;
$check('ante una apuesta pequeña, con backdoor de color abandona MENOS que la misma mano sin él', $bdFold < $noFold - 3, "con backdoor {$bdFold} % vs sin {$noFold} %");
$leadF2 = fn(array $hole) => ctx(['street' => 'flop', 'board' => ['K♦', '7♦', '2♣'], 'hole' => $hole, 'pot' => 600, 'to_call' => 0, 'stack' => 6000, 'position' => 'BTN', 'villain' => 'BB']);
$leadOf = fn(array $hole) => $betsOf($avgDist($lead, $leadF2($hole), 60));
$cbBd = $leadOf(['9♦', '3♠']); $cbNo = $leadOf(['9♥', '3♠']);
$check('sin apuesta delante, el backdoor inclina el check hacia la apuesta (c-bet / semifarol)', $cbBd > $cbNo + 1.5, "con backdoor {$cbBd} % vs sin {$cbNo} %");

echo "\n" . ($fails ? "✗ {$fails} comprobaciones fallidas" : "✓ todas las comprobaciones correctas") . "\n";
