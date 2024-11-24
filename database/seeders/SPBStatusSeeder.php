<?php

namespace Database\Seeders;

use App\Models\SpbProject_Status;
use Illuminate\Database\Seeder;

class SPBStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Daftar status yang akan dimasukkan ke tabel
        $statuses = [
            ['id' => SpbProject_Status::AWAITING, 'name' => SpbProject_Status::TEXT_AWAITING],
            ['id' => SpbProject_Status::VERIFIED, 'name' => SpbProject_Status::TEXT_VERIFIED],
            ['id' => SpbProject_Status::OPEN, 'name' => SpbProject_Status::TEXT_OPEN],
            ['id' => SpbProject_Status::OVERDUE, 'name' => SpbProject_Status::TEXT_OVERDUE],
            ['id' => SpbProject_Status::DUEDATE, 'name' => SpbProject_Status::TEXT_DUEDATE],
            ['id' => SpbProject_Status::REJECTED, 'name' => SpbProject_Status::TEXT_REJECTED],
            ['id' => SpbProject_Status::PAID, 'name' => SpbProject_Status::TEXT_PAID],
        ];

        // Insert data ke tabel
        foreach ($statuses as $status) {
            SpbProject_Status::create($status);
        }
    }
}
