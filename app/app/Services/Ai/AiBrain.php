<?php

namespace App\Services\Ai;

use App\Models\Bot;
use App\Services\Bots\Brain\ActionCatalog;
use App\Services\Bots\Brain\BrainRepository;

/**
 * Decide por un bot con la IA en las calles que tienen servicio (config/ai.php → streets). Mismo formato de salida que BrainRunner.
 *
 * Añadir una calle: servicio en python/streets (ver streets/base.py) + la calle en config('ai.streets'). El resto no cambia.
 */
class AiBrain
{
    public function __construct(
        private DecisionContract $contract,
        private ActionCatalog $catalog,
        private AiClient $client,
        private FlopRanges $ranges,
        private BrainRepository $brains,
    ) {
    }

    /** Cuánto se adapta este bot al rival según su habilidad (nivel de su cerebro); sin cerebro conocido, el valor por defecto. */
    public function adaptationFor(array $ctx): float
    {
        $levels = (array) config('ai.adaptation');
        try {
            $tier = $this->brains->forPlayer((int) $ctx['me']['id'])['skill']['tier'] ?? null;
        } catch (\Throwable) {
            $tier = null;
        }

        return (float) ($levels[$tier] ?? $levels['default'] ?? 0.5);
    }

    /** ¿Decide la IA esta decisión? Calle cubierta y bot con motor 'ai'. */
    public function covers(array $ctx): bool
    {
        return in_array($ctx['street'] ?? '', (array) config('ai.streets'), true)
            && $this->engineFor($ctx['me']['bot_id'] ?? null) === 'ai';
    }

    public function engineFor(?int $botId): string
    {
        $engine = $botId ? (((array) Bot::find($botId)?->metadata)['engine'] ?? null) : null;

        return in_array($engine, ['rules', 'ai'], true) ? $engine : (string) config('ai.engine');
    }

    /** @return array{action:string, amount:int, reason:string, flags:array, trace:array} */
    public function decide(array $ctx, ?int $seed = null): array
    {
        $seed ??= random_int(1, 2147483646);
        $state = $this->contract->fromBotContext($ctx, $this->ranges->forHand($ctx)) + ['adaptation' => $this->adaptationFor($ctx)];
        $allowed = $this->contract->allowedActions($ctx['legal'], $ctx['street']);
        if (! $allowed) {
            throw new \RuntimeException('No hay ninguna acción permitida');
        }

        $distribution = $this->contract->restrictToLegal($this->client->decide($state, $allowed, $seed), $ctx['legal']);
        if (! $distribution) {
            throw new \RuntimeException('La IA no dio probabilidad a ninguna acción legal');
        }
        $cell = $this->contract->sample($distribution, $seed);

        $resolved = $this->catalog->resolve($cell, [
            'legal' => $ctx['legal'], 'pot' => $ctx['pot'], 'to_call' => $ctx['to_call'], 'current_bet' => $ctx['current_bet'],
            'stack' => $ctx['me']['stack'], 'bet' => $ctx['me']['bet'], 'big_blind' => $ctx['big_blind'],
            'limpers' => 0, 'preflop_raises' => 0, 'flags' => $ctx['me']['flags'] ?? [],
        ]);

        $top = [];
        arsort($distribution);
        foreach (array_slice($distribution, 0, 4, true) as $a => $p) {
            $top[] = $a.' '.round($p * 100).'%';
        }

        return [
            'action' => $resolved['action'],
            'amount' => $resolved['amount'],
            'reason' => 'IA ('.$ctx['street'].'): '.implode(' · ', $top)." → {$cell}".($resolved['note'] ? " ({$resolved['note']})" : ''),
            'flags' => $resolved['flags'],
            'trace' => [
                'engine' => 'ai', 'street' => $ctx['street'], 'seed' => $seed, 'model' => $this->client->model(),
                'state' => $this->withoutRanges($state), 'allowed' => $allowed, 'distribution' => $distribution, 'draw' => ['action' => $cell],
                'resolved' => ['action' => $resolved['action'], 'amount' => $resolved['amount'], 'tag' => $resolved['tag']],
            ],
        ];
    }

    /** Los rangos son ~169 manos por jugador: en la traza de la decisión solo se deja cuántas manos tenía cada uno. */
    private function withoutRanges(array $state): array
    {
        $count = fn (?array $range) => $range === null ? null : count($range);
        $state['range_hands'] = $count($state['range'] ?? null);
        unset($state['range']);
        foreach ($state['rivals'] as &$rival) {
            $rival['range_hands'] = $count($rival['range'] ?? null);
            unset($rival['range']);
        }

        return $state;
    }
}
