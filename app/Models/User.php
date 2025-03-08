<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $dates = ['deleted_at'];
    
    protected $fillable = [
        'role_id',
        'divisi_id',
        'name',
        'email',
        'password',
        'token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function manPower(): HasOne {
        return $this->hasOne(ManPower::class, 'user_id', 'id');
    }
    

    public function divisi(): BelongsTo
    {
        return $this->belongsTo(Divisi::class);
    }

    public function salary() : HasOne {
        return $this->hasOne(UserSalary::class, 'user_id', 'id');
    }

    public function hasRole($role)
    {
        if (is_string($role)) {
            return $this->role && $this->role->role_name === $role;
        } elseif (is_int($role)) {
            return $this->role_id === $role;
        }

        return false;
    }


}
