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
        {--period= : ID del periodo académico (opcional)}';

    protected $description = 'Genera sesiones académicas reales a partir de schedules y periodo académico';

    public function handle()
    {
        $period = $this->option('period')
            ? AcademicPeriod::findOrFail($this->option('period'))
            : AcademicPeriod::where('is_active', true)->first();

        if (!$period) {
            $this->error('No hay periodo académico activo.');
            return;
        }

        $this->info("Generando sesiones para el periodo {$period->id}");

        TeachingAssignment::with('schedules')->each(function ($assignment) use ($period) {

            foreach ($assignment->schedules as $schedule) {

                $start = Carbon::parse($period->start_date);
                $end   = Carbon::parse($period->end_date);

                // day_of_week esperado: 1 (lunes) a 7 (domingo)
                $targetDay = (int) $schedule->day_of_week;

                // Ajustar al primer día válido
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

        $this->info('Sesiones académicas generadas correctamente.');
    }
}
