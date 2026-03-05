<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\ModalityController;
use App\Http\Controllers\LevelController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\GroupSubjectController;
use App\Http\Controllers\TeacherSubjectController;
use App\Http\Controllers\TeachingAssignmentController;
use App\Http\Controllers\AcademicPeriodController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GroupStudentController;
use App\Http\Controllers\ReportCardController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CourseWeightController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\PracticeController;
use App\Http\Controllers\StudentPracticeController;
use App\Http\Controllers\AcademicCalendarDayController;
use App\Http\Controllers\EvaluationCriterionController;
use App\Http\Controllers\EvaluationSchemeCloneController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\TeacherPerformanceController;
use App\Http\Controllers\TeacherPerformanceDetailController;
use App\Http\Controllers\ActivityCloneController;
use App\Http\Controllers\AdminAttendanceAlertController;
use App\Http\Controllers\AdminAcademicAlertController;
use App\Http\Controllers\StudentFollowUpController;
use App\Http\Controllers\TeacherFollowUpController;
use App\Http\Controllers\StudentFollowUpResponseController;
use App\Http\Controllers\CoordinationStudentController;
use App\Http\Controllers\Coordination\StudentReportCardController;
use App\Http\Controllers\ActivityGradingController;
use App\Http\Controllers\AttendanceJustificationController;
use App\Http\Controllers\SessionActivityController;
use App\Http\Controllers\TeacherClassController;
use App\Http\Controllers\TeacherClassSessionController;
use App\Http\Controllers\TeacherStudentController;
use App\Http\Controllers\TeacherJustificationController;
use App\Http\Controllers\CoordinationAttendanceRiskController;
use App\Http\Controllers\AcademicResolutionController;
use App\Http\Controllers\EvaluationCriteriaController;

require __DIR__.'/imports.php';

Route::get('/', function () {
    return view('welcome');
});

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Route::middleware(['auth'])->get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::middleware(['auth'])->get('students/{student}/report-card', [ReportCardController::class, 'show'])->name('students.report-card');
Route::get('students/{student}/report-card/pdf',[ReportCardController::class, 'pdf'])->name('students.report-card.pdf');

