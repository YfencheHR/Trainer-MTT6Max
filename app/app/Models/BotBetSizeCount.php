<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Conteo de apuestas de un perfil (calle, tipo de mano, tramo de tamaño) con cartas conocidas. Ver BetSizeMatrix. */
class BotBetSizeCount extends Model
{
    protected $fillable = ['bot_id', 'street', 'tier', 'bucket', 'n'];
}
