<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use App\Models\Attendance;
use App\Models\TeachingAssignment;
use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Services\AttendanceService;
use App\Services\AcademicCalendarService;
use App\Services\EconomicActaLockService;
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

    public function create(AcademicSession $academicSession, EconomicActaLockService $lockService) {
        $teacher = auth()->user()->teacher;

        // 🔒 El profesor debe ser el asignado a la sesión
        abort_if(
            $academicSession->teachingAssignment->teacher_id !== $teacher->id,
            403
        );

        $testCycleEditing = $this->allowsAttendanceEditingForTesting($academicSession);
        $periodDisabled = $academicSession->academicPeriod && ! $academicSession->academicPeriod->is_active && ! $testCycleEditing;

        // ❌ Sesión cancelada
        if ($academicSession->is_cancelled) {
            return redirect()
                ->route('teacher.classes.sessions.index', $academicSession->teachingAssignment)
                ->with('warning', 'Esta sesión fue cancelada.');
        }

        if (! $this->canCaptureAttendanceNow($academicSession)) {
            return redirect()
                ->route('teacher.classes.sessions.index', $academicSession->teachingAssignment)
                ->with('warning', $this->attendanceLockedMessage($academicSession));
        }


        $isReadOnly = $periodDisabled
            || ($academicSession->isAttendanceClosed() && ! $testCycleEditing)
            || ($lockService->isSessionLocked($academicSession) && ! $testCycleEditing);

        // 👥 Alumnos activos según pertenencia REAL
        $students = $this->studentsForAssignment($academicSession->teachingAssignment)->get();

        $attendance = $academicSession
            ->attendances()
            ->get()
            ->keyBy('student_id');

        return view('attendance.create', [
            'session' => $academicSession,
            'students' => $students,
            'attendance' => $attendance,
            'isReadOnly' => $isReadOnly,
            'periodDisabled' => $periodDisabled,
            'testCycleEditing' => $testCycleEditing,
        ]);
    }

    public function store(Request $request, AcademicSession $academicSession, EconomicActaLockService $lockService) {
        $teacher = auth()->user()->teacher;
    
        abort_if(
            $academicSession->teachingAssignment->teacher_id !== $teacher->id,
            403
        );

        $testCycleEditing = $this->allowsAttendanceEditingForTesting($academicSession);

        abort_if(
            $academicSession->academicPeriod && ! $academicSession->academicPeriod->is_active && ! $testCycleEditing,
            403,
            'Este periodo esta deshabilitado por coordinacion. Solo consulta.'
        );

        abort_if(
            ! $this->canCaptureAttendanceNow($academicSession),
            403,
            $this->attendanceLockedMessage($academicSession)
        );
    
        // 🔒 No permitir guardar si ya está cerrada
        abort_if(
            $academicSession->isAttendanceClosed() && ! $testCycleEditing,
            403,
            'La asistencia de esta sesión ya está cerrada.'
        );
    
        abort_if(
            $lockService->isSessionLocked($academicSession) && ! $testCycleEditing,
            403,
            'El parcial de esta sesion ya tiene acta economica cerrada o enviada. Solo consulta.'
        );

        $data = $request->validate([
            'attendance'   => 'required|array',
            'attendance.*' => 'required|in:present,absent,late,justified',
        ]);

        $allowedStudentIds = $this->studentsForAssignment($academicSession->teachingAssignment)
            ->pluck('students.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    
        DB::transaction(function () use ($data, $academicSession, $allowedStudentIds, $testCycleEditing) {
            $allowedStudentMap = array_flip($allowedStudentIds);
            $existing = Attendance::query()
                ->where('academic_session_id', $academicSession->id)
                ->whereIn('student_id', $allowedStudentIds)
                ->get()
                ->keyBy('student_id');
            $now = now();
            $rows = [];

            foreach ($data['attendance'] as $studentId => $status) {
                $studentId = (int) $studentId;
                if (! isset($allowedStudentMap[$studentId])) {
                    continue;
                }

                $attendance = $existing->get($studentId);
                if (! $testCycleEditing && $attendance && $attendance->status === 'justified') {
                    continue;
                }

                if (! $testCycleEditing && $attendance && (bool) $attendance->is_suspension_locked) {
                    continue;
                }

                $mustUnlockTestRow = $testCycleEditing
                    && $attendance
                    && ((bool) $attendance->is_suspension_locked || $attendance->student_suspension_id !== null);

                if ($attendance && $attendance->status === $status && ! $mustUnlockTestRow) {
                    continue;
                }

                $rows[] = [
                    'academic_session_id' => $academicSession->id,
                    'student_id' => $studentId,
                    'status' => $status,
                    'student_suspension_id' => $testCycleEditing ? null : $attendance?->student_suspension_id,
                    'is_suspension_locked' => $testCycleEditing ? false : (bool) ($attendance?->is_suspension_locked ?? false),
                    'created_at' => $attendance?->created_at ?? $now,
                    'updated_at' => $now,
                ];
            }

            if ($rows !== []) {
                Attendance::upsert(
                    $rows,
                    ['academic_session_id', 'student_id'],
                    ['status', 'student_suspension_id', 'is_suspension_locked', 'updated_at']
                );
            }
        });
    
        return redirect()
            ->route('teacher.classes.sessions.index', $academicSession->teachingAssignment)
            ->with('success', 'Asistencia registrada correctamente.');
    }

    public function edit(AcademicSession $academicSession, EconomicActaLockService $lockService) {
        $teacher = auth()->user()->teacher;
    
        abort_if(
            $academicSession->teachingAssignment->teacher_id !== $teacher->id,
            403
        );

        $testCycleEditing = $this->allowsAttendanceEditingForTesting($academicSession);
        $periodDisabled = $academicSession->academicPeriod && ! $academicSession->academicPeriod->is_active && ! $testCycleEditing;
        $isReadOnly = $periodDisabled
            || ($academicSession->isAttendanceClosed() && ! $testCycleEditing)
            || ($lockService->isSessionLocked($academicSession) && ! $testCycleEditing);
    
        $students = $this->studentsForAssignment($academicSession->teachingAssignment)->get();
    
        $attendance = $academicSession
            ->attendances()
            ->get()
            ->keyBy('student_id');
    
        return view('attendance.create', [
            'session'    => $academicSession,
            'students'   => $students,
            'attendance' => $attendance,
            'isReadOnly' => $isReadOnly,
            'periodDisabled' => $periodDisabled,
            'testCycleEditing' => $testCycleEditing,
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
        $assignment->load(['academicSessions.attendances']);
        $students = $this->studentsForAssignment($assignment)->get();
        $sessions = $assignment->academicSessions
            ->filter(function ($session) use ($period) {
                return !$session->is_cancelled
                    && $session->session_date >= $period->start_date
                    && $session->session_date <= $period->end_date;
            })
            ->map(fn ($session) => [
                'academic_session_id' => $session->id,
                'schedule_id' => $session->schedule_id,
                'class_date' => $session->session_date->toDateString(),
            ])
            ->values();

        $this->ensureDefaultAttendances($students, $sessions);
        return view('attendance.massive', ['assignment' => $assignment,'students' => $students,'sessions' => $sessions,]);
    }

    public function storeInline(Request $request, EconomicActaLockService $lockService) {
        $request->validate([
            'schedule_id' => 'required|exists:schedules,id',
            'student_id' => 'required|exists:students,id',
            'class_date' => 'required|date',
            'status' => 'required|in:present,absent,late,justified',
        ]);

        $session = AcademicSession::query()
            ->where('schedule_id', $request->schedule_id)
            ->whereDate('session_date', $request->class_date)
            ->firstOrFail();

        $allowedStudentIds = $this->studentsForAssignment($session->teachingAssignment)
            ->pluck('students.id')
            ->map(fn ($id) => (int) $id)
            ->all();
        abort_if(! in_array((int) $request->student_id, $allowedStudentIds, true), 403);

        $testCycleEditing = $this->allowsAttendanceEditingForTesting($session);

        abort_if(
            $lockService->isSessionLocked($session) && ! $testCycleEditing,
            403,
            'El parcial de esta sesion ya tiene acta economica cerrada o enviada. Solo consulta.'
        );

        $attendance = Attendance::firstOrNew([
            'academic_session_id' => $session->id,
            'student_id' => $request->student_id,
        ]);

        if (
            $testCycleEditing
            || (
                !($attendance->exists && $attendance->status === 'justified')
                && !($attendance->exists && (bool) $attendance->is_suspension_locked)
                && $attendance->status !== $request->status
            )
        ) {
            $attendance->status = $request->status;
            if ($testCycleEditing) {
                $attendance->student_suspension_id = null;
                $attendance->is_suspension_locked = false;
            }
            $attendance->save();
        }

        return response()->json(['ok' => true]);
    }

    public function adjustScoreInline(Request $request, EconomicActaLockService $lockService) {
        $attendance = Attendance::query()
            ->with('academicSession.schedule')
            ->findOrFail($request->attendance_id);

        $testCycleEditing = $this->allowsAttendanceEditingForTesting($attendance->academicSession);

        abort_if(
            $lockService->isSessionLocked($attendance->academicSession) && ! $testCycleEditing,
            403,
            'El parcial de esta sesion ya tiene acta economica cerrada o enviada. Solo consulta.'
        );

        if ($testCycleEditing || ($attendance->status !== 'justified' && !(bool) $attendance->is_suspension_locked)) {
            $payload = ['status' => $request->status];
            if ($testCycleEditing) {
                $payload['student_suspension_id'] = null;
                $payload['is_suspension_locked'] = false;
            }

            $attendance->update($payload);
        }

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
    
                    if (($session['class_date'] ?? null) > $today) {
                        continue;
                    }

                    if (empty($session['academic_session_id'])) {
                        continue;
                    }
    
                    $rows[] = [
                        'academic_session_id' => $session['academic_session_id'],
                        'student_id'  => $student->id,
                        'status'      => 'present',
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ];
                }
            }
    
            Attendance::upsert(
                $rows,
                ['academic_session_id', 'student_id'],
                [] // NO sobrescribe si ya existe
            );
        });
    }

    private function canCaptureAttendanceNow(AcademicSession $academicSession): bool
    {
        if ($this->allowsAttendanceEditingForTesting($academicSession)) {
            return true;
        }

        if ($this->allowsFutureAttendanceCaptureForLocalTesting()) {
            return true;
        }

        $sessionDate = optional($academicSession->session_date)->toDateString();
        if (! $sessionDate) {
            return true;
        }

        $startTime = $academicSession->start_time ?: optional($academicSession->schedule)->start_time;
        if (! $startTime) {
            return true;
        }

        $classStart = Carbon::parse($sessionDate . ' ' . substr((string) $startTime, 0, 8));
        $allowedFrom = $classStart->copy()->subMinutes(10);

        return now()->greaterThanOrEqualTo($allowedFrom);
    }

    private function allowsFutureAttendanceCaptureForLocalTesting(): bool
    {
        return config('app.env') === 'local'
            && (bool) config('attendance.allow_future_capture_local');
    }

    private function allowsAttendanceEditingForTesting(AcademicSession $academicSession): bool
    {
        $cycleCode = (string) (
            $academicSession->teachingAssignment?->schoolCycleGroup?->schoolCycle?->code
            ?? $academicSession->schedule?->schoolCycle?->code
            ?? ''
        );

        return $cycleCode !== ''
            && in_array($cycleCode, config('attendance.editable_cycle_codes_for_testing', []), true);
    }

    private function attendanceLockedMessage(AcademicSession $academicSession): string
    {
        $sessionDate = optional($academicSession->session_date)->toDateString();
        $startTime = $academicSession->start_time ?: optional($academicSession->schedule)->start_time;

        if (! $sessionDate || ! $startTime) {
            return 'Aun no es posible tomar asistencia para esta sesion.';
        }

        $classStart = Carbon::parse($sessionDate . ' ' . substr((string) $startTime, 0, 8));
        $allowedFrom = $classStart->copy()->subMinutes(10);

        return 'La asistencia se habilita 10 minutos antes del horario de clase (' . $allowedFrom->format('d/m/Y H:i') . ').';
    }

    private function studentsForAssignment(TeachingAssignment $assignment)
    {
        if ($assignment->students()->exists()) {
            return $assignment->students()
                ->where('students.is_active', true)
                ->where('students.group_id', (int) $assignment->group_id)
                ->with('user')
                ->orderBy('students.id');
        }

        return $assignment->group->students()
            ->where('is_active', true)
            ->with('user')
            ->orderBy('id');
    }

}


