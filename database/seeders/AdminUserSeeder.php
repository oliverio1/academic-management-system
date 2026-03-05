<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Administrador del Sistema',
                'password' => Hash::make('123123123'),
                'email_verified_at' => now(),
            ]
        );
        $admin->assignRole('admin');
        $teacher = User::firstOrCreate(
            ['email' => 'profe@profe.com'],
            [
                'name' => 'Profesor del Sistema',
                'password' => Hash::make('123123123'),
                'email_verified_at' => now(),
            ]
        );
        $teacher->assignRole('teacher');
        $student = User::firstOrCreate(
            ['email' => 'estu@estu.com'],
            [
                'name' => 'Estudiante del Sistema',
                'password' => Hash::make('123123123'),
                'email_verified_at' => now(),
            ]
        );
        $student->assignRole('student');
    }
}
