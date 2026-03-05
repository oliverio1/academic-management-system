<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Activity;
use App\Models\AcademicPeriod;
use App\Models\TeachingAssignment;
use App\Models\EvaluationCriterion;
use Carbon\Carbon;
use Faker\Factory as Faker;

class ActivitiesSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('es_MX');
        $periods = AcademicPeriod::all();

        TeachingAssignment::with('evaluationCriteria')
            ->chunk(25, function ($assignments) use ($faker, $periods) {

            foreach ($assignments as $assignment) {

                // Mapear criterios por nombre
                $criteria = $assignment->evaluationCriteria
                    ->keyBy('name');

                if ($criteria->isEmpty()) {
                    continue;
                }

                $definitions = [
                    'Apuntes'   => rand(10, 20),
                    'Exámenes'  => rand(1, 3),
                    'Hábitos'   => 1,
                    'Tareas'    => rand(5, 10),
                    'Prácticas' => rand(3, 5),
                    'Proyecto'  => 1,
                ];

                foreach ($definitions as $name => $count) {

                    if (! $criteria->has($name)) {
                        continue;
                    }

                    for ($i = 1; $i <= $count; $i++) {

                        $period = $periods->random();

                        Activity::create([
                            'teaching_assignment_id' => $assignment->id,
                            'evaluation_criterion_id' => $criteria[$name]->id,
                            'academic_period_id' => $period->id,
                            'title' => "{$name} {$i}",
                            'max_score' => 10,
                            'due_date' => Carbon::instance(
                                $faker->dateTimeBetween(
                                    $period->start_date,
                                    $period->end_date
                                )
                            ),
                        ]);
                    }
                }
            }
        });
    }
}
