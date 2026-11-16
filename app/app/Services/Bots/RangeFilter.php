<?php

namespace App\Services\Bots;

use App\Models\Bot;
use App\Models\Hand;
use App\Models\HandActionRange;
use App\Models\TournamentPlayer;
use App\Services\Bots\Brain\RivalPreflopTables;

/**
 * Filtra y actualiza rangos dinámicos de bots durante la mano.
 *
 * Partiendo de las tablas preflop/postflop iniciales, genera rangos temporales
 * que se actualizan según las acciones y la evolución de la mano.
 *
 * Los rangos se almacenan en hand_action_ranges y se mostrarán en el cliente
 * para ayudar al jugador a entender qué manos jugaría cada bot.
 */
class RangeFilter
{
    public function __construct(
        private RivalPreflopTables $preflopTables,
    ) {}

    /**
     * Inicializa los rangos para un jugador al inicio de la mano
     */
    public function initializeHandRanges(Hand $hand, TournamentPlayer $player): void
    {
        $bot = $player->bot;
        if (!$bot) return; // Solo para bots

        // Crear entrada inicial con los rangos preflop del bot
        $ranges = $this->getPreflopRanges($bot, $player->position ?? 'BTN');

        HandActionRange::updateOrCreate(
            [
                'hand_id' => $hand->id,
                'player_id' => $player->id,
                'street' => 'preflop',
                'sequence' => 'initial',
            ],
            [
                'ranges' => $ranges,
                'metadata' => [
                    'position' => $player->position,
                    'stack' => $player->stack,
                ],
            ]
        );
    }

    /**
     * Actualiza el rango tras una acción del bot (fold, call, raise, etc.)
     */
    public function updateAfterAction(
        Hand $hand,
        TournamentPlayer $player,
        string $action,
        string $street,
        array $context = []
    ): void {
        $bot = $player->bot;
        if (!$bot) return;

        // Cargar el rango anterior
        $previous = HandActionRange::where([
            'hand_id' => $hand->id,
            'player_id' => $player->id,
            'street' => $street,
        ])->latest('id')->first();

        $previousRanges = $previous?->ranges ?? $this->getPreflopRanges($bot, $player->position ?? 'BTN');

        // Filtrar el rango según la acción tomada
        $filteredRanges = $this->filterByAction($previousRanges, $action, $context);

        // Generar sequence identifier
        $sequence = $this->generateSequence($street, $action, $context);

        // Guardar el rango actualizado
        HandActionRange::create([
            'hand_id' => $hand->id,
            'player_id' => $player->id,
            'street' => $street,
            'sequence' => $sequence,
            'ranges' => $filteredRanges,
            'metadata' => array_merge($context, [
                'action' => $action,
                'timestamp' => now(),
            ]),
        ]);
    }

    /**
     * Obtiene los rangos preflop iniciales de un bot para una posición
     */
    private function getPreflopRanges(Bot $bot, string $position): array
    {
        $preflop = $bot->preflopHandRanges()
            ->where('position', $position)
            ->get()
            ->mapWithKeys(fn($r) => [
                $r->hand => [
                    'open' => round($r->attack_open),
                    'raise' => round($r->open_reraise),
                    'call' => round($r->open_call),
                    'fold' => round($r->open_fold),
                    'limp' => round($r->attack_limp),
                    'defense_3bet' => round($r->defense_three_bet),
                    'defense_call' => round($r->defense_call),
                    'defense_fold' => round($r->defense_fold),
                ],
            ])
            ->toArray();

        return $preflop ?: [];
    }

    /**
     * Filtra el rango según la acción tomada
     *
     * Ejemplo: si el bot hace RAISE, elimina manos con % de raise bajo
     */
    private function filterByAction(array $ranges, string $action, array $context): array
    {
        if (empty($ranges)) {
            return $ranges;
        }

        return array_reduce(
            array_keys($ranges),
            function ($result, $hand) use ($ranges, $action, $context) {
                $handData = $ranges[$hand];

                // Mantener la mano en el rango si tiene probabilidad de hacer esa acción
                // excepto si es 0% (no la jugaría nunca)
                $actionKey = $this->mapActionToKey($action);
                $frequency = (float) ($handData[$actionKey] ?? 0);

                if ($frequency > 0 || $this->isExceptionAction($action, $hand, $context)) {
                    // Recalcular distribución (normalizar según acciones posibles)
                    $result[$hand] = $this->normalizeFrequencies($handData, $actionKey);
                }

                return $result;
            },
            []
        );
    }

    /**
     * Mapea acciones (fold, call, raise) a las claves en la tabla de rangos
     */
    private function mapActionToKey(string $action): string
    {
        return match ($action) {
            'fold' => 'fold',
            'call' => 'call',
            'raise', 'bet', 'allin' => 'raise',
            'check' => 'check',
            default => 'call',
        };
    }

    /**
     * Verifica si una mano hace una acción incluso con baja probabilidad
     * (ej: AA haciendo fold solo si requiere ICM desesperado)
     */
    private function isExceptionAction(string $action, string $hand, array $context): bool
    {
        if ($action !== 'fold') {
            return false;
        }

        // AA, KK, QQ nunca foldearan en situaciones normales
        $premiumHands = ['AA', 'KK', 'QQ'];
        if (in_array($hand, $premiumHands, true)) {
            return false;
        }

        // Si hay situación de ICM crítico, permitir fold incluso de manos buenas
        $icmCritical = ($context['icm_critical'] ?? false) === true;
        return !$icmCritical;
    }

    /**
     * Normaliza frecuencias centradas en la acción tomada
     */
    private function normalizeFrequencies(array $handData, string $primaryAction): array
    {
        // Mantener distribución pero enfatizar la acción primaria
        $handData[$primaryAction] = max(
            $handData[$primaryAction] ?? 0,
            60 // Mínimo 60% si tomó esa acción
        );

        return $handData;
    }

    /**
     * Genera un identificador de secuencia para la acción
     */
    private function generateSequence(string $street, string $action, array $context): string
    {
        $actionCount = $context['action_number'] ?? 1;
        $actors = $context['actors_count'] ?? 1;

        return "{$street}_{$action}_{$actionCount}";
    }

    /**
     * Obtiene el rango actual de un jugador para mostrar en UI
     */
    public function getCurrentRange(Hand $hand, TournamentPlayer $player): ?array
    {
        $latest = HandActionRange::where([
            'hand_id' => $hand->id,
            'player_id' => $player->id,
        ])->latest('id')->first();

        return $latest?->ranges;
    }
}
