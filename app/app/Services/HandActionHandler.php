<?php

namespace App\Services;

use App\Models\TournamentHand;

class HandActionHandler
{
    private TournamentHand $hand;
    private array $actions = [];
    private float $currentPot = 0;
    private array $playerBets = [];

    public function __construct(TournamentHand $hand)
    {
        $this->hand = $hand;
        $this->initializeBets();
    }

    public function recordAction(int $seat, string $actionType, ?float $amount = null): self
    {
        $action = [
            'seat' => $seat,
            'type' => $actionType,
            'amount' => $amount,
            'timestamp' => now(),
            'street' => $this->hand->status,
        ];

        $this->actions[] = $action;

        // Procesar la acción
        match ($actionType) {
            'check' => $this->handleCheck($seat),
            'call' => $this->handleCall($seat, $amount),
            'raise' => $this->handleRaise($seat, $amount),
            'fold' => $this->handleFold($seat),
            'all_in' => $this->handleAllIn($seat, $amount),
            default => null,
        };

        return $this;
    }

    public function moveToNextStreet(string $newStreet): self
    {
        // Actualizar el estado de la mano
        $this->hand->update([
            'status' => $newStreet,
        ]);

        // Resetear las apuestas para la siguiente calle
        $this->playerBets = [];

        return $this;
    }

    public function getStreetPot(): float
    {
        return $this->currentPot;
    }

    public function saveActions(): void
    {
        $this->hand->update([
            'actions' => $this->actions,
        ]);
    }

    private function handleCheck(int $seat): void
    {
        // No hay acción monetaria
    }

    private function handleCall(int $seat, ?float $amount): void
    {
        if ($amount) {
            $this->currentPot += $amount;
            $this->playerBets[$seat] = ($this->playerBets[$seat] ?? 0) + $amount;
        }
    }

    private function handleRaise(int $seat, ?float $amount): void
    {
        if ($amount) {
            $this->currentPot += $amount;
            $this->playerBets[$seat] = $amount;
        }
    }

    private function handleFold(int $seat): void
    {
        // El jugador se retira de la mano
        // Será removido de players_in_hand
    }

    private function handleAllIn(int $seat, ?float $amount): void
    {
        if ($amount) {
            $this->currentPot += $amount;
            $this->playerBets[$seat] = $amount;
        }
    }

    private function initializeBets(): void
    {
        $config = $this->hand->blind_level_config;
        $smallBlind = $config['small_blind'] ?? 50;
        $bigBlind = $config['big_blind'] ?? 100;

        // Registrar blinds
        $this->recordAction($this->hand->small_blind_seat, 'blind', $smallBlind);
        $this->recordAction($this->hand->big_blind_seat, 'blind', $bigBlind);

        $this->currentPot = $smallBlind + $bigBlind;
        $this->playerBets = [
            $this->hand->small_blind_seat => $smallBlind,
            $this->hand->big_blind_seat => $bigBlind,
        ];
    }

    public function getPlayerBets(): array
    {
        return $this->playerBets;
    }

    public function getActions(): array
    {
        return $this->actions;
    }
}
