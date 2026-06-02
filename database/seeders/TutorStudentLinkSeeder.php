<?php

namespace Database\Seeders;

use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;

class TutorStudentLinkSeeder extends Seeder
{
    public function run(): void
    {
        $tutor = User::where('email', 'tutor@tutor.com')->first();

        if (! $tutor) {
            return;
        }

        $alreadyLinked = Student::where('guardian_user_id', $tutor->id)->exists();
        if ($alreadyLinked) {
            return;
        }

        $student = Student::whereNotNull('group_id')
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if (! $student) {
            return;
        }

        $student->update([
            'guardian_user_id' => $tutor->id,
        ]);
    }
}

