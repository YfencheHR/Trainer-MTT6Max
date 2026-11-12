<?php

namespace App\Services\Bots\Brain;

use App\Models\Bot;

/**
 * Monta el cerebro EFECTIVO de un bot en capas:
 *
 *   1. PLANTILLA   habilidad (percepción / imprecisión) y reglas postflop según el tipo del bot
 *   2. PREFLOP     reglas generadas SOLAS a partir de los datos del bot (BotProfile)
 *   3. CAPA PROPIA  lo que se guarda en bots.brain (o en tournament_players.brain_overrides):
 *                    - "rules"        reglas extra del usuario (se aplican después, con prioridad 100 por defecto)
 *                    - "skill", "imprecision", "default"   ajustes que sustituyen a los de la plantilla
 *                    - "auto": {"preflop": false}   desactiva el preflop automático (si se quiere escribir a mano)
 *
 * Así regenerar el preflop (por ejemplo cuando lleguen datos nuevos) nunca borra las reglas del usuario.
 */
class BrainAssembler
{
    public const CUSTOM_PRIORITY = 100;

    public function forBot(Bot $bot): array
    {
        $base = BrainTemplates::forBot($bot);

        return $this->applyLayer($base, $bot->brain ?? []);
    }

    /** Aplica una capa propia (bot o jugador) sobre un cerebro base. */
    public function applyLayer(array $brain, array $layer): array
    {
        if (!$layer) {
            return $brain;
        }

        foreach (['skill', 'imprecision'] as $key) {
            if (isset($layer[$key])) {
                $brain[$key] = array_replace_recursive($brain[$key] ?? [], $layer[$key]);
            }
        }
        if (isset($layer['default'])) {
            $brain['default'] = $layer['default'];
        }
        if (isset($layer['name'])) {
            $brain['name'] = $layer['name'];
        }

        // Partes automáticas que se pueden desactivar
        $auto = $layer['auto'] ?? [];
        $brain['rules'] = array_values(array_filter($brain['rules'], function ($r) use ($auto) {
            if (($auto['preflop'] ?? true) === false && ($r['id'] ?? '') === 'preflop') {
                return false;
            }
            if (($auto['postflop'] ?? true) === false && ($r['id'] ?? '') === 'postflop') {
                return false;
            }

            return true;
        }));

        foreach ($layer['rules'] ?? [] as $rule) {
            $rule['priority'] = $rule['priority'] ?? self::CUSTOM_PRIORITY;
            $rule['custom'] = true;
            $brain['rules'][] = $rule;
        }

        $brain['version'] = ($brain['version'] ?? 1) + (int) ($layer['version'] ?? 0);

        return $brain;
    }
}
