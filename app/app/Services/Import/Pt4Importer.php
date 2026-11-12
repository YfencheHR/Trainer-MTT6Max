<?php

namespace App\Services\Import;

use App\Models\Bot;
use App\Models\BotHudStat;
use App\Services\Bots\Brain\BotProfile;
use App\Services\Bots\Brain\PreflopRanking;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * Importa datos reales de PokerTracker 4 desde una COPIA (zip o volcado .backup/.sql), sin conectar con PokerTracker:
 *
 *   1. restore()   carga el volcado en una base temporal (pt4_tmp) del PostgreSQL del proyecto
 *   2. players()   estadísticas de cada jugador rival (tourney_hand_player_statistics)
 *   3. classify()  asigna cada jugador a uno de los 9 perfiles o decide crear un bot nuevo
 *   4. write()     rellena bot_hud_stats con los datos agregados de cada bot y crea los bots nuevos
 *   5. drop()      borra la base temporal
 *
 * El jugador "hero" (el dueño de la base) se excluye: no es un rival que haya que imitar.
 */
class Pt4Importer
{
    public const TMP_DB = 'pt4_tmp';

    /** Posición de PokerTracker → posición de nuestras tablas. */
    private const POS = [0 => 'BTN', 1 => 'CO', 2 => 'MP', 3 => 'MP', 4 => 'UTG', 5 => 'UTG', 8 => 'SB', 9 => 'BB'];

    /** Escalas (qué diferencia cuenta como "una unidad" de distancia) para clasificar. */
    private const SCALE = ['vpip' => 8, 'pfr' => 6, 'three_bet' => 4, 'af' => 1.0];

    /** Distancia máxima a un perfil para considerar que el jugador "encaja" en él. */
    public const FIT = 2.0;

    /** Manos a partir de las cuales se fían las estadísticas de 3-bet y agresión de un jugador. */
    public const RELIABLE = 100;

    /** @var callable|null */
    private $log;

    private ?Connection $tmp = null;

    public function __construct(?callable $log = null)
    {
        $this->log = $log;
    }

    private function say(string $m): void
    {
        if ($this->log) {
            ($this->log)($m);
        }
    }

    // ---------------------------------------------------------------- 1. restaurar

    /** Acepta .zip (se extrae el primer archivo) o un volcado SQL en texto (.backup / .sql). Devuelve el nº de tablas. */
    public function restore(string $path): int
    {
        $sql = $path;
        $cleanup = null;
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'zip') {
            $dir = storage_path('app/import/tmp-' . uniqid());
            mkdir($dir, 0775, true);
            $this->say('Descomprimiendo el zip…');
            $this->run(['unzip', '-o', '-q', $path, '-d', $dir]);
            $files = array_values(array_filter(glob($dir . '/*'), 'is_file'));
            usort($files, fn($a, $b) => filesize($b) <=> filesize($a));
            if (!$files) {
                throw new \RuntimeException('El zip está vacío.');
            }
            $sql = $files[0];
            $cleanup = $dir;
        }

        $head = (string) file_get_contents($sql, false, null, 0, 5);
        if ($head === 'PGDMP') {
            throw new \RuntimeException('Es un volcado binario (formato custom); este importador espera el de texto que genera PokerTracker (.backup en SQL).');
        }

        $this->drop();
        DB::statement("CREATE DATABASE " . self::TMP_DB . " ENCODING 'SQL_ASCII' TEMPLATE template0 LC_COLLATE 'C' LC_CTYPE 'C'");
        $this->say('Restaurando en la base temporal (puede tardar unos minutos)…');

        $c = config('database.connections.pgsql');
        $p = new Process(['psql', '-h', $c['host'], '-p', (string) $c['port'], '-U', $c['username'], '-d', self::TMP_DB, '-q', '-f', $sql],
            null, ['PGPASSWORD' => $c['password']], null, null);
        $p->run(); // los errores de "role postgres does not exist" son inocuos (propietarios)
        if ($cleanup) {
            $this->run(['rm', '-rf', $cleanup]);
        }

        $n = (int) $this->tmp()->selectOne("select count(*) n from pg_tables where schemaname='public'")->n;
        $need = (int) $this->tmp()->selectOne("select count(*) n from pg_tables where schemaname='public' and tablename in ('player','tourney_hand_player_statistics')")->n;
        if ($need < 2) {
            throw new \RuntimeException('El archivo no parece una base de PokerTracker 4 (faltan player / tourney_hand_player_statistics).');
        }

