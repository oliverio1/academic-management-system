<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Subject;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = [
            ['name' => 'Matemáticas III', 'hours_per_week'=> 5, 'level_id' => 1, 'type' => 'Teórica'],
            ['name' => 'Física I', 'hours_per_week'=> 5, 'level_id' => 1, 'type' => 'PRÁCTICA'],
            ['name' => 'Historia de México II', 'hours_per_week'=> 3, 'level_id' => 1, 'type' => 'Teórica'],
            ['name' => 'Literatura I', 'hours_per_week'=> 3, 'level_id' => 1, 'type' => 'Teórica'],
            ['name' => 'Tutorías III', 'hours_per_week'=> 2, 'level_id' => 1, 'type' => 'Teórica'],
            ['name' => 'Orientación educativa III', 'hours_per_week'=> 2, 'level_id' => 1, 'type' => 'Teórica'],
            ['name' => 'Biología I', 'hours_per_week'=> 4, 'level_id' => 1, 'type' => 'PRÁCTICA'],
            ['name' => 'Direccionar y evaluar el plan de acción', 'hours_per_week'=> 3, 'level_id' => 1, 'type' => 'Teórica'],
            ['name' => 'Planear actividades y asignar recursos', 'hours_per_week'=> 4, 'level_id' => 1, 'type' => 'Teórica'],
            ['name' => 'Inglés III', 'hours_per_week'=> 3, 'level_id' => 1, 'type' => 'Teórica'],
            ['name' => 'Pottencia III', 'hours_per_week'=> 1, 'level_id' => 1, 'type' => 'Teórica'],

            ['name' => 'Matemáticas IV', 'hours_per_week'=> 5, 'level_id' => 2, 'type' => 'Teórica'],
            ['name' => 'Física II', 'hours_per_week'=> 5, 'level_id' => 2, 'type' => 'PRÁCTICA'],
            ['name' => 'Estructura socioeconómica de México', 'hours_per_week'=> 3, 'level_id' => 2, 'type' => 'Teórica'],
            ['name' => 'Literatura II', 'hours_per_week'=> 3, 'level_id' => 2, 'type' => 'Teórica'],
            ['name' => 'Tutorías IV', 'hours_per_week'=> 2, 'level_id' => 2, 'type' => 'Teórica'],
            ['name' => 'Orientación educativa IV', 'hours_per_week'=> 2, 'level_id' => 2, 'type' => 'Teórica'],
            ['name' => 'Biología II', 'hours_per_week'=> 4, 'level_id' => 2, 'type' => 'PRÁCTICA'],
            ['name' => 'Generar la comunicación de la empresa', 'hours_per_week'=> 4, 'level_id' => 2, 'type' => 'Teórica'],
            ['name' => 'Controlar la información de la empresa', 'hours_per_week'=> 3, 'level_id' => 2, 'type' => 'Teórica'],
            ['name' => 'Inglés IV', 'hours_per_week'=> 3, 'level_id' => 2, 'type' => 'Teórica'],
            ['name' => 'Pottencia IV', 'hours_per_week'=> 1, 'level_id' => 2, 'type' => 'Teórica'],

            ['name' => 'Actualizar los sistemas de información de la empresa', 'hours_per_week'=> 3, 'level_id' => 3, 'type' => 'Teórica'],
            ['name' => 'Atender al cliente en su entorno social de manera presencial', 'hours_per_week'=> 4, 'level_id' => 3, 'type' => 'Teórica'],
            ['name' => 'Geografía', 'hours_per_week'=> 3, 'level_id' => 3, 'type' => 'Teórica'],
            ['name' => 'Historia universal contemporánea', 'hours_per_week'=> 3, 'level_id' => 3, 'type' => 'Teórica'],
            ['name' => 'Orientación educativa V', 'hours_per_week'=> 2, 'level_id' => 3, 'type' => 'Teórica'],
            ['name' => 'Tutorías V', 'hours_per_week'=> 2, 'level_id' => 3, 'type' => 'Teórica'],
            ['name' => 'Inglés V', 'hours_per_week'=> 2, 'level_id' => 3, 'type' => 'Teórica'],
            ['name' => 'Pottencia V', 'hours_per_week'=> 1, 'level_id' => 3, 'type' => 'Teórica'],
            
            ['name' => 'Cálculo diferencial', 'hours_per_week'=> 3, 'level_id' => 3, 'type' => 'Teórica'],
            ['name' => 'Ciencias de la salud I', 'hours_per_week'=> 3, 'level_id' => 3, 'type' => 'PRÁCTICA'],
            ['name' => 'Temas selectos de física I', 'hours_per_week'=> 3, 'level_id' => 3, 'type' => 'PRÁCTICA'],
            ['name' => 'Temas selectos de química I', 'hours_per_week'=> 3, 'level_id' => 3, 'type' => 'PRÁCTICA'],

            ['name' => 'Matemáticas financieras I', 'hours_per_week'=> 3, 'level_id' => 3, 'type' => 'Teórica'],
            ['name' => 'Psicología I', 'hours_per_week'=> 3, 'level_id' => 3, 'type' => 'Teórica'],
            ['name' => 'Sociología I', 'hours_per_week'=> 3, 'level_id' => 3, 'type' => 'Teórica'],
            ['name' => 'Derecho I', 'hours_per_week'=> 3, 'level_id' => 3, 'type' => 'Teórica'],
            
            ['name' => 'Ecología y medio ambiente', 'hours_per_week'=> 3, 'level_id' => 4, 'type' => 'Teórica'],
            ['name' => 'Filosofía', 'hours_per_week'=> 4, 'level_id' => 4, 'type' => 'Teórica'],
            ['name' => 'Detectar y dar seguimiento', 'hours_per_week'=> 4, 'level_id' => 4, 'type' => 'Teórica'],
            ['name' => 'Metodología de la investigación', 'hours_per_week'=> 3, 'level_id' => 4, 'type' => 'Teórica'],
            ['name' => 'Orientación educativa VI', 'hours_per_week'=> 2, 'level_id' => 4, 'type' => 'Teórica'],
            ['name' => 'Tutorías VI', 'hours_per_week'=> 2, 'level_id' => 4, 'type' => 'Teórica'],
            ['name' => 'Inglés VI', 'hours_per_week'=> 2, 'level_id' => 4, 'type' => 'Teórica'],
            ['name' => 'Atender al cliente mediante TICS', 'hours_per_week'=> 3, 'level_id' => 4, 'type' => 'Teórica'],
            ['name' => 'Psicología II', 'hours_per_week'=> 3, 'level_id' => 4, 'type' => 'Teórica'],
            ['name' => 'Sociología II', 'hours_per_week'=> 3, 'level_id' => 4, 'type' => 'Teórica'],
            ['name' => 'Matemáticas financieras II', 'hours_per_week'=> 3, 'level_id' => 4, 'type' => 'Teórica'],
            ['name' => 'Derecho II', 'hours_per_week'=> 3, 'level_id' => 4, 'type' => 'Teórica'],
            ['name' => 'Pottencia VI', 'hours_per_week'=> 1, 'level_id' => 4, 'type' => 'Teórica'],
        ];

        foreach($subjects as $subject) {
            Subject::create([
                'name' => mb_strtoupper($subject['name']),
                'hours_per_week' => $subject['hours_per_week'],
                'level_id' => $subject['level_id'],
                'type' => $subject['type'],
                'is_active' => true,
            ]);
        }
    }
}
