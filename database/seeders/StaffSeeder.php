<?php

namespace Database\Seeders;

use App\Models\User;
//use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StaffSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            [
            'name' => 'staff',
            'email' => 'staff@gmail.com',
            'password' => Hash::make('123'),
            'role' => 'staff',
            'is_active' => true
        ],

        [
            'name' => 'john',
            'email' => 'john2@gmail.com',
            'password' => Hash::make('1234'),
            'role' => 'staff',
            'is_active' => true
        ],

        );
    }
}
