<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Teacher;
use App\Models\User;

class TeacherSeeder extends Seeder
{
    public function run(): void
    {
        $teacherNames = [
            'FERNANDO GARCÍA',
            'OLIVER MARTÍNEZ',
            'OSIRIS DOMÍNGUEZ',
            'VICTOR FUENTES',
            'JANET PALMA',
            'INFORMÁTICA',
            'INGLÉS BACHILLERATO',
            'JONATHAN TORRES',
            'HILDA CORTÉS',
            'HAYRI CALDERÓN',
            'ERICK MÁRQUEZ',
        ];

        $teachers = [];
        foreach($teacherNames as $name) {
            $email = $this->generateEmail($name);
            $user = User::create([
                'email' => $email,
                'name' => $name,
                'password' => Hash::make('123123123')
            ]);
            $user->assignRole('teacher');
            $teacher = Teacher::create([
                'user_id' => $user->id,
                'is_active' => true
            ]);
        }
    }

    private function generateEmail($teacherName)
    {
        $cleanName = $this->cleanString($teacherName);
        $emailBase = strtolower(str_replace(' ', '.', $cleanName));
        $email = $emailBase . '.ula@gmail.com';
        $counter = 1;
        $originalEmail = $email;
        while (User::where('email', $email)->exists()) {
            $email = $emailBase . $counter . '.ula@gmail.com';
            $counter++;
        }
        return $email;
    }

    private function cleanString($string)
    {
        $string = trim($string);
        $replace = [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'Ñ' => 'N', 'ñ' => 'n',
            'Ü' => 'U', 'ü' => 'u',
        ];

        $string = strtr($string, $replace);

        // Remover cualquier otro carácter especial
        $string = preg_replace('/[^A-Za-z0-9\s]/', '', $string);

        return $string;
    }
}
