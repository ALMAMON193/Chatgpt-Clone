<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Plan::create([
            'name' => 'FREE',
            'price' => 0.00,
            'duration' => 'day',
        ]);

        Plan::create([
            'name' => 'Supporter Package',
            'price' => 9.00,
            'duration' => 'month',
        ]);

        Plan::create([
            'name' => 'Premium Supporter',
            'price' => 19.99,
            'duration' => 'month',
        ]);
    }
}
