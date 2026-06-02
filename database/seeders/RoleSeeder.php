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
            'coordinator',
            'admin',
            'prefect',
            'teacher',
            'student',
            'medical',
            'psychological',
            'finance',
            'guardian',
            'tutor'
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        $administrador = \App\Models\User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Administrador',
                'password' => bcrypt('123123123'),
            ]
        );

        $coordinador = \App\Models\User::firstOrCreate(
            ['email' => 'coord@coord.com'],
            [
                'name' => 'Coordinador',
                'password' => bcrypt('123123123'),
            ]
        );

        $prefecto = \App\Models\User::firstOrCreate(
            ['email' => 'pref@pref.com'],
            [
                'name' => 'Prefecto',
                'password' => bcrypt('123123123'),
            ]
        );

        $profesor = \App\Models\User::firstOrCreate(
            ['email' => 'profe@profe.com'],
            [
                'name' => 'Profesor',
                'password' => bcrypt('123123123'),
            ]
        );

        $estudiante = \App\Models\User::firstOrCreate(
            ['email' => 'estu@estu.com'],
            [
                'name' => 'Estudiante',
                'password' => bcrypt('123123123'),
            ]
        );

        $tutor = \App\Models\User::firstOrCreate(
            ['email' => 'tutor@tutor.com'],
            [
                'name' => 'Tutor',
                'password' => bcrypt('123123123'),
            ]
        );

        $administrador->assignRole('admin');
        $coordinador->assignRole('coordinator');
        $prefecto->assignRole('prefect');
        $profesor->assignRole('teacher');
        $estudiante->assignRole('student');
        $tutor->assignRole('guardian');
        $tutor->assignRole('tutor');
    }
}
