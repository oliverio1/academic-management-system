<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Carbon\Carbon;
use App\Models\Attendance;
use App\Models\AcademicSession;
use App\Models\TeachingAssignment;
use App\Models\AcademicCalendarDay;
use Illuminate\Support\Facades\DB;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $dayMap = [
            'lunes'     => 1,
            'martes'    => 2,
            'miercoles' => 3,
            'miércoles' => 3,
            'jueves'    => 4,
            'viernes'   => 5,
            'sabado'    => 6,
            'sábado'    => 6,
            'domingo'   => 7,
        ];

        // 🔹 Limpiar datos (dev only)
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        Attendance::truncate();
        AcademicSession::truncate();
        
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $holidays = AcademicCalendarDay::pluck('date')->toArray();

        TeachingAssignment::with([
            'group.students.groupHistories',
            'group.level.modality.periods',
            'schedules',
        ])->chunk(20, function ($assignments) use ($dayMap, $holidays) {

            foreach ($assignments as $assignment) {

                $group = $assignment->group;
                if (! $group || $group->students->isEmpty()) {
                    continue;
                }

                $period = optional($group->level?->modality)
                    ->periods
                    ->firstWhere('is_active', true);

                if (! $period || $assignment->schedules->isEmpty()) {
                    continue;
                }

                $start = Carbon::parse($period->start_date);
                $end   = Carbon::parse($period->end_date);

                foreach ($assignment->schedules as $schedule) {

                    $dayKey = mb_strtolower(trim($schedule->day_of_week));
                    if (! isset($dayMap[$dayKey])) {
                        continue;
                    }

                    $targetDay = $dayMap[$dayKey];

                    $current = $start->copy();
                    while ($current->dayOfWeekIso !== $targetDay) {
                        $current->addDay();
                    }

                    while ($current->lte($end)) {

                        // 🔹 Saltar festivos
                        if (in_array($current->toDateString(), $holidays)) {
                            $current->addWeek();
                            continue;
                        }

                        // 🔹 Crear sesión académica real
                        $session = AcademicSession::firstOrCreate(
                            [
                                'schedule_id'   => $schedule->id,
                                'session_date'  => $current->toDateString(),
                            ],
                            [
                                'teaching_assignment_id' => $assignment->id,
                                'academic_period_id'     => $period->id,
                                'start_time'             => $schedule->start_time,
                                'end_time'               => $schedule->end_time,
                            ]
                        );

                        // 🔹 Asistencias por alumno
                        foreach ($group->students as $student) {

                            $history = $student->groupHistories
                                ->firstWhere('group_id', $group->id);

                            if (! $history) {
                                continue;
                            }

                            if ($current->lt(Carbon::parse($history->start_date))) {
                                continue;
                            }

                            if (
                                $history->end_date &&
                                $current->gt(Carbon::parse($history->end_date))
                            ) {
                                continue;
                            }

                            $chance = match ($current->dayOfWeekIso) {
                                Carbon::FRIDAY => 75,
                                default        => 88,
                            };

                            Attendance::create([
                                'academic_session_id' => $session->id,
                                'student_id'          => $student->id,
                                'status'              => rand(1, 100) <= $chance
                                    ? 'present'
                                    : 'absent',
                            ]);
                        }

                        $current->addWeek();
                    }
                }
            }
        });
    }
}