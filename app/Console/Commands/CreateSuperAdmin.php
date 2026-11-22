<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Admin\SuperAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateSuperAdmin extends Command
{
    protected $signature = 'admin:create
        {--name= : The super admin name}
        {--email= : The super admin email}
        {--password= : The super admin password}';

    protected $description = 'Create a new Super Admin user';

    public function handle(): int
    {
        $name = (string) ($this->option('name') ?: $this->ask('Name'));
        $email = strtolower(trim((string) ($this->option('email') ?: $this->ask('Email'))));
        $password = (string) ($this->option('password') ?: $this->secret('Password (min 8 characters)'));

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:super_admins,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $admin = SuperAdmin::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'is_active' => true,
        ]);

        $this->info("Super Admin [{$admin->email}] created successfully.");

        return self::SUCCESS;
    }
}
