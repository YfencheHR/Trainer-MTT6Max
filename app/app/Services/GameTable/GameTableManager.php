<?php

namespace App\Services\GameTable;

use App\Models\TournamentHand;
use App\Services\HandPlayService;

class GameTableManager
{
    public function __construct(
        protected TablePositionService $positionService,
        protected HandPlayService $handPlayService
    ) {
    }

    public function getTableData(TournamentHand $hand): array
    {
        $state = $this->handPlayService->state($hand);
        $state['visible_seats'] = $this->positionService->getVisibleSeats(count($state['players']));

        return $state;
    }

    public function getPositionService(): TablePositionService
    {
        return $this->positionService;
    }
}
