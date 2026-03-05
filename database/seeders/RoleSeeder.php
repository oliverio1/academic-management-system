<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\User;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'admin',
            'teacher',
            'student',
            'medical',
            'psychological',
            'finance',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        $administrador = \App\Models\User::create([
            'name' => 'Administrador',
            'email' => 'admin@admin.com',
            'password' => bcrypt('123123123'),
        ]);

        $profesor = \App\Models\User::create([
            'name' => 'Profesor',
            'email' => 'profe@profe.com',
            'password' => bcrypt('123123123'),
        ]);

        $estudiante = \App\Models\User::create([
            'name' => 'Estudiante',
            'email' => 'estu@estu.com',
            'password' => bcrypt('123123123'),
        ]);

        $administrador->assignRole('admin');
        $profesor->assignRole('teacher');
        $estudiante->assignRole('student');
    }
}
