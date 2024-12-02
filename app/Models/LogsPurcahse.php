<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogsPurcahse extends Model
{
    use HasFactory;

    protected $table = 'logs_purchase';

    protected $fillable = [
        'purchase_id',  // ID proyek SPB
        'tab_purchase',  // Status tab
        'name',  // Nama user
        'message', // Tambahkan message di sini
    ];
}
