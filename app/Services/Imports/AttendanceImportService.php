<?php

namespace App\Services\Imports;

use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Collection;
use App\Imports\Attendance\AttendanceSheetsImport;
use App\Imports\Attendance\AttendanceWorkbookImport;
use App\Models\Attendance;
use App\Models\AcademicPeriod;
use App\Services\AttendanceService;
use Illuminate\Support\Facades\DB;
use Throwable;

class AttendanceImportService
{
    /**
     * Importar asistencias desde archivo Excel
     */
    public function import(
        UploadedFile $file,
        int $academicPeriodId
    ): ImportResult {
    
        $result = new ImportResult();
    
        $sheets = \Maatwebsite\Excel\Facades\Excel::toCollection(null, $file);
    
        foreach ($sheets as $index => $sheet) {
            try {
                $this->importSheet(
                    $sheet,
                    $academicPeriodId,
                    $result
                );
            } catch (\Throwable $e) {
                $result->addError(
                    "Hoja #{$index}: {$e->getMessage()}"
                );
            }
        }
    
        return $result;
    }

    protected function importSheet(
        Collection $sheet,
        int $academicPeriodId,
        ImportResult $result
    ): void {
    
        // 1. Encabezado A1 → GRUPO-MATERIA
        $header = trim((string) ($sheet[0][0] ?? ''));
    
        if (! str_contains($header, '-')) {
            throw new \Exception(
                "Encabezado inválido en A1. Se esperaba: GRUPO-MATERIA"
            );
        }
    
        $assignment = $this->resolveTeachingAssignment($header);
    
        // 2. Periodo académico
        $period = AcademicPeriod::findOrFail($academicPeriodId);
    
        // 3. Generar TODAS las sesiones reales
        $attendanceService = app(AttendanceService::class);
    
        $sessions = [];
    
        foreach ($assignment->schedules as $schedule) {
            foreach ($attendanceService->generateSessions($schedule, $period) as $session) {
                $sessions[] = $session;
            }
        }
    
        $sessions = collect($sessions)
            ->sortBy('class_date')
            ->values();


        if ($sessions->isEmpty()) {
            $result->addWarning(
                "No se generaron sesiones para {$header}"
            );
            return;
        }
    
        // 4. Leer alumnos (desde fila 2)
        $rows = $sheet->slice(3);
    
        DB::transaction(function () use (
            $rows,
            $sessions,
            $assignment,
            $result
        ) {
    
            foreach ($rows as $rowIndex => $row) {
    
                $studentName = trim((string) ($row[1] ?? ''));
    
                if ($studentName === '') {
                    continue;
                }
    
                $student = $assignment->group
                    ->students()
                    ->whereHas('user', fn ($q) => $q->where('name', $studentName))
                    ->first();
    
                if (! $student) {
                    $result->addWarning(
                        "Alumno no encontrado: {$studentName}"
                    );
                    continue;
                }
    
                // 5. Columnas → sesiones
                foreach ($sessions as $colIndex => $session) {
    
                    // Columna 1 = sesión 0
                    $value = strtolower(trim((string) ($row[$colIndex + 2] ?? '')));
    
                    if ($value === '' || $value === 'x' || $value === 'NA') {
                        $result->addSkipped();
                        continue;
                    }
    
                    $status = match ($value) {
                        '1' => 'present',
                        '0' => 'absent',
                        '2', 'J' => 'justified',
                        default => null,
                    };
    
                    if (! $status) {
                        $result->addWarning(
                            "Valor inválido '{$value}' para {$studentName}"
                        );
                        continue;
                    }
                    
                    Attendance::updateOrCreate(
                        [
                            'student_id'  => $student->id,
                            'schedule_id' => $session['schedule_id'],
                            'class_date'  => $session['class_date'],
                        ],
                        [
                            'status' => $status,
                        ]
                    );
    
                    $result->addUpdated();
                }
            }
        });
    }

    protected function resolveTeachingAssignment(string $header)
    {
        if (! str_contains($header, '-')) {
            throw new \Exception(
                "Encabezado inválido en A1. Se esperaba: GRUPO-MATERIA"
            );
        }
    
        [$groupName, $subjectName] = array_map(
            'trim',
            explode('-', $header, 2)
        );
    
        $group = \App\Models\Group::where('name', $groupName)->first();
    
        if (! $group) {
            throw new \Exception("Grupo '{$groupName}' no encontrado");
        }
    
        $subject = \App\Models\Subject::where('name', $subjectName)->first();
    
        if (! $subject) {
            throw new \Exception("Materia '{$subjectName}' no encontrada");
        }
    
        $assignment = \App\Models\TeachingAssignment::where([
            'group_id'   => $group->id,
            'subject_id' => $subject->id,
        ])->with(['schedules', 'group.students.user'])
          ->first();
    
        if (! $assignment) {
            throw new \Exception(
                "No existe asignación para {$groupName} - {$subjectName}"
            );
        }
    
        return $assignment;
    }
}
