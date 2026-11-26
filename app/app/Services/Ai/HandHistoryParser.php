<?php

namespace App\Services\Ai;

/**
 * Lee un historial de mano de torneo en texto de PokerStars (el que guarda PokerTracker en tourney_hand_histories)
 * y lo convierte en una mano estructurada: jugadores, posiciones, cartas conocidas y TODAS las acciones con el
 * estado que había justo antes de cada una (bote, apuesta que igualar, fondos), que es lo que necesita un modelo
 * para aprender «en esta situación, esta acción».
 *
 * Solo se conocen las cartas del héroe («Dealt to») y las que se enseñan en el showdown: los rivales que no llegan
 * al showdown tienen `hole_cards = null`.
 */
class HandHistoryParser
{
    private const STREET_MARKERS = ['*** HOLE CARDS ***' => 'preflop', '*** PRE-FLOP ***' => 'preflop', '*** FLOP ***' => 'flop', '*** TURN ***' => 'turn', '*** RIVER ***' => 'river'];

    /**
     * @return array|null null si el texto no es una mano de torneo válida
     */
    public function parse(string $text): ?array
    {
        $text = str_replace(["\r\n", '\n'], "\n", ltrim($text, "\xEF\xBB\xBF \n"));
        $lines = array_values(array_filter(array_map('rtrim', explode("\n", $text)), fn ($l) => $l !== ''));
        if (count($lines) < 4) {
            return null;
        }
        $ante = 0;
        if (preg_match('/^PokerStars Hand #(\d+): Tournament #(\d+).*?\((\d+)\/(\d+)\)/', $lines[0], $head)) {
            [$handId, $tourney, $sb, $bb] = [$head[1], $head[2], (int) $head[3], (int) $head[4]];
        } elseif (preg_match('/^Winamax Poker - Tournament .*?HandId: #([\w-]+) - Holdem no limit \((?:(\d+)\/)?(\d+)\/(\d+)\)/', $lines[0], $head)
            && preg_match("/^Table: '[^']*\((\d+)\)/", $lines[1], $t)) {
            // Winamax: (ciega pequeña/grande) o (ante/ciega pequeña/grande)
            [$handId, $tourney, $ante, $sb, $bb] = [$head[1], $t[1], (int) $head[2], (int) $head[3], (int) $head[4]];
        } else {
            return null;
        }
        if (! preg_match("/^Table:? '([^']+)'.*? (\d+)-max.*?Seat #(\d+) is the button/", $lines[1], $table)) {
            return null;
        }

        $hand = [
            'hand_id' => $handId, 'tourney_id' => $tourney, 'small_blind' => $sb, 'big_blind' => $bb,
            'table_size' => (int) $table[2], 'button_seat' => (int) $table[3],
            'hero' => null, 'players' => [], 'board' => [], 'actions' => [], 'winners' => [],
        ];
        if (preg_match('/- (\d{4}\/\d{2}\/\d{2} \d{2}:\d{2}:\d{2})/', $lines[0], $when)) {
            $hand['played_at'] = $when[1];
        }

        $names = [];
        $i = 2;
        for (; $i < count($lines) && preg_match('/^Seat (\d+): (.+?) \((\d+)(?: in chips)?(?:, [^)]*)?\)(.*)$/', $lines[$i], $m); $i++) {
            if (str_contains($m[4], 'out of hand') || str_contains($m[4], 'sitting out')) {
                continue; // no juega la mano
            }
            $names[$m[2]] = count($hand['players']);
            $hand['players'][] = [
                'name' => $m[2], 'seat' => (int) $m[1], 'stack' => (int) $m[3], 'position' => null, 'hole_cards' => null,
            ];
        }
        if (count($hand['players']) < 2) {
            return null;
        }
        $this->assignPositions($hand);

        $street = 'preblind';
        $board = [];
        $committed = [];   // fichas puestas en la calle actual por jugador
        $stack = [];       // fondos actuales
        $inHand = [];
        $pot = 0;
        $currentBet = 0;
        foreach ($hand['players'] as $k => $p) {
            $stack[$k] = $p['stack'];
            $committed[$k] = 0;
            $inHand[$k] = true;
        }

        // Winamax anuncia el ante en la cabecera y no lo escribe como línea: lo ponen todos los jugadores
        if ($ante > 0 && ! preg_grep('/ posts (the )?ante /', $lines)) {
            foreach ($stack as $k => $_) {
                $put = min($ante, $stack[$k]);
                $stack[$k] -= $put;
                $pot += $put;
            }
        }

        $order = 0;
        foreach (array_slice($lines, $i) as $line) {
            if (isset(self::STREET_MARKERS[$line])) {
                $street = self::STREET_MARKERS[$line]; // las ciegas ya puestas siguen contando en preflop

                continue;
            }
            if (preg_match('/^\*\*\* (FLOP|TURN|RIVER) \*\*\* (?:\[[^\]]*\] ?)?\[([^\]]+)\]/', $line, $m)) {
                $street = strtolower($m[1]);
                $committed = array_fill_keys(array_keys($committed), 0);
                $currentBet = 0;
                $board = array_merge($board, array_map(fn ($c) => $this->card($c), explode(' ', $m[2])));
                $hand['board'] = $board;

                continue;
            }
            if (str_starts_with($line, '*** SHOW DOWN') || str_starts_with($line, '*** SUMMARY')) {
                $street = 'showdown';

                continue;
            }

            // Cartas del héroe
            if (preg_match('/^Dealt to (.+) \[([^\]]+)\]$/', $line, $m) && isset($names[$m[1]])) {
                $hand['hero'] = $m[1];
                $hand['players'][$names[$m[1]]]['hole_cards'] = array_map(fn ($c) => $this->card($c), explode(' ', $m[2]));

                continue;
            }
            // Cartas enseñadas
            if (preg_match('/^(.+?):? shows \[([^\]]+)\]/', $line, $m) && isset($names[$m[1]])) {
                $hand['players'][$names[$m[1]]]['hole_cards'] = array_map(fn ($c) => $this->card($c), explode(' ', $m[2]));

                continue;
            }
            if (preg_match('/^(.+) collected (\d+) from pot/', $line, $m) && isset($names[$m[1]])) {
                $hand['winners'][$m[1]] = ($hand['winners'][$m[1]] ?? 0) + (int) $m[2];

                continue;
            }
            if (preg_match('/^Uncalled bet \((\d+)\) returned to (.+)$/', $line, $m) && isset($names[$m[2]])) {
                $k = $names[$m[2]];
                $stack[$k] += (int) $m[1];
                $pot -= (int) $m[1];
                $committed[$k] -= (int) $m[1];

                continue;
            }

            // Apuestas forzadas
            if (preg_match('/^(.+?):? posts (?:the ante|ante|small blind|big blind|small & big blinds) (\d+)/', $line, $m) && isset($names[$m[1]])) {
                $k = $names[$m[1]];
                $amt = (int) $m[2];
                $stack[$k] -= $amt;
                $pot += $amt;
                if (! str_contains($line, 'ante')) {
                    $committed[$k] += $amt;
                    $currentBet = max($currentBet, $committed[$k]);
                }

                continue;
            }

            // Acciones de juego
            if (! preg_match('/^(.+?):? (folds|checks|calls (\d+)|bets (\d+)|raises (\d+) to (\d+))( and is all-in)?/', $line, $m) || ! isset($names[$m[1]]) || $street === 'preblind') {
                continue;
            }
            $k = $names[$m[1]];
            $kind = strtok($m[2], ' ');
            $toCall = max(0, $currentBet - $committed[$k]);
            $row = [
                'order' => $order++, 'player' => $m[1], 'position' => $hand['players'][$k]['position'], 'street' => $street,
                'pot_before' => $pot, 'to_call' => $toCall, 'current_bet' => $currentBet,
                'stack_before' => $stack[$k], 'committed_before' => $committed[$k],
                'players_left' => count(array_filter($inHand)),
                'board' => $board,
            ];
            $put = 0;
            switch ($kind) {
                case 'folds':
                    $row['action'] = 'fold';
                    $inHand[$k] = false;
                    break;
                case 'checks':
                    $row['action'] = 'check';
                    break;
                case 'calls':
                    $row['action'] = 'call';
                    $put = (int) $m[3];
                    break;
                case 'bets':
                    $row['action'] = 'bet';
                    $put = (int) $m[4];
                    break;
                case 'raises':
                    $row['action'] = 'raise';
                    $put = (int) $m[6] - $committed[$k];
                    break;
            }
            $row['amount'] = $put;
            $row['all_in'] = ! empty($m[7]);
            $stack[$k] -= $put;
            $committed[$k] += $put;
            $pot += $put;
            $currentBet = max($currentBet, $committed[$k]);
            $hand['actions'][] = $row;
        }

        return $hand;
    }

