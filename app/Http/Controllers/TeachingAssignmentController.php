<?php 

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\AcademicPeriod;
use App\Models\Attendance;
use App\Models\AcademicSession;
use App\Services\GradeService;
use App\Services\AcademicPerformanceService;
use Carbon\Carbon;

class TeachingAssignmentController extends Controller
{
    public function edit(Group $group) {
        $subjects = $group->subjects()->where('is_active', true)->get();
        $teachers = Teacher::where('is_active', true)->with('subjects')->get();
        $assignments = TeachingAssignment::where('group_id', $group->id)->get()->keyBy(fn ($a) => $a->subject_id . '_' . $a->teacher_id);
        return view('groups.assignments', compact('group','subjects','teachers','assignments'));
    }

    public function update(Group $group) {
        TeachingAssignment::where('group_id', $group->id)->delete();
        foreach (request('assignments', []) as $subjectId => $teacherId) {
            if ($teacherId) {
                TeachingAssignment::create([
                    'group_id' => $group->id,
                    'subject_id' => $subjectId,
                    'teacher_id' => $teacherId,
                ]);
            }
        }
        return redirect()->route('groups.index')->with('info', 'Asignaciones guardadas correctamente');
    }

    public function myAssignments() {
        $teacher = auth()->user()->teacher;
        $assignments = TeachingAssignment::with(['group', 'subject'])->where('teacher_id', $teacher->id)->get();
        return view('teacher.assignments.index', compact('assignments'));
    }

    public function show(TeachingAssignment $teachingAssignment, GradeService $gradeService, AcademicPerformanceService $performance) {
        abort_if(
            $teachingAssignment->teacher_id !== auth()->user()->teacher->id, 403
        );
        $tab = request('tab', 'evaluation');

        $gradesData = null;

        if ($tab === 'grades') {

            $teachingAssignment->load([
                'group.students.user',
                'evaluationCriteria.activities'
            ]);

            $gradesData = $teachingAssignment->group->students
                ->where('is_active', true)
                ->map(function ($student) use ($teachingAssignment, $gradeService, $performance) {

                    $final = $performance->finalGradeForAssignment($student, $teachingAssignment);

                    return [
                        'student'   => $student,
                        'final'     => $final,
                        'status' => $performance->academicStatus(
                            $student,
                            $teachingAssignment,
                        ),
                        'breakdown' => $gradeService->breakdown(
                            $teachingAssignment,
                            $student
                        ),
                    ];
                });
        }

        $activePeriod = AcademicPeriod::where('modality_id', $teachingAssignment->group->level->modality_id)->where('is_active',1)->first();
        $group = $teachingAssignment->group;

        // total de alumnos activos (una sola vez)
        $totalStudents = $group->students()
            ->where('is_active', true)
            ->count();

        $activities = $teachingAssignment->activities()
            ->where('academic_period_id', optional($activePeriod)->id)
            ->with('evaluationCriterion')
            ->orderBy('created_at')
            ->get();

        $activities->each(function ($activity) use ($teachingAssignment) {

            // INDIVIDUAL
            if ($activity->evaluation_mode === 'individual') {
                $activity->graded_count = $activity->grades()->count();
                return;
            }
        
            // POR EQUIPO
            if ($activity->evaluation_mode === 'team') {
        
                // equipos con calificación
                $gradedTeams = \App\Models\TeamGrade::where('activity_id', $activity->id)
                    ->pluck('team_id');
        
                // alumnos pertenecientes a esos equipos
                $activity->graded_count = \App\Models\Team::whereIn('id', $gradedTeams)
                    ->withCount('students')
                    ->get()
                    ->sum('students_count');
        
                return;
            }
        
            $activity->graded_count = 0;
        });

        $teachingAssignment->load([
            'group',
            'subject',
            'teams.students',
            'practices',
            'evaluationCriteria',
            'group.students'
        ]);

        $totalSessions = AcademicSession::where('teaching_assignment_id', $teachingAssignment->id)
        ->where('academic_period_id', $activePeriod->id)
        ->where('is_cancelled', false)
        ->count();

        $attendanceSummary = $teachingAssignment->group->students()
            ->where('is_active', true)
            ->get()
            ->map(function ($student) use ($teachingAssignment, $activePeriod, $totalSessions) {
    
            $attended = Attendance::where('student_id', $student->id)
            ->where('status', 'present')
            ->whereHas('academicSession', function ($q) use ($teachingAssignment, $activePeriod) {
                $q->where('teaching_assignment_id', $teachingAssignment->id)
                    ->where('academic_period_id', $activePeriod->id)
                    ->where('is_cancelled', false);
            })
            ->count();
    
            return [
                'student' => $student,
                'attended' => $attended,
                'total' => $totalSessions,
                'percentage' => $totalSessions > 0
                    ? round(($attended / $totalSessions) * 100, 1)
                    : 0,
            ];
        });

        $attendanceSessions = AcademicSession::where('teaching_assignment_id', $teachingAssignment->id)
            ->where('academic_period_id', $activePeriod->id)
            ->where('is_cancelled', false)
            ->with('schedule')
            ->orderByDesc('session_date')
            ->get();

        $teams = $teachingAssignment->teams;

        $studentsWithoutTeam = $teachingAssignment->group->students
            ->where('is_active', true)
            ->filter(function ($student) use ($teams) {
                return ! $teams->pluck('students')
                    ->flatten()
                    ->pluck('id')
                    ->contains($student->id);
            });
            
        $teacherAssignments = TeachingAssignment::where('teacher_id',auth()->user()->teacher->id)->where('id', '!=', $teachingAssignment->id)->with('evaluationCriteria')->get();
        return view('teacher.assignments.show', compact(
            'teachingAssignment',
            'teacherAssignments',
            'activePeriod',
            'activities',
            'totalStudents',
            'attendanceSummary',
            'totalSessions',
            'attendanceSessions',
            'tab',
            'gradesData',
            'teams',
            'studentsWithoutTeam'
        ));
    }

    public function create(TeachingAssignment $teachingAssignment) {
        abort_if(
            $teachingAssignment->teacher_id !== auth()->user()->teacher->id,
            403
        );
        abort_if(
            $teachingAssignment->hasGrades(),
            403,
            'No puedes configurar la evaluación porque ya existen calificaciones.'
        );
        if ($teachingAssignment->evaluationCriteria()->exists()) {
            return redirect()
                ->route('teacher.evaluation.edit', $teachingAssignment)
                ->with('info', 'La evaluación ya está configurada. Puedes editarla.');
        }
        $teachingAssignment->load(['subject', 'group']);
        return view('teacher.evaluation.create',compact('teachingAssignment'));
    }
}
