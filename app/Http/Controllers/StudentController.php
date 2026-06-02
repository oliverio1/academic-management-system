<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\User;
use App\Models\Group;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\StudentRequest;
use Carbon\Carbon;
use App\Models\StudentGroupHistory;
use App\Services\AttendanceService;
use App\Services\GradeService;
use App\Http\Controllers\Traits\ActivatableController;
use App\Models\TeachingAssignment;
use App\Models\SchoolCycleGroup;
use App\Models\SchoolCycle;
use Illuminate\Support\Str;

class StudentController extends Controller
{
    use ActivatableController;

    protected $activeColumn = 'is_active';

    protected function authorizeActivation($model, $action): void
    {
        $this->authorize($action, $model);
    }
    public function index() {
        $activeCampusId = (int) session('active_campus_id', 0);
        $allowedGroupIds = $this->activeCampusGroupIds();
        $groups = Group::query()
            ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId), fn ($q) => $q->whereRaw('1 = 0'))
            ->when(!empty($allowedGroupIds), fn ($q) => $q->whereIn('id', $allowedGroupIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->get();
        $students = Student::with(['user', 'group'])
            ->when($activeCampusId > 0, fn ($q) => $q->whereHas('group', fn ($groupQ) => $groupQ->where('campus_id', $activeCampusId)), fn ($q) => $q->whereRaw('1 = 0'))
            ->when(!empty($allowedGroupIds), fn ($q) => $q->whereIn('group_id', $allowedGroupIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->get();
        return view('students.index', compact('students', 'groups'));
    }

    public function create(Request $request) {
        $activeCampusId = (int) session('active_campus_id', 0);
        $cycleId = $request->integer('school_cycle_id') ?: null;
        $allowedGroupIds = $this->activeCampusGroupIds();

        $groups = Group::query()
            ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId), fn ($q) => $q->whereRaw('1 = 0'))
            ->when(!empty($allowedGroupIds), fn ($q) => $q->whereIn('id', $allowedGroupIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->when($cycleId, function ($query) use ($cycleId) {
                $groupIds = SchoolCycleGroup::query()
                    ->where('school_cycle_id', $cycleId)
                    ->where('is_active', true)
                    ->pluck('group_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                if (empty($groupIds)) {
                    $query->whereRaw('1 = 0');
                    return;
                }

                $query->whereIn('id', $groupIds);
            })
            ->orderBy('name')
            ->get();

        return view('students.create', compact('groups'));
    }

    public function store(StudentRequest $request) {
        DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name ?? $request->email,
                'email' => $request->email,
                'password' => Hash::make('123123123'),
            ]);

            $user->assignRole('student');

            $student = Student::create([
                'user_id' => $user->id,
                'group_id' => $request->group_id,
                'enrollment_number' => $request->enrollment_number,
                'is_active' => true,
            ]);
        
            StudentGroupHistory::create([
                'student_id' => $student->id,
                'group_id'   => $request->group_id,
                'start_date' => now(),
                'reason'     => 'ingreso',
            ]);
        });

        if ($request->input('source') === 'active-cycle') {
            return redirect()
                ->route('coordination.students.active-cycle', [
                    'group_id' => $request->group_id,
                ])
                ->with('success', 'Estudiante creado correctamente. Contrasena inicial: 123123123');
        }

        return redirect()->route('students.index')->with('success', 'Estudiante creado correctamente. Contrasena inicial: 123123123');
    }

    public function bulkStore(Request $request)
    {
        $data = $request->validate([
            'group_id' => 'required|exists:groups,id',
            'bulk_rows' => 'required|string',
            'email_domain' => 'nullable|string|max:120',
        ]);

        $allowedGroupIds = $this->activeCampusGroupIds();
        abort_unless(in_array((int) $data['group_id'], $allowedGroupIds, true), 422, 'El grupo seleccionado no pertenece al campus activo.');

        $domain = trim((string) ($data['email_domain'] ?? 'my.ula.edu.mx'));
        if ($domain === '') {
            $domain = 'my.ula.edu.mx';
        }

        $rows = preg_split('/\r\n|\r|\n/', (string) $data['bulk_rows']);
        $rows = array_values(array_filter(array_map('trim', $rows), fn ($line) => $line !== ''));

        if (empty($rows)) {
            return back()->withErrors(['bulk_rows' => 'Debes capturar al menos una fila.'])->withInput();
        }

        $created = 0;
        $skipped = [];

        DB::transaction(function () use ($rows, $data, $domain, &$created, &$skipped) {
            foreach ($rows as $index => $line) {
                [$name, $enrollment, $email] = $this->parseBulkRow($line);

                if ($name === '') {
                    $skipped[] = 'Fila ' . ($index + 1) . ': nombre vacío.';
                    continue;
                }

                $enrollment = $enrollment !== '' ? $this->normalizeEnrollment($enrollment) : $this->generateEnrollmentNumber();
                if (Student::query()->where('enrollment_number', $enrollment)->exists()) {
                    $enrollment = $this->generateEnrollmentNumber();
                }

                if ($email === '') {
                    $email = $this->generateEmailFromEnrollment($enrollment, $domain);
                }

                if (User::query()->where('email', $email)->exists()) {
                    $email = $this->generateUniqueEmail($email);
                }

                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make('123123123'),
                ]);

                $user->assignRole('student');

                $student = Student::create([
                    'user_id' => $user->id,
                    'group_id' => (int) $data['group_id'],
                    'enrollment_number' => $enrollment,
                    'is_active' => true,
                ]);

                StudentGroupHistory::create([
                    'student_id' => $student->id,
                    'group_id' => (int) $data['group_id'],
                    'start_date' => now(),
                    'reason' => 'ingreso',
                ]);

                $created++;
            }
        });

        $message = "Alta masiva finalizada. Creados: {$created}.";
        if (! empty($skipped)) {
            $message .= ' Omitidos: ' . count($skipped) . '.';
        }

        return redirect()->route('students.index')->with('success', $message);
    }

    public function edit(Student $student) {
        $activeCampusId = (int) session('active_campus_id', 0);
        if ($activeCampusId > 0 && (int) optional($student->group)->campus_id !== $activeCampusId) {
            abort(403, 'No puedes editar alumnos de otro campus.');
        }

        $allowedGroupIds = $this->activeCampusGroupIds();
        $groups = Group::query()
            ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId), fn ($q) => $q->whereRaw('1 = 0'))
            ->when(!empty($allowedGroupIds), fn ($q) => $q->whereIn('id', $allowedGroupIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->orderBy('name')
            ->get();
        return view('students.edit', compact('student', 'groups'));
    }

    public function update(StudentRequest $request, Student $student) {
        if ($request->group_id != $student->group_id) {
            abort(403, 'El cambio de grupo debe realizarse mediante el proceso académico.');
        }
        DB::transaction(function () use ($request, $student) {
            $student->user->update([
                'name' => $request->name,
            ]);
            $student->update([
                'group_id' => $request->group_id,
                'enrollment_number' => $request->enrollment_number,
                'phone' => $request->phone,
                'address' => $request->address,
                'is_active' => $request->has('is_active'),
            ]);
        });
        return redirect()->route('students.index')->with('success', 'Estudiante actualizado');
    }



    public function show(Student $student) {
        $student->load([
            'user',
            'group',
            'grades',        // calificaciones
            'attendances',   // asistencias
            'followUps',      // seguimientos (si tienes)
            'groupHistories.group.level'
        ]);
    
        // 🔢 PROMEDIOS
        $promediosPorParcial = $student->grades
            ->groupBy('partial')
            ->map(fn($g) => round($g->avg('grade'), 2));
    
        $promedioGeneral = round($student->grades->avg('grade'), 2);
    
        // 🟢 ASISTENCIA
        $asistenciaPorParcial = $student->attendances
            ->groupBy('partial')
            ->map(function ($a) {
                return round(
                    ($a->whereIn('status', ['present', 'late', 'justified'])->count() / max($a->count(),1)) * 100,
                    1
                );
            });
    
        $asistenciaGeneral = round(
            ($student->attendances->whereIn('status', ['present', 'late', 'justified'])->count() / max($student->attendances->count(),1)) * 100,
            1
        );
    
        return view('students.show', compact(
            'student',
            'promediosPorParcial',
            'promedioGeneral',
            'asistenciaPorParcial',
            'asistenciaGeneral'
        ));
    }

    public function changeGroup(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'group_id'   => 'required|exists:groups,id',
            'start_date' => 'required|date',
            'reason'     => 'required|string|max:255',
        ]);
    
        $student = Student::findOrFail($request->student_id);
        $newGroup = Group::findOrFail($request->group_id);
    
        if ($student->group_id == $newGroup->id) {
            return back()->withErrors(
                'El alumno ya pertenece a ese grupo.'
            );
        }
    
        $startDate = Carbon::parse($request->start_date);
    
        DB::transaction(function () use ($student, $newGroup, $startDate, $request) {
    
            $currentHistory = StudentGroupHistory::where('student_id', $student->id)
                ->whereNull('end_date')
                ->first();
    
            // Validación deshabilitada temporalmente para permitir cambios de grupo.
            // if ($currentHistory && $startDate->startOfDay()->lt($currentHistory->start_date->startOfDay())) {
            //     throw new \Exception(
            //         'La fecha efectiva no puede ser anterior al ingreso al grupo actual.'
            //     );
            // }
    
            // Cerrar historial actual
            if ($currentHistory) {
                $currentHistory->update([
                    'end_date' => $startDate,
                ]);
            }
    
            // Detectar tipo de cambio
            $reason = $student->group->level_id === $newGroup->level_id
                ? $request->reason // cambio de grupo
                : 'cambio de nivel: ' . $request->reason;
    
            // Crear nuevo historial
            StudentGroupHistory::create([
                'student_id' => $student->id,
                'group_id'   => $newGroup->id,
                'start_date' => $startDate,
                'end_date'   => null,
                'reason'     => $reason,
            ]);
    
            // Actualizar grupo actual
            $student->update([
                'group_id' => $newGroup->id,
            ]);
        });
    
        return back()->with(
            'success',
            'Cambio de grupo realizado correctamente.'
        );
    }

    public function groupImpact(
        Student $student,
        AttendanceService $attendanceService,
        GradeService $gradeService
    ) {
        $student = Student::find($student->id);

        if (! $student) {
            return response()->json([
                'attendance' => null,
                'final' => null,
                'error' => 'Alumno no encontrado'
            ], 404);
        }
        try {
            $assignment = TeachingAssignment::where('group_id', $student->group_id)
                ->first();
        
            if (! $assignment) {
                return response()->json([
                    'attendance' => null,
                    'final' => null,
                    'has_data' => false,
                ]);
            }
        
            $history = StudentGroupHistory::where('student_id', $student->id)
                ->where('group_id', $student->group_id)
                ->whereNull('end_date')
                ->first();
        
            $from = $history?->start_date
                ? \Carbon\Carbon::parse($history->start_date)
                : null;
        
            $to = now();
        
            $attendance = $attendanceService
                ->attendancePercentage($assignment, $student, $from, $to);
        
            $final = $gradeService
                ->finalGrade($assignment, $student, $from, $to);
        
            return response()->json([
                'attendance' => $attendance,
                'final' => $final,
                'has_data' => $attendance !== null || $final !== null,
            ]);
    
        } catch (\Throwable $e) {
    
            \Log::error('groupImpact error', [
                'student_id' => $student->id,
                'error' => $e->getMessage(),
            ]);
    
            return response()->json([
                'attendance' => null,
                'final' => null,
                'error' => true,
            ], 200);
        }
    }

    public function groupHistory(Student $student)
    {
        $histories = $student->groupHistories()
            ->with(['group.level'])
            ->orderByDesc('start_date')
            ->get();
    
        $levels = $histories
            ->pluck('group.level_id')
            ->unique()
            ->count();
    
        $response = [
            'has_level_change' => $levels > 1,
            'changes_count'    => $histories->count(),
            'histories'        => $histories
                ->take(3)
                ->map(function ($history) {
                    return [
                        'group'  => $history->group->name,
                        'level'  => $history->group->level->name,
                        'from'   => $history->start_date->format('d/m/Y'),
                        'to'     => $history->end_date
                            ? $history->end_date->format('d/m/Y')
                            : 'Actual',
                        'reason' => $history->reason,
                    ];
                })
                ->values(),
        ];
    
        return response()->json($response);
    }

    public function deactivate(Request $request, Student $student)
    {
        $this->authorize('deactivate', $student);

        $data = $request->validate([
            'deactivation_reason' => ['required', 'string', 'max:255'],
        ]);

        if (! $student->is_active) {
            return back()->with('info', 'Estudiante ya esta inactivo.');
        }

        DB::transaction(function () use ($student, $data) {
            $currentHistory = StudentGroupHistory::query()
                ->where('student_id', $student->id)
                ->whereNull('end_date')
                ->orderByDesc('start_date')
                ->first();

            if ($currentHistory) {
                $existingReason = trim((string) $currentHistory->reason);
                $newReason = 'baja: ' . trim((string) $data['deactivation_reason']);

                $currentHistory->update([
                    'end_date' => now()->toDateString(),
                    'reason' => $existingReason !== '' ? ($existingReason . ' | ' . $newReason) : $newReason,
                ]);
            }

            $student->update(['is_active' => false]);
        });

        return back()->with('success', 'Estudiante dado de baja correctamente.');
    }

    public function activate(Student $student)
    {
        $this->authorize('activate', $student);

        if ($student->is_active) {
            return back()->with('info', 'Estudiante ya esta activo.');
        }

        $student->update(['is_active' => true]);

        return back()->with('success', 'Estudiante activado correctamente.');
    }

    private function activeCampusGroupIds(): array
    {
        $activeCampusId = (int) session('active_campus_id', 0);
        if ($activeCampusId <= 0) {
            return [0];
        }

        $activeCycleId = SchoolCycle::query()
            ->where('is_active', true)
            ->whereHas('campuses', fn ($q) => $q->where('campuses.id', $activeCampusId))
            ->orderByDesc('start_date')
            ->value('id');

        if (! $activeCycleId) {
            return [0];
        }

        return SchoolCycleGroup::query()
            ->where('school_cycle_id', (int) $activeCycleId)
            ->where('campus_id', $activeCampusId)
            ->where('is_active', true)
            ->pluck('group_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function parseBulkRow(string $line): array
    {
        $parts = preg_split('/\s*[,\|\t;]\s*/', $line);
        $parts = array_map(fn ($value) => trim((string) $value), $parts ?: []);

        $name = $parts[0] ?? '';
        $enrollment = $parts[1] ?? '';
        $email = $parts[2] ?? '';

        return [$name, $enrollment, $email];
    }

    private function normalizeEnrollment(string $enrollment): string
    {
        $value = preg_replace('/\s+/', '', trim($enrollment)) ?? '';
        return strtoupper($value);
    }

    private function generateEnrollmentNumber(): string
    {
        do {
            $value = 'TMP' . now()->format('ymd') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Student::query()->where('enrollment_number', $value)->exists());

        return $value;
    }

    private function generateEmailFromEnrollment(string $enrollment, string $domain): string
    {
        $local = Str::lower(preg_replace('/[^a-zA-Z0-9._-]/', '', $enrollment) ?: 'alumno');
        $email = $local . '@' . $domain;

        return $this->generateUniqueEmail($email);
    }

    private function generateUniqueEmail(string $email): string
    {
        [$localPart, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $localPart = $localPart !== '' ? $localPart : 'alumno';
        $domain = $domain !== '' ? $domain : 'my.ula.edu.mx';

        $candidate = $localPart . '@' . $domain;
        $suffix = 1;
        while (User::query()->where('email', $candidate)->exists()) {
            $candidate = $localPart . '.' . $suffix . '@' . $domain;
            $suffix++;
        }

        return $candidate;
    }
    
}
