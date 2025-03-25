<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Insurer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'email',
        'daily_capacity',
        'min_batch_size',
        'max_batch_size',
        'prefers_encounter_date', 
    ];

    protected $casts = [
        'daily_capacity' => 'integer',
        'min_batch_size' => 'integer',
        'max_batch_size' => 'integer',
        'prefers_encounter_date' => 'boolean',
    ];

  
    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class);
    }

   
    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

  
    public function specialties(): BelongsToMany
    {
        return $this->belongsToMany(Specialty::class)
            ->withPivot('efficiency_factor')
            ->withTimestamps();
    }
}