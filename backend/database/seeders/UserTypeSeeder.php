<?php

namespace Database\Seeders;

use App\Enums\UserTypeName;
use App\Models\UserType;
use Illuminate\Database\Seeder;

class UserTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (UserTypeName::cases() as $type) {
            UserType::firstOrCreate(['name' => $type->value]);
        }
    }
}