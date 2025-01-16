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
    const BORONGAN = 3;

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
            self::BORONGAN => 'Borongan',
        ];

        return $categories[$id] ?? 'Unknown';
    }
}
