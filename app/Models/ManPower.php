<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes; 

class ManPower extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    public function user() : HasOne {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function project() : HasOne {
        return $this->HasOne(Project::class, 'id', 'project_id');
    }

    public function logs() : HasMany {
        return $this->hasMany(LogManPower::class, 'man_power_id', 'id');
    }
}
