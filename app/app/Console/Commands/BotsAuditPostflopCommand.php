<?php

namespace App\Console\Commands;

use App\Models\Bot;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Audita las decisiones POSTFLOP de los bots en el histórico de torneos (hand_actions con su registro de decisión):
 *
 *  1. Frecuencias reales vs estadísticas del perfil (de PokerTracker): apuesta de continuación, fold ante apuesta por calle.
 *  2. Fugas por decisión, con la equity que el propio bot estimaba:
 *       - pagar con menos equity de la necesaria,
 *       - foldear con equity muy por encima de la necesaria,
 *       - pagar con mano débil contra un rival que lleva varias calles apostando,
 *       - faroles (subidas con mano sin pareja ni proyecto).
 *  3. Las peores decisiones por pérdida de EV.
 *
 * Con pocas manos los porcentajes no dicen nada: solo se comparan nodos con --min-samples decisiones. Los casos sueltos
 * (fugas y peores decisiones) sí sirven desde la primera mano.
 */
class BotsAuditPostflopCommand extends Command
{
    protected $signature = 'bots:audit-postflop
        {--tournament= : id de un torneo (por defecto, todos)}
        {--only-simulated : solo torneos del simulador sin cabeza}
        {--min-samples=15 : decisiones mínimas de un nodo para compararlo con la estadística}
        {--gap=15 : diferencia (puntos) observado/estadística a partir de la cual se marca}
        {--worst=10 : peores decisiones a listar}';

    protected $description = 'Audita las decisiones postflop de los bots en el histórico (frecuencias vs estadísticas, fugas y peores decisiones)';

    /** Acciones agresivas (apostar/subir/all-in) y pasivas */
    private const AGGRESSIVE = ['raise', 'bet', 'allin'];

    public function handle(): int
    {
        $rows = $this->decisions();
        if ($rows->isEmpty()) {
            $this->warn('No hay decisiones postflop de bots registradas.');

            return self::SUCCESS;
        }

        $this->info($rows->count() . ' decisiones postflop de ' . $rows->pluck('name')->map(fn($n) => $this->profile($n))->unique()->count() . ' perfiles, en ' . $rows->pluck('hand_id')->unique()->count() . ' manos.');

        $this->frequencies($rows);
        $this->leaks($rows);
        $this->worst($rows);

        return self::SUCCESS;
    }

