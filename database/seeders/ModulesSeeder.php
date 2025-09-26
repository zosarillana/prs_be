<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Modules;

class ModulesSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            ['name' => 'Dashboard',       'description' => 'Main system overview'],
            ['name' => 'Purchase Report', 'description' => 'View and analyze purchase reports'],
            ['name' => 'Purchase Order',  'description' => 'Manage purchase orders'],
            ['name' => 'Users',           'description' => 'User management'],
            ['name' => 'UOM',             'description' => 'Units of measurement'],
            ['name' => 'Department',      'description' => 'Department management'],
            ['name' => 'Tags',            'description' => 'Tag management'],
            ['name' => 'User Logs',       'description' => 'Track user activities'],
            ['name' => 'Audit Logs',      'description' => 'System audit trail'],
        ];

        foreach ($modules as $module) {
            Modules::create($module);
        }
    }
}
