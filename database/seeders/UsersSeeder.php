<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Models\Divisi;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Buat roles
        $roles = ['Owner', 'Admin', 'Marketing', 'Supervisor', 'Gudang', 'Finance', 'Tenaga Kerja'];

        foreach ($roles as $role) {
            Role::firstOrCreate([
                'role_name' => $role
            ]);
        }

        // Buat divisi CAT jika belum ada
        $divisiCat = Divisi::firstOrCreate([
            'name' => 'CAT',
            'kode_divisi' => 'CAT-001',
        ]);

        // Buat User Owner
        User::factory()->create([
            'email' => 'owner@mailinator.com',
            'role_id' => Role::where('role_name', 'Owner')->first()->id,
            'name' => 'Owner'
        ]);

        // Buat Admin
        User::factory()->create([
            'email' => 'admin@mailinator.com',
            'role_id' => Role::where('role_name', 'Admin')->first()->id,
            'name' => 'Admin'
        ]);

        // Buat User Marketing
        User::factory()->create([
            'email' => 'marketing@mailinator.com',
            'role_id' => Role::where('role_name', 'Marketing')->first()->id,
            'name' => 'Marketing'
        ]);

        // Buat User Supervisor
        User::factory()->create([
            'email' => 'supervisor@mailinator.com',
            'role_id' => Role::where('role_name', 'Supervisor')->first()->id,
            'name' => 'Supervisor'
        ]);

        // Buat User Gudang
        User::factory()->create([
            'email' => 'gudang@mailinator.com',
            'role_id' => Role::where('role_name', 'Gudang')->first()->id,
            'name' => 'Gudang'
        ]);

        // Buat User Finance
        User::factory()->create([
            'email' => 'finance@mailinator.com',
            'role_id' => Role::where('role_name', 'Finance')->first()->id,
            'name' => 'Finance'
        ]);

        // Buat User dengan role Tenaga Kerja dan divisi CAT
        User::factory()->create([
            'email' => 'tenagakerja@mailinator.com',
            'role_id' => Role::where('role_name', 'Tenaga Kerja')->first()->id,
            'divisi_id' => $divisiCat->id,
            'name' => 'Tenaga Kerja'
        ]);
    }
}
