<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Una acción de una repetición: de la mano jugada (side = original) o de la repetida (side = replay). */
class HandReplayAction extends Model
{
    protected $table = 'hand_replay_actions';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'distribution' => 'array',
    ];

    public function replay(): BelongsTo
    {
        return $this->belongsTo(HandReplay::class, 'hand_replay_id');
    }
}
