<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Copia congelada del cerebro completo de un bot (identificada por su hash). */
class BotBrainSnapshot extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'brain' => 'array',
        'created_at' => 'datetime',
    ];
}