Route::middleware(['auth'])->group(function () {
    Route::get('/home', function () {
        return view('home');
    })->name('home');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

Route::middleware(['auth', 'role:admin'])->group(function() {
    Route::resource('users', UserController::class);
    Route::post('/users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
    Route::post('/users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');

    Route::resource('modalities', ModalityController::class);
    Route::post('/modalities/{modality}/activate', [ModalityController::class, 'activate'])->name('modalities.activate');
    Route::post('/modalities/{modality}/deactivate', [ModalityController::class, 'deactivate'])->name('modalities.deactivate');

    Route::resource('levels', LevelController::class);
    Route::post('/levels/{level}/activate', [LevelController::class, 'activate'])->name('levels.activate');
    Route::post('/levels/{level}/deactivate', [LevelController::class, 'deactivate'])->name('levels.deactivate');
});

Route::middleware(['auth', 'role:admin|coordination'])->prefix('coordination')->name('coordination.')->group(function () {
        Route::get('students/attendances-risk', [CoordinationAttendanceRiskController::class, 'index'])->name('students.attendances-risk');
        Route::get('follow-ups', [StudentFollowUpController::class, 'index'])->name('follow-ups.index');
        Route::get('follow-ups/critical', [StudentFollowUpController::class, 'critical'])->name('follow-ups.critical');
        Route::get('follow-ups/create', [StudentFollowUpController::class, 'create'])->name('follow-ups.create');
        Route::post('follow-ups', [StudentFollowUpController::class, 'store'])->name('follow-ups.store');
        Route::get('follow-ups/{followUp}', [StudentFollowUpController::class, 'show'])->name('follow-ups.show');
        Route::get('follow-ups/responses/{assignment}',[StudentFollowUpResponseController::class, 'show'])->name('follow-ups.responses.show');
        Route::resource('students', CoordinationStudentController::class)->only(['index', 'show']);

    });

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('groups/{group}/students',[GroupStudentController::class, 'edit'])->name('groups.students.edit');
    Route::post('groups/{group}/students',[GroupStudentController::class, 'update'])->name('groups.students.update');

    Route::get('academic-periods', [AcademicPeriodController::class, 'index'])->name('academic-periods.index');
    Route::resource('academic-periods', AcademicPeriodController::class);
    Route::post('/academic-periods/{period}/activate', [AcademicPeriodController::class, 'activate'])->name('academic-periods.activate');
    Route::post('/academic-periods/{period}/deactivate', [AcademicPeriodController::class, 'deactivate'])->name('academic-periods.deactivate');

    Route::resource('students', StudentController::class);
    Route::resource('academic-calendar-days', AcademicCalendarDayController::class);

    Route::get('groups/{group}/assignments/{assignment}/weights',[CourseWeightController::class, 'edit'])->name('weights.edit');
    Route::post('groups/{group}/assignments/{assignment}/weights',[CourseWeightController::class, 'update'])->name('weights.update');

    Route::get('groups/{group}/assignments/{assignment}/schedules',[ScheduleController::class, 'index'])->name('schedules.index');
    Route::post('groups/{group}/assignments/{assignment}/schedules',[ScheduleController::class, 'store'])->name('schedules.store');
    Route::post('schedules/{schedule}/deactivate',[ScheduleController::class, 'deactivate'])->name('schedules.deactivate');

    Route::post('students/{student}/deactivate', [StudentController::class, 'deactivate'])->name('students.deactivate');
    Route::post('students/{student}/activate', [StudentController::class, 'activate'])->name('students.activate');
    Route::post('/students/change-group',[StudentController::class, 'changeGroup'])->name('students.change-group');
    Route::get('/students/{student}/group-impact',[StudentController::class, 'groupImpact'])->name('students.group-impact');
    Route::post('/admin/academic-resolutions',[AcademicResolutionController::class, 'store'])->name('academic-resolutions.store');
    Route::get('/students/{student}/group-history',[StudentController::class, 'groupHistory'])->name('students.groupHistory');

    Route::get('subjects/{subject}/teachers',[TeacherSubjectController::class, 'edit'])->name('subjects.teachers.assign');
    Route::post('subjects/{subject}/teachers',[TeacherSubjectController::class, 'update'])->name('subjects.teachers.update');

    Route::get('teachers/{teacher}/subjects',[TeacherSubjectController::class, 'editTeacher'])->name('teachers.subjects.assign');
    Route::post('teachers/{teacher}/subjects',[TeacherSubjectController::class, 'updateTeacher'])->name('teachers.subjects.update');

    Route::resource('teachers', TeacherController::class);
    Route::post('teachers/{teacher}/deactivate', [TeacherController::class, 'deactivate'])->name('teachers.deactivate');
    Route::post('teachers/{teacher}/activate', [TeacherController::class, 'activate'])->name('teachers.activate');

    Route::get('groups/{group}/subjects',[GroupSubjectController::class, 'edit'])->name('groups.subjects.edit');
    Route::post('groups/{group}/subjects',[GroupSubjectController::class, 'update'])->name('groups.subjects.update');

    Route::get('groups/{group}/assignments', [TeachingAssignmentController::class, 'edit'])->name('groups.assignments.edit');
    Route::post('groups/{group}/assignments', [TeachingAssignmentController::class, 'update'])->name('groups.assignments.update');

    Route::resource('groups', GroupController::class);
    Route::post('groups/{group}/deactivate', [GroupController::class, 'deactivate'])->name('groups.deactivate');
    Route::post('groups/{group}/activate', [GroupController::class, 'activate'])->name('groups.activate');

    Route::resource('subjects', SubjectController::class);
    Route::post('subjects/{subject}/deactivate',[SubjectController::class, 'deactivate'])->name('subjects.deactivate');
    Route::post('subjects/{subject}/activate',[SubjectController::class, 'activate'])->name('subjects.activate');

    Route::get('/attendance-justifications', [AttendanceJustificationController::class, 'index'])->name('attendance_justifications.index');
    Route::get('/attendance-justifications/create', [AttendanceJustificationController::class, 'create'])->name('attendance_justifications.create');
    Route::post('/attendance-justifications', [AttendanceJustificationController::class, 'store'])->name('attendance_justifications.store');

});

Route::middleware(['auth', 'role:teacher'])->group(function () {
    Route::get('teacher/assignments', [TeachingAssignmentController::class, 'myAssignments'])->name('teacher.assignments');
    Route::get('assignments/{teachingAssignment}/evaluation/create',[EvaluationCriterionController::class, 'create'])->name('teacher.evaluation.create');
    Route::get('assignments/{teachingAssignment}/evaluation/edit',[EvaluationCriterionController::class, 'edit'])->name('teacher.evaluation.edit');
    Route::post('assignments/{teachingAssignment}/evaluation',[EvaluationCriterionController::class, 'store'])->name('teacher.evaluation.store');
    Route::patch('assignments/{teachingAssignment}/evaluation',[EvaluationCriterionController::class, 'update'])->name('teacher.evaluation.update');
    Route::get('assignments/{teachingAssignment}', [TeachingAssignmentController::class, 'show'])->name('assignments.show');
    Route::get('/teacher/assignments/{teachingAssignment}/evaluation',[EvaluationCriteriaController::class, 'index'])->name('teacher.assignments.evaluation');

    Route::get('/teacher/classes', [TeacherClassController::class, 'index'])->name('teacher.classes.index');
    Route::get('/teacher/classes/calendar', [TeacherClassController::class, 'calendar'])->name('teacher.classes.calendar');
    Route::get('/teacher/classes/{teachingAssignment}', [TeacherClassController::class, 'show'])->name('teacher.classes.show');
    Route::get('/teacher/classes/{teachingAssignment}/sessions',[TeacherClassSessionController::class, 'index'])->name('teacher.classes.sessions.index');

    Route::get('/teacher/students', [TeacherStudentController::class, 'index'])->name('teacher.students.index');
    Route::get('/teacher/activities', [TeacherStudentController::class, 'index'])->name('teacher.activities.index');
    Route::get('/teacher/reports', [TeacherStudentController::class, 'index'])->name('teacher.reports.index');

    Route::get('/teacher/students', [TeacherStudentController::class, 'index'])->name('teacher.students.index');
    Route::get('/teacher/students/group/{group}', [TeacherStudentController::class, 'group'])->name('teacher.students.group');
    Route::get('/teacher/students/{student}', [TeacherStudentController::class, 'show'])->name('teacher.students.show');

    // Route::get('schedules/{schedule}/attendance',[AttendanceController::class, 'index'])->name('attendance.index');
    // Route::post('schedules/{schedule}/attendance',[AttendanceController::class, 'store'])->name('attendance.store');
    
    // Route::get('schedules/{schedule}/attendance/daily',[AttendanceController::class, 'daily'])->name('attendance.daily');
    // Route::post('schedules/{schedule}/attendance/daily',[AttendanceController::class, 'storeDaily'])->name('attendance.daily.store');
    Route::get('assignments/{assignment}/attendance/massive',[AttendanceController::class, 'massive'])->name('attendance.massive');
    // Route::post('/attendance/inline', [AttendanceController::class, 'storeInline'])->name('attendance.inline');
    Route::post('/attendance/adjust-inline', [AttendanceController::class, 'adjustScoreInline'])->name('attendance.adjustInline');

    // Route::get('assignments/{teachingAssignment}/attendance',[AttendanceController::class, 'create'])->name('teacher.attendance.create');
    // Route::post('assignments/{teachingAssignment}/attendance',[AttendanceController::class, 'store'])->name('teacher.attendance.store');
    // Route::get('assignments/{teachingAssignment}/attendance/edit',[AttendanceController::class, 'edit'])->name('teacher.attendance.edit');

    Route::get('sessions/{academicSession}/attendance',[AttendanceController::class, 'create'])->name('attendance.take');
    Route::post('sessions/{academicSession}/attendance',[AttendanceController::class, 'store'])->name('attendance.store');
    Route::get('sessions/{academicSession}/attendance/edit',[AttendanceController::class, 'edit'])->name('attendance.edit');

    Route::get('sessions/{academicSession}/activity',[SessionActivityController::class, 'create'])->name('session.activities.create');
    Route::post('sessions/{academicSession}/activity',[SessionActivityController::class, 'store'])->name('session.activities.store');

    Route::post('/teams/assign', [TeamController::class, 'assign'])->name('teams.assign');
    Route::post('/teams', [TeamController::class, 'store'])->name('teams.store');

    
    // Route::get('assignments/{assignment}/teams', [TeamController::class, 'index'])->name('teams.index');
    // Route::get('assignments/{assignment}/teams/create', [TeamController::class, 'create'])->name('teams.create');
    // Route::post('assignments/{assignment}/teams', [TeamController::class, 'store'])->name('teams.store');
    // Route::delete('teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');
    
    Route::get('assignments/{assignment}/activities',[ActivityController::class, 'index'])->name('activities.index');
    Route::get('assignments/{assignment}/activities/create',[ActivityController::class, 'create'])->name('activities.create');
    Route::post('assignments/{assignment}/activities',[ActivityController::class, 'store'])->name('activities.store');
    Route::get('/activities/{activity}',[ActivityController::class, 'show'])->name('activities.show');
    Route::post('assignments/{assignment}/activities/inline-update',[ActivityController::class, 'inlineUpdate'])->name('activities.inline-update');
    Route::get('assignments/{assignment}/activities/period/{period}',[ActivityController::class, 'sessionsByPeriod'] )->name('activities.sessions');
    Route::patch('/grades/{grade}', [GradeController::class, 'updateInline'])->name('grades.inline-update');
    Route::get('assignments/{assignment}/grades/massive',[GradeController::class, 'massive'])->name('grades.massive');

    Route::get('/activities/{activity}/grade', [ActivityGradingController::class, 'show'])->name('activities.grade');
    Route::post('/activities/{activity}/grade', [ActivityGradingController::class, 'store'])->name('activities.grade.store');

    Route::get('/boletas/{teachingAssignment}/{student}',[\App\Http\Controllers\BoletaController::class, 'show'])->name('boletas.show');
    Route::get('/boletas/{teachingAssignment}/{student}/pdf',[\App\Http\Controllers\BoletaController::class, 'pdf'])->name('boletas.pdf');
    Route::get('/actas/{teachingAssignment}/calificaciones',[\App\Http\Controllers\ActaController::class, 'calificaciones'])->name('actas.calificaciones');

    Route::get('assignments/{assignment}/practices',[PracticeController::class, 'index'])->name('practices.index');
    Route::get('assignments/{assignment}/practices/create',[PracticeController::class, 'create'])->name('practices.create');
    Route::post('assignments/{assignment}/practices',[PracticeController::class, 'store'])->name('practices.store');
    Route::get('practices/{practice}/edit',[PracticeController::class, 'edit'])->name('practices.edit');
    Route::put('practices/{practice}',[PracticeController::class, 'update'])->name('practices.update');
    Route::delete('practices/{practice}',[PracticeController::class, 'destroy'])->name('practices.destroy');

    Route::get('activities/{activity}/grades',[GradeController::class, 'index'])->name('grades.index');
    Route::post('activities/{activity}/grades',[GradeController::class, 'store'])->name('grades.store');

    Route::get('teacher/classes/{assignment}/configuration/evaluation',[EvaluationCriterionController::class, 'index'])->name('teacher.classes.evaluation.index');
    Route::post('teacher/classes/{assignment}/configuration/evaluation',[EvaluationCriterionController::class, 'store'])->name('teacher.classes.evaluation.store');
    Route::put('teacher/classes/{assignment}/configuration/evaluation',[EvaluationCriterionController::class, 'update'])->name('teacher.classes.evaluation.update');
    Route::delete('teacher/classes/{assignment}/configuration/evaluation/{criterion}',[EvaluationCriterionController::class, 'destroy'])->name('teacher.classes.evaluation.destroy');

    Route::post('assignments/{teachingAssignment}/clone-evaluation',[EvaluationSchemeCloneController::class, 'clone'])->name('teacher.evaluation.clone');
    Route::post('assignments/{assignment}/activities/clone', [ActivityCloneController::class, 'clone'])->name('activities.clone');

    Route::get('teacher/justifications',[TeacherJustificationController::class, 'index'])->name('teacher.justifications.index');

    Route::get('performance',[TeacherPerformanceController::class, 'index'] )->name('teacher.performance.index');
    Route::get('performance/{assignment}',[TeacherPerformanceController::class, 'show'] )->name('teacher.performance.show');
    Route::get('performance/{assignment}/student/{student}', [TeacherPerformanceDetailController::class, 'show'])->name('performance.detail');

    Route::get('teacher/follow-ups', [TeacherFollowUpController::class, 'index'])->name('teacher.follow-ups.index');
    Route::get('teacher/follow-ups/{followUpTeacher}', [TeacherFollowUpController::class, 'show'])->name('teacher.follow-ups.show');
    Route::post('teacher/follow-ups/{followUpTeacher}/respond', [TeacherFollowUpController::class, 'respond'])->name('teacher.follow-ups.respond');
    
    // Route::get('follow-ups', [TeacherFollowUpController::class, 'index'])->name('teacher.follow-ups.index');
    // Route::get('follow-ups/{assignment}', [TeacherFollowUpController::class, 'show'])->name('teacher.follow-ups.show');
    // Route::post('follow-ups/{assignment}', [TeacherFollowUpController::class, 'store'])->name('teacher.follow-ups.store');
    Route::post('/notifications/{notification}/read', function (\Illuminate\Notifications\DatabaseNotification $notification) {
        abort_if(
            $notification->notifiable_id !== auth()->id(),
            403
        );    
        $notification->markAsRead();
        return back();})->name('notifications.read');
});

Route::middleware(['auth','role:student'])->group(function () {
    Route::get('student/grades', [GradeController::class, 'myGrades'])->name('student.grades');

    Route::get('student/practices', [StudentPracticeController::class, 'index'])->name('student.practices.index');
    Route::get('student/practices/{practice}', [StudentPracticeController::class, 'show'])->name('student.practices.show');
    Route::post('student/practices/{practice}', [StudentPracticeController::class, 'store'])->name('student.practices.store');
});

Route::middleware(['auth', 'role:admin|coordination'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('announcements', AnnouncementController::class);
});

Route::middleware(['auth', 'role:admin|coordination'])->prefix('coordination/students/{student}')->name('coordination.students.')->group(function () {
    Route::get('general', [CoordinationStudentController::class, 'general'])->name('general');
    Route::get('attendance', [CoordinationStudentController::class, 'attendance'])->name('attendance');
    Route::get('grades', [CoordinationStudentController::class, 'grades'])->name('grades');
    Route::get('followups', [CoordinationStudentController::class, 'followups'])->name('followups');
    
    Route::get('attendance-history', [CoordinationStudentController::class, 'attendanceHistory'])->name('attendance.history');
    Route::get('grades-history',[CoordinationStudentController::class, 'gradesHistory'])->name('grades.history');
});

Route::get('alerts/attendance/{modality}',[AdminAttendanceAlertController::class, 'fullDayAbsences'])->name('admin.alerts.attendance');
Route::get('alerts/partial-attendance/{modality}',[AdminAttendanceAlertController::class, 'partialAttendance'])->name('admin.alerts.partial');
Route::get('alerts/critical-subjects/{modality}',[AdminAcademicAlertController::class, 'criticalSubjects'])->name('admin.alerts.academic');

Route::get('coordination/students/{student}/report-card',[StudentReportCardController::class, 'show'])->name('coordination.students.report-card');
Route::get('coordination/students/{student}/report-card/pdf',[StudentReportCardController::class, 'pdf'])->name('coordination.students.report-card.pdf');