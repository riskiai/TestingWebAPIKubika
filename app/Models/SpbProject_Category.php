<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpbProject_Category extends Model
{
    use HasFactory;

    // Constants untuk kategori SPB
    const FLASH_CASH = 1;
    const INVOICE = 2;
    // const MAN_POWER = 3;
    // const EXPENSE = 4;
    // const REIMBURSEMENT = 5;

    protected $table = 'spb_project__categories';

    protected $fillable = [
        'name',
        'short',
    ];

    /**
     * Method untuk mendapatkan nama kategori berdasarkan ID.
     */
    public static function getCategoryName($id)
    {
        $categories = [
            self::FLASH_CASH => 'Flash Cash',
            self::INVOICE => 'Invoice',
            // self::MAN_POWER => 'Man Power',
            // self::EXPENSE => 'Expense',
            // self::REIMBURSEMENT => 'Reimbursement',
        ];

        return $categories[$id] ?? 'Unknown';
    }
}
