<?php

namespace Database\Seeders;

use App\Models\PurchaseCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PurchaseCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Seed data untuk kategori SPB
        PurchaseCategory::create([
            'name' => 'Flash Cash',
            'short' => 'FCAPRCS',
        ]);
        PurchaseCategory::create([
            'name' => 'Invoice',
            'short' => 'INVPRCS',
        ]);
        PurchaseCategory::create([
            'name' => 'Man Power',
            'short' => 'MAPPRCS',
        ]);
        PurchaseCategory::create([
            'name' => 'Expense',
            'short' => 'EXPPRCS',
        ]);
        PurchaseCategory::create([
            'name' => 'Reimbursement',
            'short' => 'RMBPRCS',
        ]);
    }
}
