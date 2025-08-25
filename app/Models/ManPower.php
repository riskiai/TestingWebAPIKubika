<?php

namespace App\Models;

use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; 
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ManPower extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    public function user() : HasOne {
        return $this->hasOne(User::class, 'id', 'user_id')->withTrashed();
    }

    public function project() : HasOne {
        return $this->HasOne(Project::class, 'id', 'project_id');
    }

    public function logs() : HasMany {
        return $this->hasMany(LogManPower::class, 'man_power_id', 'id');
    }

    protected static function booted(): void
    {
        static::deleting(function (self $manPower): void {
            if (! $manPower->isForceDeleting() && Auth::check()) {
                $manPower->deleted_by = Auth::id();   // atau Auth::user()->name
                $manPower->save();
            }
        });
    }
}
