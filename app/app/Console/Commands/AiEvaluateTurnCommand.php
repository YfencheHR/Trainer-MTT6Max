<?php

namespace App\Console\Commands;

/**
 * Lo mismo que ai:evaluate-flop pero con las decisiones del turn: el perfil lleva las estadísticas del flop y del turn (las del flop sirven para
 * estrechar el rango con lo que cada uno hizo antes), la iniciativa es la de quien apostó en la calle anterior y el rango propio sale de su línea preflop.
 */
class AiEvaluateTurnCommand extends AiEvaluateFlopCommand
{
    protected $signature = 'ai:evaluate-turn {file : TSV id_hand<TAB>history (volcado de tourney_hand_histories)}
        {--player= : jugador a evaluar (por defecto, el héroe de las manos)}
        {--profile-hands=6000 : manos con turn para calcular el perfil}
        {--limit=100 : decisiones a evaluar}
        {--modes=plain,range,full : modos a comparar}
        {--min-line=40 : manos mínimas de una línea preflop para construir su rango}
        {--dump= : números de decisión cuyo estado se guarda en storage/app/ai/eval-states.json; no llama al modelo}
        {--out=ai/eval-turn.json : resultado detallado (dentro de storage/app)}';

    protected $description = 'Evalúa la IA del turn contra manos reales de PokerTracker';

    protected string $street = 'turn';
}
