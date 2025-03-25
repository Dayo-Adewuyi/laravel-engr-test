<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'insurer_id',
        'batch_date',
        'batch_identifier',
        'total_claims',
        'total_amount',
        'processing_cost',
        'processed',
        'processing_date',
    ];

    protected $casts = [
        'batch_date' => 'date',
        'total_claims' => 'integer',
        'total_amount' => 'decimal:2',
        'processing_cost' => 'decimal:2',
        'processed' => 'boolean',
        'processing_date' => 'date',
    ];

   
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

 
    public function insurer(): BelongsTo
    {
        return $this->belongsTo(Insurer::class);
    }

  
    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class);
    }

   
}