<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HandGroupValue extends Model
{
    protected $fillable = [
        'hand_group_id',
        'scenario',
        'positions',
        'stack_depth_min',
        'stack_depth_max',
        'ev',
        'actions',
    ];

    protected $casts = [
        'positions' => 'array',
        'ev' => 'decimal:2',
        'actions' => 'array',
    ];

    public function handGroup()
    {
        return $this->belongsTo(HandGroup::class);
    }

    public function scopeByScenario($query, $scenario)
    {
        return $query->where('scenario', $scenario);
    }

    public function scopeByPosition($query, $position)
    {
        return $query->whereJsonContains('positions', $position);
    }

    public function scopeByStackDepth($query, $stackDepth)
    {
        return $query->where('stack_depth_min', '<=', $stackDepth)
                     ->where('stack_depth_max', '>=', $stackDepth);
    }
}
