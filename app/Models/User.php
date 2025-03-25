<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $with = ['provider'];
    
    protected $fillable = [
        'name',
        'email',
        'password',
        'provider_id',
        'role',
    ];

  
    protected $hidden = [
        'password',
        'remember_token',
    ];

    
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];


    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }


    public function hasProvider(): bool
    {
        return !is_null($this->provider_id);
    }


    public function hasRole(string $roleName): bool
    {
        return $this->role === $roleName;
    }

  
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}