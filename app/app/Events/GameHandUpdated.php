<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class GameHandUpdated implements ShouldBroadcastNow
{
    public function __construct(
        public int $channelHandId,
        public array $state,
        public bool $finished = false,
        public ?string $message = null,
    ) {
    }

    public function broadcastOn(): Channel
    {
        return new Channel("game-hand.{$this->channelHandId}");
    }

    public function broadcastAs(): string
    {
        return 'game.hand.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'state' => $this->state,
            'finished' => $this->finished,
            'message' => $this->message,
        ];
    }
}
