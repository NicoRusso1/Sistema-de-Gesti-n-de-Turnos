<?php

namespace Database\Seeders;

use App\Enums\PaymentMethodName;
use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PaymentMethodName::cases() as $method) {
            PaymentMethod::firstOrCreate(['name' => $method->value]);
        }
    }
}