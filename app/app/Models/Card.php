<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Card extends Model
{
    protected $fillable = ['rank', 'suit', 'code', 'image_path'];

    public function startingHandsAsCard1(): HasMany
    {
        return $this->hasMany(StartingHand::class, 'card1_id');
    }

    public function startingHandsAsCard2(): HasMany
    {
        return $this->hasMany(StartingHand::class, 'card2_id');
    }
}
