<?php

namespace App\Services\Imports;

use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\Grade;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\AttendanceService;

class GradesImportService
{
    public function import(
        UploadedFile $file,
        int $academicPeriodId
    ): ImportResult {

        $result = new ImportResult();

        $sheets = Excel::toCollection(null, $file);

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
    
        // A1 = GRUPO-MATERIA
        $header = trim((string) ($sheet[0][0] ?? ''));
    
        if (! str_contains($header, '-')) {
            throw new \Exception(
                "Encabezado inválido en A1. Se esperaba: GRUPO-MATERIA"
            );
        }
    
        $assignment = $this->resolveTeachingAssignment($header);
        $period = AcademicPeriod::findOrFail($academicPeriodId);
    
        /** -------------------------------------------------
         * Generar sesiones (MISMA lógica que asistencias)
         * ------------------------------------------------- */
        $attendanceService = app(\App\Services\AttendanceService::class);
    
        $sessions = [];
    
        foreach ($assignment->schedules as $schedule) {
            foreach ($attendanceService->generateSessions($schedule, $period) as $s) {
                $sessions[] = $s;
            }
        }
    
        $sessions = collect($sessions)
            ->sortBy('class_date')
            ->values();
    
        if ($sessions->isEmpty()) {
            throw new \Exception(
                "No se pudieron generar sesiones para asignar fechas a las actividades"
            );
        }
    
        DB::transaction(function () use (
            $sheet,
            $assignment,
            $period,
            $sessions,
            $result
        ) {
    
            /** -------------------------------------------------
             * 1️⃣ Resolver criterio "Apuntes"
             * ------------------------------------------------- */
            $apuntesCriterion = \App\Models\EvaluationCriterion::where([
                'teaching_assignment_id' => $assignment->id,
                'name'                   => 'Apuntes',
            ])->first();
    
            if (! $apuntesCriterion) {
                throw new \Exception(
                    "No existe el criterio 'Apuntes' para la asignación {$assignment->id}"
                );
            }
    
            /** -------------------------------------------------
             * 2️⃣ Borrar actividades existentes del periodo
             * ------------------------------------------------- */
            Activity::where('teaching_assignment_id', $assignment->id)
                ->where('academic_period_id', $period->id)
                ->delete();
    
            /** -------------------------------------------------
             * 3️⃣ Crear actividades (fila 2, col C+)
             * ------------------------------------------------- */
            $activityRow = $sheet[2] ?? collect();
    
            $activities = [];
            $order = 1;
    
            foreach ($activityRow as $colIndex => $cell) {
    
                // Ignorar columnas A y B
                if ($colIndex < 2) {
                    continue;
                }
    
                $name = trim((string) $cell);
    
                if ($name === '' || $this->isCalculatedColumn($name)) {
                    continue;
                }
    
                // Asignar fecha según sesión (por orden)
                $sessionIndex = $order - 1;
                $session = $sessions->get($sessionIndex, $sessions->last());
    
                $activities[$colIndex] = Activity::create([
                    'teaching_assignment_id'  => $assignment->id,
                    'academic_period_id'      => $period->id,
                    'evaluation_criterion_id' => $apuntesCriterion->id,
                    'title'                   => $name,   // ⚠️ usa "name" si así es tu tabla
                    'max_score'               => 10,
                    'due_date'                => $session['class_date'],
                    'order'                   => $order,
                ]);
    
                $order++;               // 👈 CLAVE
                $result->addCreated();
            }
    
            if (empty($activities)) {
                $result->addWarning(
                    "No se detectaron actividades en {$assignment->group->name}"
                );
                return;
            }
    
            /** -------------------------------------------------
             * 4️⃣ Importar calificaciones (fila 3+)
             * ------------------------------------------------- */
            $rows = $sheet->slice(3); // desde fila 3
    
            foreach ($rows as $row) {
    
                // Alumno en columna B
                $studentName = trim((string) ($row[1] ?? ''));
    
                if ($studentName === '') {
                    continue;
                }
    
                $student = $assignment->group
                    ->students()
                    ->whereHas('user', function ($q) use ($studentName) {
                        $q->whereRaw(
                            'LOWER(name) = ?',
                            [mb_strtolower($studentName)]
                        );
                    })
                    ->first();
    
                if (! $student) {
                    $result->addWarning(
                        "Alumno no encontrado: {$studentName}"
                    );
                    continue;
                }
    
                foreach ($activities as $colIndex => $activity) {

                    $rawValue = trim((string) ($row[$colIndex] ?? ''));
                    $value = strtoupper($rawValue);
                
                    if ($value === '' || $value === 'N/A') {
                        $result->addSkipped();
                        continue;
                    }
                
                    if (in_array($value, ['SI', 'SÍ', 'J', 'EX'], true)) {
                        $score = 10;
                    } elseif (is_numeric($value)) {
                        $score = min((float) $value, 10);
                    } else {
                        $result->addSkipped();
                        continue;
                    }
                
                    Grade::updateOrCreate(
                        [
                            'student_id'  => $student->id,
                            'activity_id' => $activity->id,
                        ],
                        [
                            'score' => $score,
                        ]
                    );
                
                    $result->addUpdated();
                }
            }
        });
    }

    protected function isCalculatedColumn(string $name): bool
    {
        $upper = mb_strtoupper($name);

        return str_contains($upper, 'FINAL')
            || str_contains($upper, 'PROMEDIO')
            || str_contains($upper, 'CONTINUA')
            || str_contains($upper, 'PEDAGOGICA')
            || str_contains($upper, 'PROYECTO')
            || str_contains($upper, 'EXAMEN');
    }

    protected function resolveTeachingAssignment(string $header)
    {
        [$groupName, $subjectName] = array_map(
            'trim',
            explode('-', $header, 2)
        );

        $group = \App\Models\Group::where('name', $groupName)->first();
        $subject = \App\Models\Subject::where('name', $subjectName)->first();

        if (! $group || ! $subject) {
            throw new \Exception(
                "Grupo o materia no encontrada: {$header}"
            );
        }

        $assignment = \App\Models\TeachingAssignment::where([
            'group_id'   => $group->id,
            'subject_id' => $subject->id,
        ])->with('group.students.user')->first();

        if (! $assignment) {
            throw new \Exception(
                "No existe asignación para {$header}"
            );
        }

        return $assignment;
    }
}