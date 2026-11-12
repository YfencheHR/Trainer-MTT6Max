<?php

namespace Tests\Unit;

use App\Events\GameHandUpdated;
use Illuminate\Broadcasting\Channel;
use PHPUnit\Framework\TestCase;

class GameHandUpdatedTest extends TestCase
{
    public function test_it_broadcasts_the_hand_state_and_completion_status(): void
    {
        $state = ['hand_id' => 42, 'status' => 'completed'];
        $event = new GameHandUpdated(41, $state, true, 'Torneo terminado');

        $this->assertSame('game.hand.updated', $event->broadcastAs());
        $this->assertEquals(new Channel('game-hand.41'), $event->broadcastOn());
        $this->assertSame([
            'state' => $state,
            'finished' => true,
            'message' => 'Torneo terminado',
        ], $event->broadcastWith());
    }
}
