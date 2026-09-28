<?php

namespace Database\Seeders;

use App\Enums\AdminActionName;
use App\Models\AdminAction;
use Illuminate\Database\Seeder;

class AdminActionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (AdminActionName::cases() as $action) {
            AdminAction::firstOrCreate(['name' => $action->value]);
        }
    }
}
