<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HandGroup extends Model
{
    protected $fillable = ['group_name', 'hand_type', 'gto_ev', 'hands_in_group', 'combinatorios'];

    protected $casts = [
        'gto_ev' => 'decimal:4',
        'hands_in_group' => 'json',
    ];

    public function values()
    {
        return $this->hasMany(HandGroupValue::class);
    }

    public function getValue($scenario = 'Nash', $position = null, $stackDepth = 50)
    {
        $query = $this->values()->byScenario($scenario);

        if ($position) {
            $query->byPosition($position);
        }

        // Primero intentar encontrar el rango exacto
        $value = $query->byStackDepth($stackDepth)->first();

        // Si no encuentra exactamente, buscar el rango más cercano
        if (!$value) {
            $query = $this->values()->byScenario($scenario);
            if ($position) {
                $query->byPosition($position);
            }
            // Buscar el rango que contenga el stackDepth o el más cercano
            $value = $query
                ->where('stack_depth_min', '<=', $stackDepth)
                ->orderBy('stack_depth_max', 'desc')
                ->first();
        }

        return $value?->ev ?? $this->gto_ev;
    }
}
