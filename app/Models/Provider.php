<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;


class Provider extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'email',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
   
    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class);
    }

    
    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }
}