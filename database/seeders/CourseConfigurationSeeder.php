<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\TeachingAssignment;

class CourseConfigurationSeeder extends Seeder
{
    public function run()
    {
        foreach (TeachingAssignment::all() as $assignment) {

            $exam = $assignment->evaluationCriteria()->create([
                'name' => 'Exámenes',
                'percentage' => 40,
            ]);

            $tasks = $assignment->evaluationCriteria()->create([
                'name' => 'Tareas',
                'percentage' => 40,
            ]);

            $assignment->evaluationCriteria()->create([
                'name' => 'Asistencia',
                'percentage' => 20,
            ]);
        }
    }
}
