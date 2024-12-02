<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseCategory extends Model
{
    use HasFactory;

    const FLASH_CASH = 1;
    const INVOICE = 2;
    const MAN_POWER = 3;
    const EXPENSE = 4;
    const REIMBURSEMENT = 5;

    // Tentukan nama tabel jika tidak mengikuti konvensi
    protected $table = 'purchase_category';

    // Tentukan kolom yang dapat diisi (fillable)
    protected $fillable = [
        'name',
        'short',
    ];

}
