<?php

namespace Database\Seeders;

use App\Models\PurchaseStatus;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PurchaseStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Daftar status yang akan dimasukkan ke tabel
        $statuses = [
            ['id' => PurchaseStatus::AWAITING, 'name' => PurchaseStatus::TEXT_AWAITING],
            ['id' => PurchaseStatus::VERIFIED, 'name' => PurchaseStatus::TEXT_VERIFIED],
            ['id' => PurchaseStatus::OPEN, 'name' => PurchaseStatus::TEXT_OPEN],
            ['id' => PurchaseStatus::OVERDUE, 'name' => PurchaseStatus::TEXT_OVERDUE],
            ['id' => PurchaseStatus::DUEDATE, 'name' => PurchaseStatus::TEXT_DUEDATE],
            ['id' => PurchaseStatus::REJECTED, 'name' => PurchaseStatus::TEXT_REJECTED],
            ['id' => PurchaseStatus::PAID, 'name' => PurchaseStatus::TEXT_PAID],
        ];

        // Insert data ke tabel
        foreach ($statuses as $status) {
            PurchaseStatus::create($status);
        }
    }
}
