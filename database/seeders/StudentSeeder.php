<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StudentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $studentUser = User::updateOrCreate(
            ['email' => 'student1@school.test'],
            [
                'name' => 'Demo Student',
                'password' => Hash::make('password123'),
                'role' => UserRole::Student,
            ],
        );

        Student::updateOrCreate(
            ['roll' => 'STD-0001'],
            [
                'user_id' => $studentUser->id,
                'class' => 'Class 10',
                'section' => 'A',
                'phone' => '01700000001',
                'address' => '12 Demo Road, Dhaka',
            ],
        );
    }
}
