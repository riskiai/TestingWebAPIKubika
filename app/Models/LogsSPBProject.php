<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogsSPBProject extends Model
{
    use HasFactory;

    protected $table = 'logs_spbprojects';

    protected $fillable = [
        'spb_project_id',  // ID proyek SPB
        'tab_spb',  // Status tab
        'name',  // Nama user
        'message', // Tambahkan message di sini
        'deleted_at',  // Tambahkan deleted_at ke fillable
        'deleted_by',
    ];

    protected $dates = ['deleted_at'];
}
