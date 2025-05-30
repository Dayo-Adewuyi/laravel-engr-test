<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Claim extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'insurer_id',
        'specialty_id',
        'batch_id',
        'encounter_date',
        'submission_date',
        'priority_level',
        'total_amount',
        'processed',
    ];

    protected $casts = [
        'encounter_date' => 'date',
        'submission_date' => 'date',
        'priority_level' => 'integer',
        'total_amount' => 'decimal:2',
        'processed' => 'boolean',
    ];

  
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

   
    public function insurer(): BelongsTo
    {
        return $this->belongsTo(Insurer::class);
    }

  
    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

   
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

  
    public function items(): HasMany
    {
        return $this->hasMany(ClaimItem::class);
    }

  
}