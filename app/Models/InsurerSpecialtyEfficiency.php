<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class InsurerSpecialtyEfficiency extends Pivot
{
    use HasFactory;

    protected $fillable = [
        'insurer_id',
        'specialty_id',
        'efficiency_factor',
    ];

    protected $casts = [
        'efficiency_factor' => 'float',
    ];
}