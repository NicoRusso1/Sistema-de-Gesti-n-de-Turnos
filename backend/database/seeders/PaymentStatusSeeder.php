<?php

namespace Database\Seeders;

use App\Enums\PaymentStatusName;
use App\Models\PaymentStatus;
use Illuminate\Database\Seeder;

class PaymentStatusSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PaymentStatusName::cases() as $status) {
            PaymentStatus::firstOrCreate(['name' => $status->value]);
        }
    }
}