<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Admin\SuperAdmin;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = strtolower(trim((string) env('SUPER_ADMIN_EMAIL', 'admin@example.com')));
        $password = (string) env('SUPER_ADMIN_PASSWORD', 'password');

        SuperAdmin::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Super Admin',
                'password' => $password,
                'is_active' => true,
            ]
        );
    }
}
