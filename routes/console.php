<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\AttendanceJustification;
use App\Models\AcademicSession;
use App\Models\PrefectDailyAttendance;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('partials:process-deadlines')
    ->dailyAt('07:00');

Artisan::command('justifications:backfill-prefect {--dry-run : Solo simula, no escribe cambios}', function () {
    $dryRun = (bool) $this->option('dry-run');

    $justifications = AttendanceJustification::query()
        ->with(['student.groupHistories'])
        ->orderBy('id')
        ->get();

    $processed = 0;
    $created = 0;
    $updated = 0;
    $skipped = 0;

    foreach ($justifications as $justification) {
        $student = $justification->student;
        if (! $student) {
            $skipped++;
            continue;
        }

        $from = optional($justification->from_date)?->toDateString();
        $to = optional($justification->to_date)?->toDateString();

        if (! $from || ! $to) {
            $skipped++;
            continue;
        }

        $sessions = AcademicSession::query()
            ->whereBetween('session_date', [$from, $to])
            ->where('is_cancelled', false)
            ->with('teachingAssignment')
            ->get();

        $daysToJustify = [];
        foreach ($sessions as $session) {
            $groupIdForDate = $student->groupHistories
                ->first(fn ($h) => $session->session_date->gte($h->start_date)
                    && (! $h->end_date || $session->session_date->lte($h->end_date))
                )?->group_id;

            if (! $groupIdForDate) {
                continue;
            }

            if ((int) optional($session->teachingAssignment)->group_id !== (int) $groupIdForDate) {
                continue;
            }

            $daysToJustify[$session->session_date->toDateString()] = (int) $groupIdForDate;
        }

        foreach ($daysToJustify as $date => $groupId) {
            $existing = PrefectDailyAttendance::query()
                ->where('student_id', $student->id)
                ->whereDate('attendance_date', $date)
                ->first();

            if (! $existing) {
                $created++;
                if (! $dryRun) {
                    PrefectDailyAttendance::create([
                        'group_id' => $groupId,
                        'student_id' => $student->id,
                        'attendance_date' => $date,
                        'status' => 'justified',
                        'recorded_by' => $justification->issued_by,
                    ]);
                }
                continue;
            }

            if ($existing->status !== 'justified') {
                $updated++;
                if (! $dryRun) {
                    $existing->update([
                        'group_id' => $groupId,
                        'status' => 'justified',
                        'recorded_by' => $justification->issued_by,
                    ]);
                }
            }
        }

        $processed++;
    }

    $this->info('Backfill de justificantes a prefectura finalizado.');
    $this->line("Justificantes procesados: {$processed}");
    $this->line("Registros creados: {$created}");
    $this->line("Registros actualizados: {$updated}");
    $this->line("Justificantes omitidos: {$skipped}");
    $this->line('Modo: ' . ($dryRun ? 'SIMULACION (sin cambios)' : 'EJECUCION REAL'));
})->purpose('Backfill: marcar justificadas en asistencia global (prefectura) con base en justificantes historicos');
