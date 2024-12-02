<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseStatus extends Model
{
    use HasFactory;

    // Constants to represent different statuses
    const AWAITING = 1;
    const VERIFIED = 2;
    const OPEN = 3;
    const OVERDUE = 4;
    const DUEDATE = 5;
    const REJECTED = 6;
    const PAID = 7;

    const TEXT_AWAITING = "Awaiting";
    const TEXT_VERIFIED = "Verified";
    const TEXT_OPEN = "Open";
    const TEXT_OVERDUE = "Overdue";
    const TEXT_DUEDATE = "Due Date";
    const TEXT_REJECTED = "Rejected";
    const TEXT_PAID = "Paid";

    // Tentukan nama tabel jika tidak sesuai konvensi
    protected $table = 'purchase_status';

    // Tentukan kolom yang dapat diisi (fillable)
    protected $fillable = [
        'name',
    ];
}
