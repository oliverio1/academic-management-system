<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\AcademicPeriod;
use App\Models\TeachingAssignment;
use App\Models\AcademicSession;

class GenerateAcademicSessions extends Command
{
    protected $signature = 'academic:sessions:generate 
        {--period= : ID del periodo academico (opcional)}';

    protected $description = 'Genera sesiones academicas reales a partir de schedules y periodo academico';

    public function handle()
    {
        $period = $this->option('period')
            ? AcademicPeriod::findOrFail($this->option('period'))
            : AcademicPeriod::where('is_active', true)->first();

        if (! $period) {
            $this->error('No hay periodo academico activo.');
            return;
        }

        $this->info("Generando sesiones para el periodo {$period->id}");

        TeachingAssignment::with('schedules')->each(function ($assignment) use ($period) {
            foreach ($assignment->schedules as $schedule) {
                $start = Carbon::parse($period->start_date);
                $end = Carbon::parse($period->end_date);

                $targetDay = $this->resolveDayOfWeekIso($schedule->day_of_week);

                if ($targetDay === null) {
                    $this->warn("Schedule {$schedule->id} ignorado: day_of_week invalido ({$schedule->day_of_week}).");
                    continue;
                }

                $current = $start->copy();
                while ($current->dayOfWeekIso !== $targetDay) {
                    $current->addDay();
                }

                while ($current->lte($end)) {
                    AcademicSession::firstOrCreate(
                        [
                            'schedule_id' => $schedule->id,
                            'session_date' => $current->toDateString(),
                        ],
                        [
                            'teaching_assignment_id' => $assignment->id,
                            'academic_period_id' => $period->id,
                            'start_time' => $schedule->start_time,
                            'end_time' => $schedule->end_time,
                        ]
                    );

                    $current->addWeek();
                }
            }
        });

        $this->info('Sesiones academicas generadas correctamente.');
    }

    private function resolveDayOfWeekIso(mixed $rawDay): ?int
    {
        if (is_numeric($rawDay)) {
            $day = (int) $rawDay;
            return ($day >= 1 && $day <= 7) ? $day : null;
        }

        $day = strtolower(trim((string) $rawDay));

        $map = [
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
            'sunday' => 7,
            'lunes' => 1,
            'martes' => 2,
            'miercoles' => 3,
            'mi?rcoles' => 3,
            'jueves' => 4,
            'viernes' => 5,
            'sabado' => 6,
            's?bado' => 6,
            'domingo' => 7,
        ];

        return $map[$day] ?? null;
    }
}