    /** @return Collection<int, object> */
    private function decisions(): Collection
    {
        $query = DB::table('hand_actions as a')
            ->join('tournament_players as p', 'p.id', '=', 'a.tournament_player_id')
            ->join('tournament_hands as h', 'h.id', '=', 'a.tournament_hand_id')
            ->where('p.is_bot', true)
            ->where('a.street', '<>', 'preflop')
            ->whereNotNull('a.decision')
            ->orderBy('a.id')
            ->selectRaw("a.id, a.street, a.type, a.to_call, a.pot_before, a.equity as real_equity, a.required_equity, a.ev_diff, a.ev_verdict,
                p.name, p.bot_id, h.id as hand_id, h.hand_number,
                a.decision::jsonb #>> '{context,me,hole_cards}' as hole,
                a.decision::jsonb #>> '{context,board}' as board,
                a.decision::jsonb #>> '{brain,features,facing_bet}' as facing_bet,
                a.decision::jsonb #>> '{brain,features,is_preflop_aggressor}' as pf_aggressor,
                a.decision::jsonb #>> '{brain,features,equity}' as est_equity,
                a.decision::jsonb #>> '{brain,features,range_equity}' as range_equity,
                a.decision::jsonb #>> '{brain,features,bluff_likelihood}' as bluff_likelihood,
                a.decision::jsonb #>> '{brain,features,perceived_rank}' as perceived_rank,
                a.decision::jsonb #>> '{brain,features,agg_barrels}' as agg_barrels,
                a.decision::jsonb #>> '{brain,features,made}' as made,
                a.decision::jsonb #>> '{brain,features,draw_type}' as draw,
                a.decision::jsonb #>> '{brain,distribution}' as distribution");

        if ($this->option('only-simulated')) {
            $query->join('tournaments as t', 't.id', '=', 'h.tournament_id')->whereRaw("(t.config::jsonb ->> 'simulated') = 'true'");
        }
        if ($this->option('tournament')) {
            $query->where('h.tournament_id', (int) $this->option('tournament'));
        }

        return $query->get();
    }

    /** Perfil de un jugador: su nombre sin el número con el que se distingue cuando el perfil se repite en un torneo ("Pool 5 · 48/1 (W) 10" → "Pool 5 · 48/1 (W)"). */
    private function profile(string $name): string
    {
        return preg_replace('/ \d+$/', '', $name);
    }

    private function isAggressive(object $row): bool
    {
        return in_array($row->type, self::AGGRESSIVE, true);
    }

    private function facingBet(object $row): bool
    {
        return $row->facing_bet === 'true' || (float) $row->to_call > 0;
    }

    /** Equity estimada de la decisión: contra el rango del rival (lo que usan las reglas de pagar/abandonar); si falta, la general. */
    private function est(object $row): ?float
    {
        $value = $row->range_equity ?? $row->est_equity;

        return $value === null ? null : (float) $value;
    }

    private function pct(int $n, int $d): ?float
    {
        return $d > 0 ? round(100 * $n / $d, 1) : null;
    }

    // ------------------------------------------------------------------ 1. frecuencias vs estadísticas

    private function frequencies(Collection $rows): void
    {
        $min = (int) $this->option('min-samples');
        $gap = (float) $this->option('gap');
        $table = [];
        $flags = [];

        foreach ($rows->groupBy(fn($r) => $this->profile($r->name)) as $name => $mine) {
            $stats = $this->stats($mine->first()->bot_id);
            $cbetOpp = $mine->filter(fn($r) => $r->street === 'flop' && ! $this->facingBet($r) && $r->pf_aggressor === 'true');
            $cbets = $cbetOpp->filter(fn($r) => $this->isAggressive($r))->count();

            $line = [mb_substr((string) $name, 0, 26), $mine->count()];
            $nodes = [['cbet', 'cbet', $cbets, $cbetOpp->count()]];
            foreach (['flop' => 'f2cbet', 'turn' => 'f2beturn', 'river' => 'f2betriver'] as $street => $stat) {
                $facing = $mine->filter(fn($r) => $r->street === $street && $this->facingBet($r));
                $nodes[] = ["fold {$street}", $stat, $facing->filter(fn($r) => $r->type === 'fold')->count(), $facing->count()];
            }

            foreach ($nodes as [$label, $stat, $hits, $opps]) {
                $obs = $this->pct($hits, $opps);
                $expected = $stats[$stat] ?? null;
                $line[] = $opps === 0 ? '-' : sprintf('%s%% / %s (%d)', $obs, $expected === null ? '?' : round($expected), $opps);
                if ($opps >= $min && $expected !== null && abs($obs - $expected) > $gap) {
                    $flags[] = sprintf('%s · %s: juega %s%% y su estadística es %s%% (%d decisiones)', $name, $label, $obs, round($expected), $opps);
                }
            }
            $table[] = $line;
        }

        $this->newLine();
        $this->line('<info>1. Frecuencias observadas / estadística del perfil (nº de decisiones)</info>');
        $this->table(['bot', 'decisiones', 'c-bet flop', 'fold flop', 'fold turn', 'fold river'], $table);
        foreach ($flags as $flag) {
            $this->warn('  ' . $flag);
        }
        if (! $flags) {
            $this->line("  Ningún nodo con >= {$min} decisiones se desvía más de {$gap} puntos de su estadística (con pocas manos no hay nodos que comparar).");
        }
    }

    /** @return array<string, float|null> estadísticas generales del perfil (BD) */
    private function stats(?int $botId): array
    {
        static $cache = [];
        if (! $botId) {
            return [];
        }

        return $cache[$botId] ??= (function () use ($botId) {
            $row = Bot::withoutGlobalScope('active')->find($botId)?->generalStats();

            return $row ? $row->only(['cbet', 'f2cbet', 'f2beturn', 'f2betriver']) : [];
        })();
    }

    // ------------------------------------------------------------------ 2. fugas por decisión

    private function leaks(Collection $rows): void
    {
        $calls = $rows->filter(fn($r) => $r->type === 'call' && $this->est($r) !== null && (float) $r->required_equity > 0);
        $folds = $rows->filter(fn($r) => $r->type === 'fold' && $this->est($r) !== null && (float) $r->required_equity > 0);

        $badCalls = $calls->filter(fn($r) => $this->est($r) < 100 * (float) $r->required_equity - 2);
        $goodFolds = $folds->filter(fn($r) => $this->est($r) > 100 * (float) $r->required_equity + 10);
        $weakVsBarrels = $rows->filter(fn($r) => $r->type === 'call' && in_array($r->street, ['turn', 'river'], true)
            && (float) $r->perceived_rank >= 40 && (int) $r->agg_barrels >= 2);
        $bluffs = $rows->filter(fn($r) => $this->isAggressive($r) && $this->facingBet($r) && $r->made === 'high_card' && $r->draw === 'none');

        $this->newLine();
        $this->line('<info>2. Fugas por decisión (con la equity que estimaba el bot)</info>');
        $this->table(['patrón', 'casos', 'sobre', '%'], [
            ['Paga con menos equity de la necesaria', $badCalls->count(), $calls->count(), $this->pct($badCalls->count(), $calls->count()) ?? '-'],
            ['Foldea con equity > necesaria + 10', $goodFolds->count(), $folds->count(), $this->pct($goodFolds->count(), $folds->count()) ?? '-'],
            ['Paga turn/river con mano débil ante 2+ calles de apuestas', $weakVsBarrels->count(), $rows->filter(fn($r) => $r->type === 'call' && in_array($r->street, ['turn', 'river'], true))->count(), $this->pct($weakVsBarrels->count(), $rows->filter(fn($r) => $r->type === 'call' && in_array($r->street, ['turn', 'river'], true))->count()) ?? '-'],
            ['Sube ante una apuesta sin pareja ni proyecto (farol)', $bluffs->count(), $rows->filter(fn($r) => $this->isAggressive($r) && $this->facingBet($r))->count(), $this->pct($bluffs->count(), $rows->filter(fn($r) => $this->isAggressive($r) && $this->facingBet($r))->count()) ?? '-'],
        ]);

        foreach ([['Pagos con menos equity de la necesaria', $badCalls], ['Pagos débiles ante varias calles de apuestas', $weakVsBarrels]] as [$title, $set]) {
            if ($set->isNotEmpty()) {
                $this->line("  <comment>{$title}:</comment>");
                foreach ($set->take(8) as $r) {
                    $this->line('   ' . $this->describe($r));
                }
            }
        }

        if ($goodFolds->isNotEmpty()) {
            $this->line('  <comment>Folds con equity muy por encima de la necesaria:</comment>');
            foreach ($goodFolds->take(8) as $r) {
                $this->line('   ' . $this->describe($r));
            }
        }
    }

    // ------------------------------------------------------------------ 3. peores decisiones

    private function worst(Collection $rows): void
    {
        $worst = $rows->filter(fn($r) => $r->ev_diff !== null && (int) $r->ev_diff < 0)->sortBy('ev_diff')->take((int) $this->option('worst'));

        $this->newLine();
        $this->line('<info>3. Peores decisiones por pérdida de EV (fichas)</info>');
        if ($worst->isEmpty()) {
            $this->line('  Ninguna decisión con EV negativo registrado.');

            return;
        }
        foreach ($worst as $r) {
            $this->line(sprintf('  %6d  %s', $r->ev_diff, $this->describe($r)));
        }
    }

    private function describe(object $r): string
    {
        $equityText = $this->est($r) !== null ? sprintf('equity vs su rango %d%% (general %s%%) / necesaria %d%% / real %s%%', round($this->est($r)), $r->est_equity === null ? '?' : round((float) $r->est_equity), round(100 * (float) $r->required_equity), $r->real_equity === null ? '?' : round(100 * (float) $r->real_equity)) : '';
        $hole = implode(' ', (array) json_decode((string) $r->hole, true));
        $board = implode(' ', (array) json_decode((string) $r->board, true));

        return sprintf('mano %d · %s · %s %s · %s [%s] sobre %s · %s', $r->hand_number, $r->name, $r->street, $r->type, $hole, $r->made ?? '?', $board, $equityText);
    }
}
