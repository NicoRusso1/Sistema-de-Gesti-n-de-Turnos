<?php

namespace Database\Seeders;

use App\Models\HealthInsurance;
use Illuminate\Database\Seeder;

class HealthInsuranceSeeder extends Seeder
{
    public function run(): void
    {
        $insurances = [
            ['name' => 'OSDE', 'discount_percentage' => 30],
            ['name' => 'Swiss Medical', 'discount_percentage' => 25],
            ['name' => 'Galeno', 'discount_percentage' => 20],
            ['name' => 'Sancor Salud', 'discount_percentage' => 15],
        ];

        foreach ($insurances as $insurance) {
            HealthInsurance::firstOrCreate(
                ['name' => $insurance['name']],
                ['discount_percentage' => $insurance['discount_percentage'], 'status' => 1]
            );
        }
    }
}