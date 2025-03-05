<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class ManPowerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $path = database_path('seeders/man_powers.sql');
        DB::unprepared(file_get_contents($path));
    }
}
