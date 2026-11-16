<?php

namespace Database\Seeders;

use App\Models\Bot;
use Illuminate\Database\Seeder;

class BotSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $bots = [
            [
                'nombre' => 'Setminer',
                'tipo' => 'setminer',
                'perfil' => 'tight-pasivo',
                'vpip' => 9,
                'pfr' => 7,
                'tilt_risk' => 0,
                'profesional' => true,
                'version_rango' => 'setminer-v001',
                'metadata' => [
                    'descripcion' => 'Entra CASI EXCLUSIVAMENTE con pares de bolsillo buscando ligar set.',
                    'play_style' => 'Pares solamente → limpia o llama pequeño raise. Si no ligan set en flop → fold',
                    'exploit' => 'Open amplio (villain no va a defender). Steal blinds constantemente.'
                ]
            ],
            [
                'nombre' => 'Roca',
                'tipo' => 'roca',
                'perfil' => 'tight-pasivo',
                'vpip' => 13,
                'pfr' => 9,
                'tilt_risk' => 0,
                'profesional' => true,
                'version_rango' => 'roca-v001',
                'metadata' => [
                    'descripcion' => 'Extremadamente conservador. Solo manos tier-1.',
                    'play_style' => 'Espera manos premium. Nunca juega marginal. Nunca bluffea.',
                    'exploit' => 'Roba blinds permanentemente. Es predecible → 3-bet sus opens.'
                ]
            ],
            [
                'nombre' => 'Nit',
                'tipo' => 'nit',
                'perfil' => 'tight-agresivo',
                'vpip' => 16,
                'pfr' => 14,
                'tilt_risk' => 5,
                'profesional' => true,
                'version_rango' => 'nit-v001',
                'metadata' => [
                    'descripcion' => 'Tight pero agresivo preflop. Foldea postflop ante presión.',
                    'play_style' => 'Abre rangos estándar. Agresivo preflop, pasivo postflop.',
                    'exploit' => '3-bet agresivo (foldea 65%). C-bets fuertes postflop. Presión: abandona manos marginales.'
                ]
            ],
            [
                'nombre' => 'OMC',
                'tipo' => 'omc',
                'perfil' => 'tight-pasivo',
                'vpip' => 22,
                'pfr' => 6,
                'tilt_risk' => 8,
                'profesional' => true,
                'version_rango' => 'omc-v001',
                'metadata' => [
                    'descripcion' => 'Old Man Coffee. Patrón vivo: limpa mucho, solo sube con premium.',
                    'play_style' => 'Limp pasivo con parejas, suites. Solo sube con AA, KK, AK.',
                    'exploit' => 'Agresión ante sus limps. 3-bet sus opens. Control de blinds.'
                ]
            ],
            [
                'nombre' => 'TAG',
                'tipo' => 'tag',
                'perfil' => 'tight-agresivo',
                'vpip' => 21,
                'pfr' => 19,
                'tilt_risk' => 10,
                'profesional' => true,
                'version_rango' => 'tag-v001',
                'metadata' => [
                    'descripcion' => 'Estilo ganador estándar. Equilibrado y adaptable.',
                    'play_style' => 'Abre rangos estándar por posición. 3-bets equilibrados. Agresivo postflop pero selectivo.',
                    'exploit' => 'Difícil (es el jugador a imitar). Tighten vs sus 3-bets. Juega valor-focused.',
                    'profesional_desde_normal' => true
                ]
            ],
            [
                'nombre' => 'LAG',
                'tipo' => 'lag',
                'perfil' => 'loose-agresivo',
                'vpip' => 28,
                'pfr' => 26,
                'tilt_risk' => 15,
                'profesional' => true,
                'version_rango' => 'lag-v001',
                'metadata' => [
                    'descripcion' => 'Domina con rangos amplios. Alto volumen, agresión constante.',
                    'play_style' => 'Abre amplio desde cualquier posición. 3-bets light. Agresión postflop.',
                    'exploit' => 'Tighten 3-bet range. Check-trap con value. Controla aggression con c-bets.',
                    'profesional_desde_normal' => true
                ]
            ],
            [
                'nombre' => 'Calling Station',
                'tipo' => 'calling_station',
                'perfil' => 'loose-pasivo',
                'vpip' => 40,
                'pfr' => 15,
                'tilt_risk' => 40,
                'profesional' => false,
                'version_rango' => 'calling-station-v001',
                'metadata' => [
                    'descripcion' => 'Paga apuestas con cualquier cosa. Rara vez foldean.',
                    'play_style' => 'Juega muchas manos pero pasivamente. Paga raises con marginal. Rara vez foldean.',
                    'exploit' => 'Value bet agresivo (foldeam poco). Poco bluff. Aprieta si va en tilt.',
                    'tilts_easily' => true,
                    'ev_on_tilt' => '-40%'
                ]
            ],
            [
                'nombre' => 'Maníaco',
                'tipo' => 'maniaco',
                'perfil' => 'loose-agresivo',
                'vpip' => 50,
                'pfr' => 38,
                'tilt_risk' => 60,
                'profesional' => false,
                'version_rango' => 'maniaco-v001',
                'metadata' => [
                    'descripcion' => 'HIPERAGRESIVO. Sube con casi cualquier dos cartas.',
                    'play_style' => 'Raise-happy con cualquier cosa. Impredecible, altísima varianza.',
                    'exploit' => 'Tightear decisiones. Valuebets fuertes. Deja ganar al azar.',
                    'tilts_easily' => true,
                    'ev_on_tilt' => '-60%'
                ]
            ],
            [
                'nombre' => 'Ballena',
                'tipo' => 'ballena',
                'perfil' => 'errático',
                'vpip' => 60,
                'pfr' => 8,
                'tilt_risk' => 0,
                'profesional' => false,
                'version_rango' => 'ballena-v001',
                'metadata' => [
                    'descripcion' => 'Completamente errático. Juega por diversión/apuestas, NO para ganar.',
                    'play_style' => 'Entra mayoría de manos. Nunca sube. Paga todo. Juega sin lógica matemática.',
                    'exploit' => 'Juega poker normal. Value bet constante. Deja que cometa errores. Variable (suerte puede ayudarlo).',
                    'tilts_easily' => false
                ]
            ]
        ];

        foreach ($bots as $botData) {
            Bot::firstOrCreate(
                ['nombre' => $botData['nombre']],
                $botData,
            );
        }
    }
}
