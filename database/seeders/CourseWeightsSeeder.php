<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TeachingAssignment;
use App\Models\CourseWeight;
use Faker\Factory as Faker;

class CourseWeightsSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('es_MX');
        $activityTypes = ['exam', 'project', 'homework', 'quiz'];
        TeachingAssignment::chunk(50, function ($assignments) use ($faker, $activityTypes) {
            foreach ($assignments as $assignment) {
                if ($assignment->weights()->exists()) {
                    continue;
                }
                $exam = $faker->numberBetween(35, 50);
                $project = $faker->numberBetween(20, 35);
                $homework = $faker->numberBetween(10, 25);
                $used = $exam + $project + $homework;
                $quiz = max(0, 100 - $used);
                $weights = [
                    'exam' => $exam,
                    'project' => $project,
                    'homework' => $homework,
                    'quiz' => $quiz,
                ];
                foreach ($weights as $type => $weight) {
                    CourseWeight::create([
                        'teaching_assignment_id' => $assignment->id,
                        'activity_type' => $type,
                        'weight' => $weight,
                    ]);
                }
            }
        });
    }
}