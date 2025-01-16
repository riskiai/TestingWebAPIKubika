<?php

namespace Database\Seeders;

use App\Models\SpbProject_Category;
use Illuminate\Database\Seeder;

class SPBCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Seed data untuk kategori SPB
       /*  SpbProject_Category::create([
            'name' => 'Flash Cash',
            'short' => 'FCASPB',
        ]);
        SpbProject_Category::create([
            'name' => 'Invoice',
            'short' => 'INVSPB',
        ]); */
        SpbProject_Category::create([
            'name' => 'Borongan',
            'short' => 'BRGSPB',
        ]);
        // SpbProject_Category::create([
        //     'name' => 'Man Power',
        //     'short' => 'MAP',
        // ]);
        // SpbProject_Category::create([
        //     'name' => 'Expense',
        //     'short' => 'EXP',
        // ]);
        // SpbProject_Category::create([
        //     'name' => 'Reimbursement',
        //     'short' => 'RMB',
        // ]);
    }
}