    /** Posiciones según la distancia al botón entre los jugadores que reciben cartas: BTN, SB, BB y, desde el final, CO, HJ y UTG. */
    private function assignPositions(array &$hand): void
    {
        $players = $hand['players'];
        usort($players, fn ($a, $b) => $a['seat'] <=> $b['seat']);
        $seats = array_column($players, 'seat');
        $n = count($seats);
        // El botón puede ser un asiento vacío / sin jugar: se toma el siguiente asiento ocupado hacia atrás
        $btnIdx = 0;
        foreach ($seats as $idx => $seat) {
            if ($seat <= $hand['button_seat']) {
                $btnIdx = $idx;
            }
        }

        $labelFor = function (int $k) use ($n): string {
            if ($n === 2) {
                return $k === 0 ? 'SB' : 'BB'; // heads-up: el botón es la ciega pequeña
            }
            if ($k === 0) {
                return 'BTN';
            }
            if ($k === 1) {
                return 'SB';
            }
            if ($k === 2) {
                return 'BB';
            }
            $fromEnd = $n - $k; // 1 = CO, 2 = HJ, resto UTG

            return [1 => 'CO', 2 => 'HJ'][$fromEnd] ?? 'UTG';
        };

        $bySeat = [];
        foreach ($seats as $idx => $seat) {
            $bySeat[$seat] = $labelFor(($idx - $btnIdx + $n) % $n);
        }
        foreach ($hand['players'] as &$p) {
            $p['position'] = $bySeat[$p['seat']];
        }
    }

    /** «Ad» → «A♦» (el formato de cartas del motor). */
    private function card(string $c): string
    {
        static $suits = ['s' => '♠', 'h' => '♥', 'd' => '♦', 'c' => '♣'];

        return strtoupper($c[0]).($suits[strtolower($c[1])] ?? $c[1]);
    }
}
