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

class StudentController extends Controller
{
    use ActivatableController;

    protected $activeColumn = 'is_active';

    protected function authorizeActivation($model, $action): void
    {
        $this->authorize($action, $model);
    }
    public function index() {
        $groups = Group::get();
        $students = Student::with(['user', 'group'])->get();
        return view('students.index', compact('students', 'groups'));
    }

    public function create() {
        $groups = Group::orderBy('name')->get();
        return view('students.create', compact('groups'));
    }

    public function store(StudentRequest $request) {
        DB::transaction(function () use ($request) {

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
        return redirect()->route('students.index')->with('success', 'Estudiante creado correctamente');
    }

    public function edit(Student $student) {
        $groups = Group::orderBy('name')->get();
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
                    ($a->where('present', true)->count() / max($a->count(),1)) * 100,
                    1
                );
            });
    
        $asistenciaGeneral = round(
            ($student->attendances->where('present', true)->count() / max($student->attendances->count(),1)) * 100,
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
    
            if ($currentHistory && $startDate->lt($currentHistory->start_date)) {
                throw new \Exception(
                    'La fecha efectiva no puede ser anterior al ingreso al grupo actual.'
                );
            }
    
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
    
}
