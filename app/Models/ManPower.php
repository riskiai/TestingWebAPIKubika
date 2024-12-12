<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ManPower extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function user() : HasOne {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function project() : HasOne {
        return $this->HasOne(Project::class, 'id', 'project_id');
    }
}
