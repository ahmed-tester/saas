<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            FeatureSeeder::class,
            PlanSeeder::class,
            PermissionSeeder::class,
            SuperAdminSeeder::class,
        ]);
    }
}
