<?php

namespace Database\Seeders;

use App\Models\AdminAction;
use Illuminate\Database\Seeder;

class AdminActionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['grant_owner', 'revoke_owner', 'activate', 'deactivate'] as $action) {
            AdminAction::firstOrCreate(['name' => $action]);
        }
    }
}