        return $n;
    }

    public function tmp(): Connection
    {
        if (!$this->tmp) {
            $cfg = array_merge(config('database.connections.pgsql'), ['database' => self::TMP_DB]);
            $this->tmp = DB::connectUsing('pt4_tmp', $cfg, true);
        }

        return $this->tmp;
    }

    public function drop(): void
    {
        $this->tmp = null;
        DB::purge('pt4_tmp');
        DB::statement('DROP DATABASE IF EXISTS ' . self::TMP_DB . ' WITH (FORCE)');
    }

    private function run(array $cmd): void
    {
        $p = new Process($cmd);
        $p->setTimeout(null);
        $p->mustRun();
    }

    // ---------------------------------------------------------------- 2. estadísticas

    /** Expresiones SQL de cada estadística (porcentajes 0-100, agresión como ratio). */
    private function exprs(): array
    {
        $pct = fn($n, $d) => "round(100.0 * sum($n) / nullif(sum($d), 0), 2)";

        return [
            'hands' => 'count(*)',
            'vpip' => 'round(100.0 * avg(flg_vpip::int), 2)',
            'pfr' => 'round(100.0 * avg((cnt_p_raise > 0)::int), 2)',
            'af_preflop' => 'round(1.0 * sum(cnt_p_raise) / nullif(sum(cnt_p_call), 0), 2)',
            'three_bet' => $pct('flg_p_3bet::int', 'flg_p_3bet_opp::int'),
            'fold_to_3bet' => $pct("(enum_p_3bet_action = 'F')::int", 'flg_p_3bet_def_opp::int'),
            'four_bet' => $pct('flg_p_4bet::int', 'flg_p_4bet_opp::int'),
            'fold_to_4bet' => $pct("(enum_p_4bet_action = 'F')::int", 'flg_p_4bet_def_opp::int'),
            'rfi' => $pct('flg_p_open::int', 'flg_p_open_opp::int'),
            'limp' => $pct('flg_p_limp::int', 'flg_p_open_opp::int'),
            'limp_raise' => $pct("(flg_p_limp and flg_p_face_raise and cnt_p_raise > 0 and enum_allin <> 'P')::int", '(flg_p_limp and flg_p_face_raise)::int'),
            'limp_push' => $pct("(flg_p_limp and flg_p_face_raise and cnt_p_raise > 0 and enum_allin = 'P')::int", '(flg_p_limp and flg_p_face_raise)::int'),
            'call_vs_3bet' => $pct("(enum_p_3bet_action = 'C')::int", 'flg_p_3bet_def_opp::int'),
            'reraise_vs_3bet' => $pct("(enum_p_3bet_action = 'R')::int", 'flg_p_3bet_def_opp::int'),
            'squeeze' => $pct('flg_p_squeeze::int', 'flg_p_squeeze_opp::int'),
            'cold_call' => 'round(100.0 * avg(flg_p_ccall::int), 2)',
            'af_flop' => 'round(1.0 * sum(flg_f_bet::int + cnt_f_raise) / nullif(sum(cnt_f_call), 0), 2)',
            'cbet' => $pct('flg_f_cbet::int', 'flg_f_cbet_opp::int'),
            'f2cbet' => $pct("(enum_f_cbet_action = 'F')::int", 'flg_f_cbet_def_opp::int'),
            'donk' => $pct('flg_f_donk::int', 'flg_f_donk_opp::int'),
            'cr_flop' => $pct('flg_f_check_raise::int', 'flg_f_check::int'),
            'af_turn' => 'round(1.0 * sum(flg_t_bet::int + cnt_t_raise) / nullif(sum(cnt_t_call), 0), 2)',
            'beturn' => $pct('flg_t_bet::int', 'flg_t_saw::int'),
            'cr_turn' => $pct('flg_t_check_raise::int', 'flg_t_check::int'),
            'f2beturn' => $pct('flg_t_fold::int', 'flg_t_fold::int + cnt_t_call'),
            'af_river' => 'round(1.0 * sum(flg_r_bet::int + cnt_r_raise) / nullif(sum(cnt_r_call), 0), 2)',
            'betriver' => $pct('flg_r_bet::int', 'flg_r_saw::int'),
            'f2betriver' => $pct('flg_r_fold::int', 'flg_r_fold::int + cnt_r_call'),
            'wsd' => $pct('flg_showdown::int', 'flg_f_saw::int'),
            'f2raise_flop' => $pct('(flg_f_fold and flg_f_face_raise)::int', 'flg_f_face_raise::int'),
            'f2raise_turn' => $pct('(flg_t_fold and flg_t_face_raise)::int', 'flg_t_face_raise::int'),
            'float' => $pct('flg_t_float::int', 'flg_t_float_opp::int'),
            'barrel' => $pct('flg_t_cbet::int', 'flg_t_cbet_opp::int'),
            'checkcall_turn' => $pct('(flg_t_check and cnt_t_call > 0)::int', 'flg_t_check::int'),
            'shove' => "round(100.0 * avg((enum_allin <> 'N')::int), 2)",
            // Farol y ratio valor:farol del river, estimados con las apuestas que llegan a showdown (ganan = valor, pierden = farol)
            'bluff' => $pct('(flg_r_bet and flg_showdown and not flg_won_hand)::int', '(flg_r_bet and flg_showdown)::int'),
            'vb_ratio' => 'round(1.0 * sum((flg_r_bet and flg_showdown and flg_won_hand)::int) / nullif(sum((flg_r_bet and flg_showdown and not flg_won_hand)::int), 0), 2)',
        ];
    }

    private function select(): string
    {
        $out = [];
        foreach ($this->exprs() as $k => $e) {
            $out[] = "$e as $k";
        }

        return implode(",\n ", $out);
    }

    /** @return array<int, array> jugadores rivales con al menos $minHands manos, por id_player */
    public function players(int $minHands): array
    {
        $rows = $this->tmp()->select("
            select s.id_player, " . $this->select() . "
            from fresh_stats s join player p using (id_player)
            where p.player_name not like '[%'
              and s.id_player not in (select id_player from tourney_hand_player_statistics where flg_hero group by 1)
            group by s.id_player having count(*) >= ? and avg(s.flg_vpip::int) > 0 -- VPIP 0: sentado sin jugar, no es un estilo
            order by count(*) desc", [$minHands]);

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->id_player] = (array) $r;
        }

        return $out;
    }

    /** Estadísticas agregadas de un conjunto de jugadores (suma de sus manos): fila general + una por posición. */
    public function aggregate(array $ids): array
    {
        $in = '{' . implode(',', array_map('intval', $ids)) . '}';
        $general = (array) $this->tmp()->selectOne('select ' . $this->select() . ' from fresh_stats s where id_player = any(?)', [$in]);
        $byPos = [];
        // La posición de PT4 es relativa al botón: 0 = BTN, 1 = CO, 8 = ciega GRANDE, 9 = ciega PEQUEÑA; el asiento más lejano del botón es UTG
        $label = "case when position = 8 then 'BB' when position = 9 then 'SB' when position = 0 then 'BTN' when position = 1 then 'CO' when position = cnt_players - 3 then 'UTG' else 'MP' end";
        foreach ($this->tmp()->select("select $label as pos, " . $this->select() . ' from fresh_stats s where id_player = any(?) group by 1', [$in]) as $r) {
            $pos = $r->pos;
            if (!$pos) {
                continue;
            }
            $a = (array) $r;
            unset($a['position']);
            // MP/UTG de varias posiciones de PT4 se funden: media ponderada por manos
            if (isset($byPos[$pos])) {
                $o = $byPos[$pos];
                $t = $o['hands'] + $a['hands'];
                foreach (['vpip', 'pfr', 'rfi', 'cold_call'] as $k) {
                    $o[$k] = $t ? round((($o[$k] ?? 0) * $o['hands'] + ($a[$k] ?? 0) * $a['hands']) / $t, 2) : null;
                }
                $o['hands'] = $t;
                $a = $o;
            }
            $byPos[$pos] = $a;
        }

        $postflop = $this->postflopRoles($in);

        // Estadísticas generales que salen del desglose por posición / papel
        $general['cbet_ip'] = $postflop['flop']['ip']['bet'] ?? null;
        $general['cbet_oop'] = $postflop['flop']['oop']['bet'] ?? null;
        // Defensa de ciegas ante un robo: 100 − fold al robo (8 = ciega grande, 9 = ciega pequeña en PokerTracker)
        $d = $this->tmp()->selectOne("select
            round(100 - 100.0 * sum(flg_bb_steal_fold::int) filter (where position = 8) / nullif(sum(flg_blind_def_opp::int) filter (where position = 8), 0), 2) as bb,
            round(100 - 100.0 * sum(flg_sb_steal_fold::int) filter (where position = 9) / nullif(sum(flg_blind_def_opp::int) filter (where position = 9), 0), 2) as sb
            from fresh_stats where id_player = any(?)", [$in]);
        $general['bb_defense'] = $d->bb;
        $general['sb_defense'] = $d->sb;

        // Resultado en bb/100: amt_won (ya es el resultado NETO de la mano en PokerTracker) entre la ciega grande de esa mano, ×100. Es el resultado en fichas
        // (sin ICM) y con pocas manos es muy ruidoso: se guarda también la desviación para mostrar su error típico.
        $r = $this->tmp()->selectOne("select count(*) as n,
                round(100.0 * avg(s.amt_won / nullif(b.amt_bb, 0)), 2) as bb100,
                round(coalesce(stddev_samp(s.amt_won / nullif(b.amt_bb, 0)), 0), 3) as sd
            from fresh_stats s join tourney_blinds b on b.id_blinds = s.id_blinds
            where s.id_player = any(?) and b.amt_bb > 0", [$in]);
        $bb = ['value' => $r->bb100 === null ? null : (float) $r->bb100, 'n' => (int) $r->n, 'sd' => (float) $r->sd];

        return [
            'general' => $general,
            'positions' => $byPos,
            'preflop_hands' => $this->preflopHands($in, $label),
            'postflop' => $postflop,
            'bb100' => $bb,
        ];
    }

    /** Estadísticas preflop por mano inicial y posición, acumuladas sobre todas las combinaciones de palos. */
    private function preflopHands(string $in, string $label): array
    {
        $rows = $this->tmp()->select("
            select $label as pos, holecard_1 as card_1, holecard_3 as card_2,
              coalesce(sum(flg_p_open_opp::int), 0) as open_opportunities,
              coalesce(sum(flg_p_open::int), 0) as open_raises,
              coalesce(sum(flg_p_limp::int), 0) as limps,
              coalesce(sum(flg_p_3bet_opp::int), 0) as vs_open_opportunities,
              coalesce(sum(flg_p_3bet::int), 0) as three_bets,
              coalesce(sum(flg_p_3bet_def_opp::int), 0) as vs_3bet_opportunities,
              coalesce(sum((enum_p_3bet_action = 'R')::int), 0) as open_reraises,
              coalesce(sum((enum_p_3bet_action = 'C')::int), 0) as open_calls,
              coalesce(sum((enum_p_3bet_action = 'F')::int), 0) as open_folds,
              coalesce(sum((flg_p_limp and flg_p_face_raise)::int), 0) as limp_raise_opportunities,
              coalesce(sum((flg_p_limp and flg_p_face_raise and cnt_p_raise > 0 and enum_allin <> 'P')::int), 0) as limp_raises,
              coalesce(sum((flg_p_limp and flg_p_face_raise and cnt_p_raise > 0 and enum_allin = 'P')::int), 0) as limp_pushes
            from fresh_stats
            where id_player = any(?)
              and holecard_1 between 1 and 52 and holecard_3 between 1 and 52
            group by $label, holecard_1, holecard_3", [$in]);

        $ranking = new PreflopRanking();
        $out = [];
        $columns = [
            'open_opportunities',
            'open_raises',
            'limps',
            'vs_open_opportunities',
            'three_bets',
            'vs_3bet_opportunities',
            'open_reraises',
            'open_calls',
            'open_folds',
            'limp_raise_opportunities',
            'limp_raises',
            'limp_pushes',
        ];

        foreach ($rows as $row) {
            $pos = $row->pos;
            if (!$pos) {
                continue;
            }

            $code = $ranking->code([
                $this->pt4CardCode((int) $row->card_1),
                $this->pt4CardCode((int) $row->card_2),
            ]);
            $target = &$out[$pos][$code];
            $target ??= array_fill_keys($columns, 0);
            foreach ($columns as $column) {
                $target[$column] += (int) $row->{$column};
            }
            unset($target);
        }

        return $out;
    }

    /** PT4 card ids are rank-major with suits clubs, diamonds, hearts, spades. */
    private function pt4CardCode(int $card): string
    {
        $ranks = '23456789TJQKA';
        $suits = ['♣', '♦', '♥', '♠'];
        $index = $card - 1;

        return $ranks[$index % 13] . $suits[intdiv($index, 13)];
    }

    /**
     * Postflop por calle, en posición (ip) o fuera de posición (oop) y según el papel:
     *   agresor  (tenía la iniciativa): % de veces que apuesta (apuesta de continuación / barriles)
     *   defensor (se enfrenta a esa apuesta): % de fold / call / subida
     * Los "opp" son las ocasiones reales (denominadores), útiles como tamaño de muestra.
     */
    private function postflopRoles(string $in): array
    {
        $out = [];
        foreach (['flop' => 'f', 'turn' => 't', 'river' => 'r'] as $street => $s) {
            $rows = $this->tmp()->select("
                select flg_{$s}_has_position as ip,
                  coalesce(sum(flg_{$s}_cbet_opp::int), 0) as agg_n,
                  round(100.0 * sum(flg_{$s}_cbet::int) / nullif(sum(flg_{$s}_cbet_opp::int), 0), 2) as bet,
                  coalesce(sum(flg_{$s}_cbet_def_opp::int), 0) as def_n,
                  round(100.0 * sum((enum_{$s}_cbet_action = 'F')::int) / nullif(sum(flg_{$s}_cbet_def_opp::int), 0), 2) as fold,
                  round(100.0 * sum((enum_{$s}_cbet_action = 'C')::int) / nullif(sum(flg_{$s}_cbet_def_opp::int), 0), 2) as call,
                  round(100.0 * sum((enum_{$s}_cbet_action = 'R')::int) / nullif(sum(flg_{$s}_cbet_def_opp::int), 0), 2) as raise
                from fresh_stats where id_player = any(?) and flg_{$s}_saw
                group by 1", [$in]);
            foreach ($rows as $r) {
                $out[$street][$r->ip ? 'ip' : 'oop'] = (array) $r;
            }
        }

        return $out;
    }

    // ---------------------------------------------------------------- 3. clasificar

    /** VPIP / PFR de referencia de cada perfil primario (sus arquetipos). La importación NO los modifica: alimenta sus copias Pool. */
    public const PRIMARY_VPIP_PFR = [
        'setminer' => [9, 7], 'roca' => [13, 9], 'nit' => [16, 14], 'omc' => [22, 6], 'tag' => [21, 19],
        'lag' => [28, 26], 'calling_station' => [40, 15], 'maniaco' => [50, 38], 'ballena' => [60, 8],
    ];

    /** Centroides de los 9 perfiles primarios (cifras de referencia fijas). */
    public function centroids(): array
    {
        $c = [];
        foreach (Bot::whereIn('tipo', array_keys(BotProfile::DOCUMENTED))->get() as $bot) {
            $d = BotProfile::DOCUMENTED[$bot->tipo];
            [$vpip, $pfr] = self::PRIMARY_VPIP_PFR[$bot->tipo] ?? [(float) $bot->vpip, (float) $bot->pfr];
            $c[$bot->id] = [
                'bot' => $bot,
                'vpip' => (float) $vpip, 'pfr' => (float) $pfr, 'three_bet' => (float) $d['three_bet'],
                'af' => (float) (BotProfile::DOCUMENTED_POSTFLOP[$bot->tipo]['flop']['af'] ?? $d['af_preflop']),
            ];
        }

        return $c;
    }

    /** @return array{0:?int,1:float} id del bot más cercano (null si ninguno encaja) y su distancia */
    public function nearest(array $p, array $centroids): array
    {
        // Con pocas manos 3-bet y agresión son ruido: solo se clasifica por VPIP y PFR
        $feat = ['vpip' => $p['vpip'], 'pfr' => $p['pfr']];
        if ((int) $p['hands'] >= self::RELIABLE) {
            $feat += ['three_bet' => $p['three_bet'], 'af' => $p['af_flop'] ?? $p['af_preflop']];
        }
        $best = [null, INF];
        foreach ($centroids as $id => $c) {
            $s = 0;
            $n = 0;
            foreach ($feat as $k => $v) {
                if ($v === null) {
                    continue;
                }
                $s += (($v - $c[$k]) / self::SCALE[$k]) ** 2;
                $n++;
            }
            $d = $n ? sqrt($s / $n) * sqrt(4) : INF; // normalizado a 4 variables
            if ($d < $best[1]) {
                $best = [$id, $d];
            }
        }

        return [$best[1] <= self::FIT ? $best[0] : null, $best[1], $best[0]];
    }

    // ---------------------------------------------------------------- 4. escribir

    /**
     * @return array{profiles: array, created: array, players: int}
     */
    public function import(int $minHands = 20, bool $dry = false, int $botHands = 200, bool $reset = false): array
    {
        if ($reset && !$dry) {
            // Recalcular desde cero con esta copia: se olvida lo registrado y los datos se reemplazan en vez de sumarse
            DB::table('pt4_imported_hands')->truncate();
            DB::table('pt4_imported_tourneys')->truncate();
        }
        $dedupe = $this->prepare();
        $this->merge = DB::table('pt4_imported_hands')->exists(); // ya hay datos registrados: se suma en vez de reemplazar
        $this->say("Manos en la copia: {$dedupe['hands_total']} · ya importadas: {$dedupe['hands_known']} · nuevas: {$dedupe['hands_new']}");
        $this->say("Torneos en la copia: {$dedupe['tourneys_total']} · nuevos (con manos nuevas): {$dedupe['tourneys_new']}");
        if ($dedupe['hands_new'] === 0) {
            $this->say('No hay nada nuevo que importar: todas las manos de esta copia ya estaban importadas.');

            return ['profiles' => [], 'created' => [], 'players' => 0, 'dedupe' => $dedupe];
        }

        // Sin datos registrados (primera importación con registro o recálculo) se recrean los bots Pool desde cero;
        // si ya hay datos, se conservan y se actualizan. Se hace ANTES de crear las copias.
        if (!$dry && !$this->merge) {
            $old = Bot::where('tipo', 'pool')->whereIn('metadata->source', ['pt4_import', 'pt4_pool'])->pluck('id');
            BotHudStat::whereIn('bot_id', $old)->delete();
            Bot::whereIn('id', $old)->delete();
        }

        $players = $this->players($minHands);
        $this->say(count($players) . " jugadores con al menos $minHands manos");
        $centroids = $this->centroids();

        // Los jugadores con pocas manos tienen estadísticas muy ruidosas (un 0 % o un 70 % de VPIP en 20 manos casi siempre es
        // suerte): se "encogen" hacia la media de la población en proporción a las manos que tienen (media ponderada)
        $this->shrink($players);

        $groups = []; // bot_id => [ids] (encajan en un perfil primario)
        $outliers = []; // no encajan en ninguno: se agrupan entre sí
        foreach ($players as $id => $p) {
            $view = ['vpip' => $p['s_vpip'], 'pfr' => $p['s_pfr'], 'hands' => $p['hands'], 'three_bet' => $p['three_bet'], 'af_flop' => $p['af_flop'], 'af_preflop' => $p['af_preflop']];
            [$bot] = $this->nearest($view, $centroids);
            if ($bot) {
                $groups[$bot][] = $id;
            } else {
                $outliers[] = ['id' => $id, 'vpip' => $p['s_vpip'], 'pfr' => $p['s_pfr'], 'hands' => (int) $p['hands']];
            }
        }

        // Los que no encajan se agrupan por parecido (VPIP/PFR). Los grupos pequeños se funden con el más cercano; los que
        // acaban pareciéndose a un perfil primario se unen a él (así lo mejoran con sus tendencias) y el resto son perfiles nuevos
        [$clusters, $absorbed] = $this->clusterOutliers($outliers, $botHands, $centroids);
        foreach ($absorbed as $pid => $ids) {
            $groups[$pid] = array_merge($groups[$pid] ?? [], $ids);
        }
        $this->say(count($outliers) . ' jugadores no encajan en un perfil primario → ' . count($clusters) . ' perfiles nuevos (y ' . array_sum(array_map('count', $absorbed)) . ' absorbidos por un primario)');

        $report = ['profiles' => [], 'created' => [], 'players' => count($players), 'dedupe' => $dedupe];

        // Cada primario conserva sus cifras de referencia; los jugadores que se le parecen alimentan su copia Pool
        // ("TAG · Pool"), donde se ve cómo evolucionan las estadísticas y con cuántos jugadores
        $nPlayers = count($players);
        foreach ($groups as $botId => $ids) {
            $primary = $centroids[$botId]['bot'];
            $agg = $this->aggregate($ids);
            $g = $agg['general'];
            $report['profiles'][] = ['bot' => $primary->nombre . ' · Pool', 'players' => count($ids), 'hands' => $g['hands'], 'vpip' => $g['vpip'], 'pfr' => $g['pfr'], 'three_bet' => $g['three_bet']];
            if (!$dry) {
                $copy = $this->poolCopy($primary);
                $this->write($copy, $agg);
                app(\App\Services\Bots\RivalPreflopTables::class)->forBot($copy);
                $this->logHistory($copy, count($ids), $nPlayers, $g, $agg['bb100']);
            }
        }

        $i =$this->merge ? (int) Bot::where('tipo', 'pool')->where('metadata->source', 'pt4_import')->count() : 0;
        foreach ($clusters as $c) {
            $ids = $c['ids'];
            $agg = $this->aggregate($ids);
            $g = $agg['general'];
            $i++;
            $nombre = "Pool $i · " . (int) round($g['vpip']) . '/' . (int) round($g['pfr']);
            $row = ['bot' => $nombre, 'players' => count($ids), 'hands' => $g['hands'], 'vpip' => $g['vpip'], 'pfr' => $g['pfr'], 'three_bet' => $g['three_bet']];
            $report['created'][] = $row;
            if (!$dry) {
                $perfil = $this->perfil((float) $g['vpip'], (float) $g['pfr']);
                $bot = Bot::create([
                    'nombre' => $nombre,
                    'tipo' => 'pool',
                    'perfil' => $perfil,
                    'icono' => \App\Config\IconConfig::pick($perfil, Bot::whereNotNull('icono')->where('icono', '!=', '')->pluck('icono')->all()),
                    'vpip' => (int) round($g['vpip']), 'pfr' => (int) round($g['pfr']),
                    'profesional' => false,
                    'descripcion' => 'Perfil creado automáticamente desde PokerTracker: ' . count($ids) . ' jugadores parecidos (' . number_format($g['hands'], 0, ',', '.') . ' manos), '
                        . 'VPIP ' . (int) round($g['vpip']) . ' / PFR ' . (int) round($g['pfr']) . '.',
                    'metadata' => ['source' => 'pt4_import', 'players' => count($ids)],
                ]);
                $this->write($bot, $agg);
                [, , $closest] = $this->nearest($g, $centroids);
                $like = $centroids[$closest]['bot'] ?? null;
                $this->fillMissing($bot, $like);
                $bot->update(['metadata' => array_merge((array) $bot->metadata, ['template' => $like?->tipo, 'primary_id' => $like?->id])]);
                app(\App\Services\Bots\RivalPreflopTables::class)->forBot($bot);
                $this->logHistory($bot, count($ids), $nPlayers, $g, $agg['bb100']);
            }
        }
        if (!$dry) {
            $this->register();
        }

        return $report;
    }

    // ---------------------------------------------------------------- copias Pool de los primarios

    /**
     * Copia Pool de un perfil primario ("TAG · Pool"): empieza como una copia (mismas filas de estadísticas) y es la que
     * se actualiza con los datos de PokerTracker. Hereda el estilo / cerebro del primario (metadata.template).
     */
    private function poolCopy(Bot $primary): Bot
    {
        $existing = Bot::where('tipo', 'pool')->where('metadata->source', 'pt4_pool')->where('metadata->primary_id', $primary->id)->first();
        if ($existing) {
            return $existing;
        }
        $copy = Bot::create([
            'nombre' => $primary->nombre . ' · Pool',
            'tipo' => 'pool',
            'perfil' => $primary->perfil,
            'icono' => $primary->icono,
            'vpip' => $primary->vpip, 'pfr' => $primary->pfr,
            'tilt_risk' => $primary->tilt_risk,
            'profesional' => $primary->profesional,
            'descripcion' => 'Copia de ' . $primary->nombre . ' que se actualiza con los jugadores de PokerTracker que se le parecen. ' . $primary->nombre . ' conserva sus cifras de referencia.',
            'metadata' => ['source' => 'pt4_pool', 'primary_id' => $primary->id, 'template' => $primary->tipo, 'history' => []],
        ]);
        foreach ($primary->hudStats()->get() as $stat) {
            $new = $stat->replicate();
            $new->bot_id = $copy->id;
            $new->save();
        }

        return $copy;
    }

    /** Anota en metadata.history cuántos jugadores y manos han alimentado este perfil y cómo quedaron sus cifras clave. */
    private function logHistory(Bot $bot, int $players, int $total, array $g, array $bb = []): void
    {
        $meta = (array) $bot->metadata;

        // bb/100 acumulado: media ponderada por manos (y varianza combinada) si se suma a lo anterior; si no, el de esta importación
        if (!empty($bb['n']) && $bb['value'] !== null) {
            $oldN = $this->merge ? (int) ($meta['bb100_n'] ?? 0) : 0;
            $oldV = $oldN ? (float) $meta['bb100'] : 0.0;
            $oldSd = $oldN ? (float) ($meta['bb100_sd'] ?? 0) : 0.0;
            $n = $oldN + $bb['n'];
            $meta['bb100'] = round(($oldV * $oldN + $bb['value'] * $bb['n']) / $n, 2);
            $meta['bb100_sd'] = round(sqrt(($oldSd ** 2 * $oldN + $bb['sd'] ** 2 * $bb['n']) / $n), 3);
            $meta['bb100_n'] = $n;
        }
        $row = BotHudStat::where(['bot_id' => $bot->id, 'nivel' => 1])->whereNull('posicion')->whereNull('accion')->whereNull('rango_ciegas')->first();
        $history = (array) ($meta['history'] ?? []);
        $history[] = [
            'at' => now()->toDateTimeString(),
            'players' => $players,
            'share' => $total ? round($players / $total * 100, 1) : null,
            'hands_added' => (int) $g['hands'],
            'hands_total' => (int) ($row->sample_size ?? $g['hands']),
            'vpip' => $row->vpip ?? null, 'pfr' => $row->pfr ?? null, 'three_bet' => $row->three_bet ?? null,
            'bb100' => $meta['bb100'] ?? null,
        ];
        $meta['history'] = array_slice($history, -30);
        $meta['players_last'] = $players;
        $meta['share_last'] = $row ? ($history[count($history) - 1]['share']) : null;
        $bot->update(['metadata' => $meta]);
    }

    // ---------------------------------------------------------------- agrupar por parecido

    /** Manos de "peso" de la media de la población al encoger estadísticas (con 40 manos, la mitad es del jugador y la mitad de la media). */
    public const SHRINK_K = 40;

    /** Radio (en unidades de VPIP/PFR) para que un jugador entre en un grupo ya existente; más allá empieza otro. */
    public const LEADER_R = 1.0;

    /** Los grupos pequeños se funden con el más cercano si está a menos de esto; si no, con el perfil primario más parecido. */
    public const MERGE_R = 2.0;

    /** Jugadores mínimos para justificar un perfil nuevo. */
    public const MIN_PLAYERS = 3;

    /** Añade s_vpip / s_pfr: VPIP y PFR encogidos hacia la media de la población según las manos de cada jugador. */
    private function shrink(array &$players): void
    {
        $tot = max(1, array_sum(array_column($players, 'hands')));
        $mv = array_sum(array_map(fn($p) => $p['vpip'] * $p['hands'], $players)) / $tot;
        $mp = array_sum(array_map(fn($p) => $p['pfr'] * $p['hands'], $players)) / $tot;
        foreach ($players as &$p) {
            $n = (int) $p['hands'];
            $p['s_vpip'] = round(($p['vpip'] * $n + $mv * self::SHRINK_K) / ($n + self::SHRINK_K), 2);
            $p['s_pfr'] = round(($p['pfr'] * $n + $mp * self::SHRINK_K) / ($n + self::SHRINK_K), 2);
        }
    }

    private function gap(array $a, array $b): float
    {
        return sqrt((($a['vpip'] - $b['vpip']) / self::SCALE['vpip']) ** 2 + (($a['pfr'] - $b['pfr']) / self::SCALE['pfr']) ** 2);
    }

    private function mergeInto(array &$c, array $o): void
    {
        $h = $c['hands'] + $o['hands'];
        $c['vpip'] = $h ? ($c['vpip'] * $c['hands'] + $o['vpip'] * $o['hands']) / $h : $c['vpip'];
        $c['pfr'] = $h ? ($c['pfr'] * $c['hands'] + $o['pfr'] * $o['hands']) / $h : $c['pfr'];
        $c['hands'] = $h;
        $c['ids'] = array_merge($c['ids'], $o['ids']);
    }

    /**
     * Agrupa los jugadores que no encajan en ningún perfil primario.
     *
     * @param array<int, array{id:int, vpip:float, pfr:float, hands:int}> $outliers
     * @return array{0: array<int, array>, 1: array<int, array<int>>} grupos nuevos y jugadores absorbidos por un primario (id de bot → ids)
     */
    private function clusterOutliers(array $outliers, int $botHands, array $centroids): array
    {
        usort($outliers, fn($a, $b) => $b['hands'] <=> $a['hands']);

        // 1. Agrupación por radio, de más a menos manos: el centro de cada grupo es la media ponderada de sus miembros
        $cl = [];
        foreach ($outliers as $o) {
            $best = null;
            $bd = INF;
            foreach ($cl as $k => $c) {
                $d = $this->gap($o, $c);
                if ($d < $bd) {
                    [$bd, $best] = [$d, $k];
                }
            }
            $item = ['vpip' => $o['vpip'], 'pfr' => $o['pfr'], 'hands' => $o['hands'], 'ids' => [$o['id']]];
            if ($best !== null && $bd <= self::LEADER_R) {
                $this->mergeInto($cl[$best], $item);
            } else {
                $cl[] = $item;
            }
        }

        // 2. Los grupos pequeños (pocos jugadores o pocas manos) se funden con el más cercano o, si está lejos, con un primario
        $absorbed = [];
        $small = fn(array $c) => count($c['ids']) < self::MIN_PLAYERS || $c['hands'] < $botHands;
        while (true) {
            $idx = null;
            foreach ($cl as $k => $c) {
                if ($small($c) && ($idx === null || $c['hands'] < $cl[$idx]['hands'])) {
                    $idx = $k;
                }
            }
            if ($idx === null) {
                break;
            }
            $c = $cl[$idx];
            unset($cl[$idx]);
            $near = null;
            $nd = INF;
            foreach ($cl as $k => $o) {
                $d = $this->gap($c, $o);
                if ($d < $nd) {
                    [$nd, $near] = [$d, $k];
                }
            }
            if ($near !== null && $nd <= self::MERGE_R) {
                $this->mergeInto($cl[$near], $c);
            } else {
                [, , $pid] = $this->nearest(['vpip' => $c['vpip'], 'pfr' => $c['pfr'], 'hands' => 0], $centroids);
                $absorbed[$pid] = array_merge($absorbed[$pid] ?? [], $c['ids']);
            }
        }

        // 3. Un grupo que acaba pareciéndose a un perfil primario se une a él (lo mejora con sus tendencias) en vez de duplicarlo
        foreach ($cl as $k => $c) {
            [$pid] = $this->nearest(['vpip' => $c['vpip'], 'pfr' => $c['pfr'], 'hands' => 0], $centroids);
            if ($pid) {
                $absorbed[$pid] = array_merge($absorbed[$pid] ?? [], $c['ids']);
                unset($cl[$k]);
            }
        }

        return [array_values($cl), $absorbed];
    }
    // ---------------------------------------------------------------- comprobador de duplicados

    private bool $merge = false;

    /**
     * Deja en la base temporal solo lo NUEVO: fresh_hands (manos aún no importadas) y fresh_stats (sus filas de jugador).
     * Una mano se identifica por (sitio, nº de mano) y un torneo por (sitio, nº de torneo).
     */
    private function prepare(): array
    {
        $t = $this->tmp();
        $t->statement('drop table if exists known_hands');
        $t->statement('create table known_hands (id_site int, hand_no varchar(40), primary key (id_site, hand_no))');
        foreach (DB::table('pt4_imported_hands')->select('id_site', 'hand_no')->orderBy('id')->lazy(5000)->chunk(5000) as $chunk) {
            $values = $chunk->map(fn($r) => "(" . (int) $r->id_site . ",'" . str_replace("'", "''", $r->hand_no) . "')")->implode(',');
            $t->statement("insert into known_hands values $values on conflict do nothing");
        }

        $t->statement('drop table if exists fresh_stats');
        $t->statement('drop table if exists fresh_hands');
        $t->statement('create table fresh_hands as
            select h.id_hand, h.id_site, h.hand_no, ts.tourney_no
            from tourney_hand_summary h
            left join tourney_summary ts on ts.id_tourney = h.id_tourney
            left join known_hands k on k.id_site = h.id_site and k.hand_no = h.hand_no
            where k.hand_no is null');
        $t->statement('create index on fresh_hands (id_hand)');
        $t->statement('create table fresh_stats as select s.* from tourney_hand_player_statistics s join fresh_hands f using (id_hand)');
        $t->statement('create index on fresh_stats (id_player)');

        $total = (int) $t->selectOne('select count(*) n from tourney_hand_summary')->n;
        $new = (int) $t->selectOne('select count(*) n from fresh_hands')->n;

        return [
            'hands_total' => $total, 'hands_new' => $new, 'hands_known' => $total - $new,
            'tourneys_total' => (int) $t->selectOne('select count(distinct (h.id_site, ts.tourney_no)) n from tourney_hand_summary h join tourney_summary ts using (id_tourney)')->n,
            'tourneys_new' => (int) $t->selectOne('select count(distinct (id_site, tourney_no)) n from fresh_hands where tourney_no is not null')->n,
        ];
    }

    /** Anota en el registro las manos y torneos importados para que una copia posterior no los cuente otra vez. */
    private function register(): void
    {
        $t = $this->tmp();
        foreach ($t->table('fresh_hands')->select('id_site', 'hand_no', 'tourney_no')->orderBy('id_hand')->lazy(5000)->chunk(5000) as $chunk) {
            DB::table('pt4_imported_hands')->insertOrIgnore($chunk->map(fn($r) => (array) $r)->all());
        }
        foreach ($t->select('select id_site, tourney_no, count(*) n from fresh_hands where tourney_no is not null group by 1, 2') as $r) {
            DB::statement(
                'insert into pt4_imported_tourneys (id_site, tourney_no, hands) values (?, ?, ?) on conflict (id_site, tourney_no) do update set hands = pt4_imported_tourneys.hands + excluded.hands',
                [$r->id_site, $r->tourney_no, $r->n]
            );
        }
    }

    /** Media ponderada por manos de una fila ya guardada y los datos nuevos (los porcentajes se promedian por nº de manos). */
    private function blend(?BotHudStat $old, array $new, int $nNew): array
    {
        $nOld = (int) ($old->sample_size ?? 0);
        if (!$old || $nOld <= 0) {
            return $new; // los valores previos eran provisionales (sin muestra): se sustituyen
        }
        $out = [];
        $precisePercentages = ['limp', 'limp_raise', 'limp_push', 'call_vs_3bet', 'reraise_vs_3bet'];
        foreach ($new as $k => $v) {
            $o = $old->{$k};
            $out[$k] = $o === null ? $v : (\App\Config\BotStreets::isRatio($k) || in_array($k, $precisePercentages, true)
                ? round(($o * $nOld + $v * $nNew) / ($nOld + $nNew), 2)
                : (int) round(($o * $nOld + $v * $nNew) / ($nOld + $nNew)));
        }

        return $out;
    }

    /**
     * Un bot con poca muestra no tiene todas las situaciones: lo que le falta se completa con la media de su perfil
     * más parecido (que se apoya en muchas más manos). Solo rellena huecos; nunca pisa un dato propio.
     */
    private function fillMissing(Bot $bot, ?Bot $like): void
    {
        $row = $bot->generalStats();
        $src = $like?->generalStats();
        if (!$row || !$src) {
            return;
        }
        $fill = [];
        foreach (collect(\App\Config\BotStreets::STREETS)->flatMap(fn($s) => array_merge($s['stats'], $s['adaptativas'])) as $c) {
            if ($row->{$c} === null && $src->{$c} !== null) {
                $fill[$c] = $src->{$c};
            }
        }
        if ($fill) {
            $row->update($fill);
            $bot->update(['descripcion' => $bot->descripcion . " Estadísticas con poca muestra: " . implode(', ', array_keys($fill)) . " tomadas del perfil {$like->nombre}."]);
        }
    }

    private function perfil(float $vpip, float $pfr): string
    {
        $tight = $vpip < 24;
        $gap = $vpip ? $pfr / $vpip : 0;
        $agg = $gap > 0.65;

        return ($tight ? 'tight' : 'loose') . '-' . ($agg ? 'agresivo' : 'pasivo');
    }

    /** Los porcentajes HUD existentes son enteros; las nuevas frecuencias preflop conservan dos decimales. */
    private function typed(array $row): array
    {
        $out = [];
        $precisePercentages = ['limp', 'limp_raise', 'limp_push', 'call_vs_3bet', 'reraise_vs_3bet'];
        foreach ($row as $k => $v) {
            if ($v === null || $k === 'hands') {
                continue;
            }
            $out[$k] = \App\Config\BotStreets::isRatio($k) || in_array($k, $precisePercentages, true)
                ? (float) $v
                : (int) round($v);
        }

        return $out;
    }

    private function write(Bot $bot, array $agg): void
    {
        $g = $agg['general'];
        $n = (int) $g['hands'];
        $cols = array_diff_key($g, ['hands' => 1]);
        $meta = ['sample_size' => $n, 'confidence' => round($n / ($n + 300), 3)];

        $key = ['bot_id' => $bot->id, 'nivel' => 1, 'posicion' => null, 'accion' => null, 'rango_ciegas' => null];
        $old = $this->merge ? BotHudStat::where($key)->first() : null;
        $vals = $this->blend($old, $this->typed($cols), $n);
        $total = $n + (int) ($old->sample_size ?? 0);
        $meta = ['sample_size' => $total, 'confidence' => round($total / ($total + 300), 3)];

        $bot->update(['vpip' => $vals['vpip'] ?? (int) round($g['vpip']), 'pfr' => $vals['pfr'] ?? (int) round($g['pfr'])]);
        BotHudStat::updateOrCreate($key, $vals + $meta);

        // Por posición, en filas separadas por acción: son las que busca el cerebro (BotProfile::derive)
        //   opening → RFI/VPIP/PFR · vs_open → 3-bet · cold_call → call en frío · vs_3bet → fold a 3-bet y 4-bet · squeeze
        $spec = [
            'opening' => ['vpip', 'pfr', 'rfi', 'limp', 'limp_raise', 'limp_push'],
            'vs_open' => ['three_bet'],
            'cold_call' => ['cold_call'],
            'vs_3bet' => ['fold_to_3bet', 'four_bet', 'call_vs_3bet', 'reraise_vs_3bet'],
            'squeeze' => ['squeeze'],
        ];
        foreach ($agg['positions'] as $pos => $r) {
            if ($r['hands'] < 30) {
                continue; // con pocas manos el dato no vale
            }
            foreach ($spec as $action => $fields) {
                if (($action === 'opening' && $pos === 'BB') || ($action === 'cold_call' && in_array($pos, ['SB', 'BB'], true))) {
                    continue; // la BB no abre y en las ciegas no hay "call en frío"
                }
                $new = $this->typed(array_filter(array_intersect_key($r, array_flip($fields)), fn($v) => $v !== null));
                if (!$new) {
                    continue;
                }
                $pk = ['bot_id' => $bot->id, 'nivel' => 1, 'posicion' => $pos, 'accion' => $action, 'rango_ciegas' => null];
                $po = $this->merge ? BotHudStat::where($pk)->first() : null;
                $pt = (int) $r['hands'] + (int) ($po->sample_size ?? 0);
                BotHudStat::updateOrCreate($pk, $this->blend($po, $new, (int) $r['hands']) + ['sample_size' => $pt, 'confidence' => round($pt / ($pt + 300), 3)]);
            }
        }

        $this->storePreflopHandStats($bot, $agg['preflop_hands'] ?? []);

        // Postflop por calle, IP/OOP y papel. Filas sin posición de mesa: accion = "{calle}_agg_{ip|oop}" / "{calle}_def_{ip|oop}"
        //   agg → cbet (apuesta como agresor) · def → f2cbet / call_vs_bet / raise_vs_bet (respuesta como defensor)
        foreach ($agg['postflop'] ?? [] as $street => $byPos) {
            foreach ($byPos as $ip => $r) {
                foreach (['agg' => [['bet' => 'cbet'], (int) $r['agg_n']], 'def' => [['fold' => 'f2cbet', 'call' => 'call_vs_bet', 'raise' => 'raise_vs_bet'], (int) $r['def_n']]] as $role => [$map, $n]) {
                    if ($n < 30) {
                        continue;
                    }
                    $new = [];
                    foreach ($map as $src => $col) {
                        if ($r[$src] !== null) {
                            $new[$col] = (int) round($r[$src]);
                        }
                    }
                    $pk = ['bot_id' => $bot->id, 'nivel' => 1, 'posicion' => null, 'accion' => "{$street}_{$role}_{$ip}", 'rango_ciegas' => null];
                    $po = $this->merge ? BotHudStat::where($pk)->first() : null;
                    $pt = $n + (int) ($po->sample_size ?? 0);
                    BotHudStat::updateOrCreate($pk, $this->blend($po, $new, $n) + ['sample_size' => $pt, 'confidence' => round($pt / ($pt + 300), 3)]);
                }
            }
        }
    }

    /** Incremental imports add action counts rather than replacing older hand samples. */
    private function storePreflopHandStats(Bot $bot, array $stats): void
    {
        $countColumns = [
            'open_opportunities',
            'open_raises',
            'limps',
            'vs_open_opportunities',
            'three_bets',
            'vs_3bet_opportunities',
            'open_reraises',
            'open_calls',
            'open_folds',
            'limp_raise_opportunities',
            'limp_raises',
            'limp_pushes',
        ];
        $columns = array_merge(['bot_id', 'posicion', 'mano'], $countColumns, ['created_at', 'updated_at']);
        $now = now()->toDateTimeString();
        $values = [];
        foreach ($stats as $position => $hands) {
            foreach ($hands as $hand => $counts) {
                $values[] = array_merge(
                    [$bot->id, $position, $hand],
                    array_map(fn($column) => (int) ($counts[$column] ?? 0), $countColumns),
                    [$now, $now]
                );
            }
        }

        foreach (array_chunk($values, 200) as $chunk) {
            $bindings = [];
            $valueSql = [];
            foreach ($chunk as $row) {
                $valueSql[] = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
                array_push($bindings, ...$row);
            }

            $updates = array_map(
                fn($column) => "{$column} = bot_preflop_hand_stats.{$column} + excluded.{$column}",
                $countColumns
            );
            $updates[] = 'updated_at = excluded.updated_at';
            DB::statement(
                'insert into bot_preflop_hand_stats (' . implode(',', $columns) . ') values ' . implode(',', $valueSql)
                . ' on conflict (bot_id, posicion, mano) do update set ' . implode(', ', $updates),
                $bindings
            );
        }

        $bot->preflopHandRanges()->where('is_manual', false)->delete();
    }
}
