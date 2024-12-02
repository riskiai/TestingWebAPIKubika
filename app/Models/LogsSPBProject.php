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
        'tab',  // Status tab
        'name',  // Nama user
        'message', // Tambahkan message di sini
    ];
}
