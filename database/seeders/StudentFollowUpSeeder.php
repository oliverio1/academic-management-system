<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Student;
use App\Models\StudentFollowUp;
use App\Models\TeachingAssignment;
use App\Models\TeachingFollowUpTeacher;
use App\Models\StudentFollowUpTeacher;
use Illuminate\Support\Arr;

class StudentFollowUpSeeder extends Seeder
{
    public function run()
    {
        $students = Student::inRandomOrder()->take(10)->get();

        foreach ($students as $student) {

            $followUp = StudentFollowUp::create([
                'student_id' => $student->id,
                'requested_by' => 1,
                'type' => Arr::random(['academic','behavioral','mixed']),
            ]);

            $teacherIds = TeachingAssignment::where('group_id', $student->group_id)
                ->pluck('teacher_id')
                ->unique();

            foreach ($teacherIds as $teacherId) {
                StudentFollowUpTeacher::create([
                    'student_follow_up_id' => $followUp->id,
                    'teacher_id' => $teacherId,
                    'status' => Arr::random(['pending','answered']),
                ]);
            }
        }
    }
}
