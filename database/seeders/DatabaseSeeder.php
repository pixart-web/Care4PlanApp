<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Tiago', 'Andreia', 'Fernanda'] as $admin) {
            User::updateOrCreate(
                ['username' => $admin],
                [
                    'name' => $admin,
                    'password' => Hash::make(env('CARE4ALL_INITIAL_ADMIN_PASSWORD', 'CHANGE_ME_ON_SERVER')),
                    'role' => 'ADMIN',
                ]
            );
        }
    }
}
