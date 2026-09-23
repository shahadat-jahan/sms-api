<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@school.test'],
            [
                'name' => 'Management Admin',
                'password' => Hash::make('Admin@12345'),
                'role' => UserRole::Admin,
            ],
        );
    }
}
