<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Student;
use App\Models\Attendance;
use App\Models\Grade;
use App\Services\AcademicCalendarService;
use Carbon\Carbon;

class TestAlertsSeeder extends Seeder
{
    public function run(): void
    {
        $calendar = app(AcademicCalendarService::class);

        $students = Student::with('group.level.modality')
            ->orderBy('id')
            ->take(3)
            ->get();

        if ($students->count() < 3) {
            $this->command->error('Se requieren al menos 3 alumnos.');
            return;
        }

        [$student1, $student2, $student3] = $students;

        $modalityId = $student1->group->level->modality_id;

        $endDate = $calendar->getLastSchoolDay(now(), $modalityId);
        $startDate = $calendar->subtractSchoolDays($endDate, 20, $modalityId);

        /*
        |--------------------------------------------------------------------------
        | 🔴 ALERTA 1 – 3 días completos consecutivos
        |--------------------------------------------------------------------------
        */
        $fullAbsenceDays = collect();
        $date = $endDate->copy();

        while ($fullAbsenceDays->count() < 3) {
            if (! $calendar->isNonWorkingDay($date, $modalityId) && ! $date->isWeekend()) {
                $fullAbsenceDays->push($date->copy());
            }
            $date->subDay();
        }

        foreach ($fullAbsenceDays as $day) {
            Attendance::where('student_id', $student1->id)
                ->whereDate('class_date', $day)
                ->update(['status' => 'absent']);
        }

        /*
        |--------------------------------------------------------------------------
        | 🟠 ALERTA 2 – Ausentismo parcial frecuente (6 días)
        |--------------------------------------------------------------------------
        */
        $partialDays = collect();
        $date = $startDate->copy();

        while ($partialDays->count() < 6) {
            if (! $calendar->isNonWorkingDay($date, $modalityId) && ! $date->isWeekend()) {
                $partialDays->push($date->copy());
            }
            $date->addDay();
        }

        foreach ($partialDays as $day) {

            $attendances = Attendance::where('student_id', $student2->id)
                ->whereDate('class_date', $day)
                ->get();

            if ($attendances->count() === 0) {
                continue;
            }

            $half = ceil($attendances->count() / 2);

            $attendances->shuffle()->each(function ($attendance, $index) use ($half) {
                $attendance->status = $index < $half ? 'absent' : 'present';
                $attendance->save();
            });
        }

        /*
        |--------------------------------------------------------------------------
        | 🔴 ALERTA 3 – Materia crítica (< 6 después de 2 semanas)
        |--------------------------------------------------------------------------
        */
        $grades = Grade::where('student_id', $student3->id)
            ->orderBy('created_at')
            ->take(3)
            ->get();

        foreach ($grades as $grade) {
            $grade->score = 4.5;
            $grade->save();
        }

        $this->command->info('Seeder de alertas ejecutado correctamente.');
        $this->command->info('Alumno 1 → Alerta 1');
        $this->command->info('Alumno 2 → Alerta 2');
        $this->command->info('Alumno 3 → Alerta 3');
    }
}