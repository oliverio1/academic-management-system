<?php

namespace App\Console\Commands;

use App\Models\CyclePartial;
use App\Models\EconomicActa;
use App\Models\SchoolCycleGroup;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Notifications\PartialAutoClosedNotification;
use App\Notifications\TeacherPartialDeadlineReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessPartialClosureDeadlines extends Command
{
    protected $signature = 'partials:process-deadlines';
    protected $description = 'Envia recordatorios de cierre docente y aplica cierre automatico por fecha limite.';

    public function handle(): int
    {
        $today = now()->startOfDay();
        $reminderDays = [7, 3, 1, 0];
        $coordinators = User::role('coordinator')->get();

        $partials = CyclePartial::query()
            ->whereNotNull('teacher_capture_deadline_at')
            ->with('schoolCycle')
            ->get();

        foreach ($partials as $partial) {
            $deadline = $partial->teacher_capture_deadline_at;
            if (!$deadline) {
                continue;
            }

            $daysRemaining = $today->diffInDays($deadline->copy()->startOfDay(), false);

            $groupIds = SchoolCycleGroup::query()
                ->where('school_cycle_id', $partial->school_cycle_id)
                ->where('is_active', true)
                ->pluck('group_id')
                ->all();

            if (empty($groupIds)) {
                continue;
            }

            $assignments = TeachingAssignment::query()
                ->with(['teacher.user', 'subject', 'group'])
                ->where('is_active', true)
                ->whereIn('group_id', $groupIds)
                ->whereHas('schedules', function ($query) use ($partial) {
                    $query->where('school_cycle_id', $partial->school_cycle_id)
                        ->where('is_active', true);
                })
                ->get();

            foreach ($assignments as $assignment) {
                $acta = EconomicActa::query()
                    ->where('teaching_assignment_id', $assignment->id)
                    ->where('cycle_partial_id', $partial->id)
                    ->first();

                // Recordatorios solo si aun no ha cerrado el docente.
                if (in_array($daysRemaining, $reminderDays, true) && !$acta) {
                    $teacherUser = $assignment->teacher?->user;
                    if ($teacherUser) {
                        $teacherUser->notify(new TeacherPartialDeadlineReminderNotification(
                            $assignment,
                            $partial,
                            $daysRemaining
                        ));
                    }
                }

                // Auto cierre al vencer (si no existe cierre docente).
                if (now()->greaterThan($deadline) && !$acta) {
                    DB::transaction(function () use ($assignment, $partial, $coordinators) {
                        $newActa = EconomicActa::create([
                            'teaching_assignment_id' => $assignment->id,
                            'cycle_partial_id' => $partial->id,
                            'status' => 'closed',
                            'is_auto_closed' => true,
                            'is_late_closure_acta' => true,
                            'auto_closed_at' => now(),
                            'closed_at' => now(),
                            'notes' => 'Acta administrativa generada por incumplimiento de fecha limite de cierre docente.',
                        ]);

                        $newActa->events()->create([
                            'from_status' => null,
                            'to_status' => 'closed',
                            'changed_by' => null,
                            'changed_at' => now(),
                            'comment' => 'Cierre automatico por fecha limite. Se genero acta por incumplimiento.',
                        ]);

                        $teacherUser = $assignment->teacher?->user;
                        if ($teacherUser) {
                            $teacherUser->notify(new PartialAutoClosedNotification($assignment, $partial));
                        }

                        foreach ($coordinators as $coordinator) {
                            $coordinator->notify(new PartialAutoClosedNotification($assignment, $partial, true));
                        }
                    });
                }
            }
        }

        $this->info('Proceso de recordatorios/cierre de parciales completado.');
        return self::SUCCESS;
    }
}

