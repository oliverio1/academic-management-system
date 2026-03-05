<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TeachingAssignment;
use App\Models\EvaluationCriterion;

class EvaluationCriterionSeeder extends Seeder
{
    public function run(): void
    {
        $criteria = [
            ['name' => 'Apuntes',   'percentage' => 20],
            ['name' => 'Exámenes',  'percentage' => 30],
            ['name' => 'Hábitos',   'percentage' => 10],
            ['name' => 'Tareas',    'percentage' => 10],
            ['name' => 'Prácticas', 'percentage' => 20],
            ['name' => 'Proyecto',  'percentage' => 10],
        ];
        TeachingAssignment::chunk(50, function ($assignments) use ($criteria) {
            foreach ($assignments as $assignment) {
                if ($assignment->evaluationCriteria()->exists()) {
                    continue;
                }
                foreach ($criteria as $criterion) {
                    EvaluationCriterion::create([
                        'teaching_assignment_id' => $assignment->id,
                        'name' => $criterion['name'],
                        'percentage' => $criterion['percentage'],
                    ]);
                }
            }
        });
    }
}
