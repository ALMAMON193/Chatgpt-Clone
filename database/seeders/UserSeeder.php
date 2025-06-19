<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'admin',
            'email' => 'admin@admin.com',
            'password' => bcrypt('12345678'),
            'user_type' => 'admin',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);
        User::create([
            'name' => 'user',
            'email' => 'user@user.com',
            'password' => bcrypt('12345678'),
            'user_type' => 'user',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);
    }
}
