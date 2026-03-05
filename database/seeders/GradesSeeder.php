<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Activity;
use App\Models\Grade;

class GradesSeeder extends Seeder
{
    public function run(): void
    {
        // Limpieza para evitar duplicados
        Grade::truncate();

        Activity::with([
            'evaluationCriterion.teachingAssignment.group.students'
        ])->chunk(50, function ($activities) {

            foreach ($activities as $activity) {

                $criterion = $activity->evaluationCriterion;

                if (! $criterion) {
                    continue;
                }

                $assignment = $criterion->teachingAssignment;
                $group = $assignment?->group;

                if (! $group || $group->students->isEmpty()) {
                    continue;
                }

                foreach ($group->students as $student) {

                    // Simular que algunos no entregan
                    if (rand(1, 100) <= 15) {
                        continue;
                    }

                    Grade::create([
                        'activity_id' => $activity->id,
                        'student_id'  => $student->id,
                        'score'       => rand(5, 10),
                    ]);
                }
            }
        });
    }
}