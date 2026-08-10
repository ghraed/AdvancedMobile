<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::query()->updateOrCreate(['email' => 'admin@example.com'], [
            'name' => 'Admin User',
            'password' => 'password',
            'role' => User::ROLE_ADMIN,
        ]);

        User::query()->updateOrCreate(['email' => 'test@example.com'], [
            'name' => 'Test User',
            'password' => 'password',
            'role' => User::ROLE_CUSTOMER,
        ]);

        $this->call(ReferenceCatalogSeeder::class);
    }
}
