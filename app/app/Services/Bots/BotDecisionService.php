<?php

namespace App\Services\Bots;

use App\Services\Bots\Brain\BrainRepository;
use App\Services\Bots\Brain\BrainRunner;

/**
 * Punto único de decisión de los bots: "¿qué hago?".
 *
 * Recibe SOLO la información que ese bot puede conocer (ver HandPlayService::botContext):
 * sus cartas, el board visible, bote, stacks y apuestas públicas, posiciones, historial de acciones,
 * estadísticas observadas de los rivales y sus acciones legales. Nunca las cartas de los rivales.
 *
 * La decisión la toma su CEREBRO (reglas configurables + habilidad): ver App\Services\Bots\Brain\BrainRunner.
 *
 * Devuelve ['action' => fold|check|call|raise|allin, 'amount' => int, 'reason' => string,
 *           'flags' => array (intenciones para el resto de la mano), 'trace' => array (para el historial)].
 */
class BotDecisionService
{
    public function __construct(private BrainRepository $brains, private BrainRunner $runner)
    {
    }

    public function decide(array $ctx): array
    {
        try {
            $brain = $this->brains->forPlayer($ctx['me']['id']);

            return $this->runner->decide($ctx, $brain);
        } catch (\Throwable $e) {
            report($e); // un cerebro roto nunca debe parar la partida
            $fallback = $this->fallback($ctx);
            $fallback['reason'] = 'FALLBACK (' . $e->getMessage() . '): ' . $fallback['reason'];

            return $fallback;
        }
    }

    /** Decisión de emergencia (aleatoria y muy simple) si el cerebro falla. */
    private function fallback(array $ctx): array
    {
        $legal = $ctx['legal'];
        $roll = random_int(1, 100);

        if ($legal['check']) {
            return ['action' => 'check', 'amount' => 0, 'reason' => 'sin apuesta que igualar → check', 'flags' => [], 'trace' => []];
        }
        if ($legal['call'] < $ctx['me']['stack'] && $roll <= 55) {
            return ['action' => 'fold', 'amount' => 0, 'reason' => "tirada {$roll} → fold", 'flags' => [], 'trace' => []];
        }

        return ['action' => 'call', 'amount' => 0, 'reason' => "tirada {$roll} → call", 'flags' => [], 'trace' => []];
    }
}
