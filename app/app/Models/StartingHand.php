<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StartingHand extends Model
{
    protected $fillable = ['card1_id', 'card2_id', 'hand_code', 'hand_type'];

    public function card1(): BelongsTo
    {
        return $this->belongsTo(Card::class, 'card1_id');
    }

    public function card2(): BelongsTo
    {
        return $this->belongsTo(Card::class, 'card2_id');
    }
}
