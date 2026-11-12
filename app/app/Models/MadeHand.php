<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MadeHand extends Model
{
    protected $fillable = [
        'name',
        'description',
        'value',
        'order',
        'is_special',
        'type',
        'applies_to_river',
        'combos',
        'visuals',
        'probability',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'probability' => 'float',
        'is_special' => 'boolean',
        'applies_to_river' => 'boolean',
        'visuals' => 'array',
    ];

    public function scopeSpecial($query)
    {
        return $query->where('is_special', true);
    }

    public function scopeNormal($query)
    {
        return $query->where('is_special', false);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order')->orderBy('value', 'desc');
    }
}
