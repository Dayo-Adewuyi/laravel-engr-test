<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Specialty extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
    ];

  
    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class);
    }


    public function insurers(): BelongsToMany
    {
        return $this->belongsToMany(Insurer::class)
            ->withPivot('efficiency_factor')
            ->withTimestamps();
    }
}
