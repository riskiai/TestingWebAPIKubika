<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    const OWNER = 1;
    const ADMIN = 2;
    const MARKETING = 3;
    const SUPERVISOR = 4;
    const GUDANG =5;
    const FINANCE =6;
    const TENAGA_KERJA =7;

    protected $table = 'roles';

    protected $fillable = [
        'role_name',
    ];
}
