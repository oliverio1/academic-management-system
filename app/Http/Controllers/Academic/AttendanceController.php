<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\Attendance;
use App\Models\TeachingAssignment;
use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Services\AttendanceService;
use App\Services\AcademicCalendarService;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function index(Schedule $schedule, AcademicCalendarService $calendar)
    {
        $group = $schedule->assignment->group;
        $students = $group->students()->where('is_active', true)->orderBy('id')->get();
        $date = request('date', now()->toDateString());
        $modalityId = optional($schedule->assignment->group->level)->modality_id;
        $isNonWorkingDay = $calendar->isNonWorkingDay(Carbon::parse($date), $modalityId);
        $existing = Attendance::where('schedule_id', $schedule->id)->where('class_date', $date)->get()->keyBy('student_id');
        return view('attendance.index', compact('schedule','students','existing','date','isNonWorkingDay'));
    }

    public function create(AcademicSession $academicSession) {
        $teacher = auth()->user()->teacher;

        // 🔒 El profesor debe ser el asignado a la sesión
        abort_if(
            $academicSession->teachingAssignment->teacher_id !== $teacher->id,
            403
        );

        // ❌ Sesión cancelada
        if ($academicSession->is_cancelled) {
            return redirect()
                ->route('dashboard')
                ->with('warning', 'Esta sesión fue cancelada.');
        }

        // 🔒 Sesión cerrada por el sistema
        if ($academicSession->isAttendanceClosed()) {
            return redirect()
                ->route('attendance.edit', $academicSession)
                ->with('info', 'La asistencia de esta sesión ya está cerrada.');
        }

        // 👥 Alumnos activos según pertenencia REAL
        $students = $academicSession
            ->teachingAssignment
            ->group
            ->students()
            ->where('is_active', true)
            ->get();

        return view('attendance.create', [
            'session'   => $academicSession,
            'students'  => $students,
            'attendance'=> collect(), // vacía
        ]);
    }

    public function store(Request $request, AcademicSession $academicSession) {
        $teacher = auth()->user()->teacher;
    
        abort_if(
            $academicSession->teachingAssignment->teacher_id !== $teacher->id,
            403
        );
    
        // 🔒 No permitir guardar si ya está cerrada
        abort_if(
            $academicSession->isAttendanceClosed(),
            403,
            'La asistencia de esta sesión ya está cerrada.'
        );
    
        $data = $request->validate([
            'attendance'   => 'required|array',
            'attendance.*' => 'required|in:present,absent,late,justified',
        ]);
    
        DB::transaction(function () use ($data, $academicSession) {
    
            foreach ($data['attendance'] as $studentId => $status) {
    
                Attendance::updateOrCreate(
                    [
                        'academic_session_id' => $academicSession->id,
                        'student_id'          => $studentId,
                    ],
                    [
                        'status' => $status,
                    ]
                );
            }
        });
    
        return redirect()
            ->route('dashboard')
            ->with('success', 'Asistencia registrada correctamente.');
    }

    public function edit(AcademicSession $academicSession) {
        $teacher = auth()->user()->teacher;
    
        abort_if(
            $academicSession->teachingAssignment->teacher_id !== $teacher->id,
            403
        );
    
        $students = $academicSession
            ->teachingAssignment
            ->group
            ->students()
            ->where('is_active', true)
            ->get();
    
        $attendance = $academicSession
            ->attendances()
            ->get()
            ->keyBy('student_id');
    
        return view('attendance.create', [
            'session'    => $academicSession,
            'students'   => $students,
            'attendance' => $attendance,
        ]);
    }    

    public function daily(Schedule $schedule) {
        $schedule->load(['assignment.group.students.user','attendances']);
        $students = $schedule->assignment->group->students;
        return view('attendance.daily', compact('schedule', 'students'));
    }

    public function storeDaily(Request $request, Schedule $schedule) {
        foreach ($request->attendances as $item) {
            Attendance::updateOrCreate(
                [
                    'schedule_id' => $schedule->id,
                    'student_id' => $item['student_id'],
                ],
                [
                    'status' => $item['status'],
                ]
            );
        }
        return response()->json(['ok' => true]);
    }

    public function massive(TeachingAssignment $assignment, AttendanceService $attendanceService, AcademicCalendarService $calendar) {
        $period = AcademicPeriod::where('is_active', true)->firstOrFail();
        $assignment->load(['group.students.user','schedules.attendances']);
        $students = $assignment->group->students;
        $modalityId = $assignment->group->level->modality_id;
        $sessions = [];
        foreach ($assignment->schedules as $schedule) {
            foreach ($attendanceService->generateSessions($schedule, $period) as $session) {
                $sessions[] = $session;
            }
        }
        $sessions = collect($sessions)->unique(fn ($s) => $s['schedule_id'].'_'.$s['class_date'])->sortBy('class_date')->values();
        $this->ensureDefaultAttendances($students, $sessions);
        return view('attendance.massive', ['assignment' => $assignment,'students' => $students,'sessions' => $sessions,]);
    }

    public function storeInline(Request $request) {
        Attendance::updateOrCreate(
            [
                'schedule_id' => $request->schedule_id,
                'student_id' => $request->student_id,
                'class_date' => $request->class_date,
            ],
            [
                'status' => $request->status
            ]
        );
        return response()->json(['ok' => true]);
    }

    public function adjustScoreInline(Request $request) {
        Attendance::where('id', $request->attendance_id)
            ->update(['status' => $request->status]);

        return response()->json(['ok' => true]);
    }

    protected function ensureDefaultAttendances($students, $sessions)
    {
        DB::transaction(function () use ($students, $sessions) {
    
            $now = now();
            $today = now()->toDateString();
            $rows = [];
    
            foreach ($students as $student) {
                foreach ($sessions as $session) {
    
                    // ⚠️ USAR class_date
                    if ($session['class_date'] > $today) {
                        continue;
                    }
    
                    $rows[] = [
                        'schedule_id' => $session['schedule_id'],
                        'student_id'  => $student->id,
                        'class_date'  => $session['class_date'],
                        'status'      => 'present',
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ];
                }
            }
    
            Attendance::upsert(
                $rows,
                ['schedule_id', 'student_id', 'class_date'],
                [] // NO sobrescribe si ya existe
            );
        });
    }
}