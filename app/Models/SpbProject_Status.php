<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpbProject_Status extends Model
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

    // Define the table associated with the model
    protected $table = 'spb_project__statuses';

    // Fields that are mass-assignable
    protected $fillable = [
        'name',
    ];

    /**
     * Get the status name based on the status code.
     * 
     * @param int $status
     * @return string
     */
    public static function getStatusText($status)
    {
        $statuses = [
            self::AWAITING => self::TEXT_AWAITING,
            self::VERIFIED => self::TEXT_VERIFIED,
            self::OPEN => self::TEXT_OPEN,
            self::OVERDUE => self::TEXT_OVERDUE,
            self::DUEDATE => self::TEXT_DUEDATE,
            self::REJECTED => self::TEXT_REJECTED,
            self::PAID => self::TEXT_PAID,
        ];

        return $statuses[$status] ?? 'Unknown';
    }
}
