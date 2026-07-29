<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Services\NotificationCenterService;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\ModalityController;
use App\Http\Controllers\LevelController;
use App\Http\Controllers\CampusController;
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
use App\Http\Controllers\PracticeController;
use App\Http\Controllers\PracticeSubmissionAttachmentController;
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
use App\Http\Controllers\TeacherEvaluationController;
use App\Http\Controllers\TeacherStudentController;
use App\Http\Controllers\TeacherJustificationController;
use App\Http\Controllers\CoordinationAttendanceRiskController;
use App\Http\Controllers\AcademicResolutionController;
use App\Http\Controllers\EvaluationCriteriaController;
use App\Http\Controllers\SchoolCycleController;
use App\Http\Controllers\CyclePartialController;
use App\Http\Controllers\CoordinationScheduleController;
use App\Http\Controllers\CoordinationClassSkipController;
use App\Http\Controllers\TeacherStudentReportController;
use App\Http\Controllers\CoordinationReportController;
use App\Http\Controllers\StudentPortalController;
use App\Http\Controllers\StudentIncidentReportController;
use App\Http\Controllers\PrefectGroupAttendanceController;
use App\Http\Controllers\PrefectIncidentReportController;
use App\Http\Controllers\TutorPortalController;
use App\Http\Controllers\TutorAssignmentController;
use App\Http\Controllers\TutorController;
use App\Http\Controllers\TeacherKardexController;
use App\Http\Controllers\CoordinationStudentSuspensionController;
use App\Http\Controllers\CoordinationCyclePromotionController;
use App\Http\Controllers\CoordinationCyclePlanningController;
use App\Http\Controllers\CoordinationCycleSubjectController;
use App\Http\Controllers\CoordinationEconomicActaController;
use App\Http\Controllers\CoordinationTeacherAttendanceController;
use App\Http\Controllers\TemarioController;
use App\Http\Controllers\TeacherDidacticPlanController;
use App\Http\Controllers\TeacherPlanningDocumentController;
use App\Http\Controllers\EconomicActaReopenRequestController;
use App\Http\Controllers\ActiveCampusController;
use App\Http\Controllers\ActiveSchoolCycleController;
use App\Http\Controllers\TeacherCampusAttendanceController;
use App\Http\Controllers\CoordinationTeacherDocumentRequestController;
use App\Http\Controllers\TeacherDocumentRequestController;
use App\Http\Controllers\TeacherQuestionBankController;
use App\Http\Controllers\CoordinationPaperExamController;
use App\Http\Controllers\StudentOnlineExamController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\TeacherPaperExamController;
use App\Http\Controllers\CoordinationQualityController;
use App\Http\Controllers\CoordinationSchoolCaseController;
use App\Http\Controllers\CoordinationTeacherPerformanceController;
use App\Http\Controllers\Finance\FinanceDashboardController;
use App\Http\Controllers\Finance\FinanceConceptController;
use App\Http\Controllers\Finance\FinanceChargeController;
use App\Http\Controllers\Finance\FinanceStudentStatementController;
use App\Http\Controllers\Auth\TemporaryPasswordController;
use App\Http\Controllers\InventoryController;

require __DIR__.'/imports.php';

Route::get('/', function () {
    $announcements = \App\Models\Announcement::query()
        ->with('images')
        ->where('scope', 'public')
        ->where('is_active', true)
        ->where(function ($query) {
            $query->whereNull('published_at')
                ->orWhere('published_at', '<=', now());
        })
        ->orderByDesc('published_at')
        ->orderByDesc('created_at')
        ->take(12)
        ->get();

    return view('welcome', compact('announcements'));
});

Auth::routes();

Route::middleware(['auth'])->group(function () {
    Route::get('/password/temporary', [TemporaryPasswordController::class, 'edit'])
        ->name('temporary-password.edit');
    Route::put('/password/temporary', [TemporaryPasswordController::class, 'update'])
        ->name('temporary-password.update');
});

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Route::middleware(['auth'])->get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::middleware(['auth'])->post('/notifications/{notification}/read', function (
    \Illuminate\Notifications\DatabaseNotification $notification,
    Request $request,
    NotificationCenterService $notifications
) {
    abort_if($notification->notifiable_id !== auth()->id(), 403);

    $notification->markAsRead();
    $notifications->forget(auth()->id());
    $navbar = $notifications->navbar(auth()->user());

    if ($request->expectsJson()) {
        return response()->json([
            'ok' => true,
            'unread_count' => $navbar['unread_count'],
            'items' => $navbar['items'],
        ]);
    }

    $redirect = $request->input('redirect_to');
    if ($redirect) {
        return redirect()->to($redirect);
    }

    return back();
})->name('notifications.read');

Route::middleware(['auth'])->get('/notifications/summary', function (NotificationCenterService $notifications) {
    return response()->json($notifications->navbar(auth()->user()));
})->name('notifications.summary');

Route::middleware(['auth', 'campus.access'])->get(
    'practice-submission-attachments/{attachment}/download',
    [PracticeSubmissionAttachmentController::class, 'download']
)->name('practice-submission-attachments.download');
Route::middleware(['auth'])->post('/active-campus', [ActiveCampusController::class, 'update'])->name('active-campus.update');
Route::middleware(['auth'])->post('/active-school-cycle', [ActiveSchoolCycleController::class, 'update'])->name('active-school-cycle.update');
Route::middleware(['auth', 'role:coordinator|teacher|student|prefect|guardian|tutor|admin', 'campus.access'])
    ->prefix('chat')
    ->name('chat.')
    ->group(function () {
        Route::get('/', [ChatController::class, 'index'])->name('index');
        Route::get('/bootstrap', [ChatController::class, 'bootstrap'])->name('bootstrap');
        Route::post('/direct', [ChatController::class, 'storeDirectConversation'])->name('direct.store');
        Route::post('/group', [ChatController::class, 'storeGroupConversation'])->name('group.store');
        Route::get('/unread-count', [ChatController::class, 'unreadCount'])->name('unread-count');
        Route::post('/conversations/{conversation}/messages', [ChatController::class, 'storeMessage'])->name('messages.store');
        Route::get('/conversations/{conversation}/messages', [ChatController::class, 'fetchMessages'])->name('messages.fetch');
    });

Route::middleware(['auth'])->get('students/{student}/report-card', [ReportCardController::class, 'show'])->name('students.report-card');
Route::get('students/{student}/report-card/pdf',[ReportCardController::class, 'pdf'])->name('students.report-card.pdf');

Route::middleware(['auth', 'role:admin'])->group(function() {
    Route::resource('users', UserController::class);
    Route::post('/users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
    Route::post('/users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
});

Route::middleware(['auth', 'role:coordinator|admin', 'campus.access'])->group(function() {
    Route::resource('campuses', CampusController::class);
    Route::post('/campuses/{campus}/activate', [CampusController::class, 'activate'])->name('campuses.activate');
    Route::post('/campuses/{campus}/deactivate', [CampusController::class, 'deactivate'])->name('campuses.deactivate');

    Route::resource('modalities', ModalityController::class);
    Route::post('/modalities/{modality}/activate', [ModalityController::class, 'activate'])->name('modalities.activate');
    Route::post('/modalities/{modality}/deactivate', [ModalityController::class, 'deactivate'])->name('modalities.deactivate');

    Route::resource('levels', LevelController::class);
    Route::post('/levels/{level}/activate', [LevelController::class, 'activate'])->name('levels.activate');
    Route::post('/levels/{level}/deactivate', [LevelController::class, 'deactivate'])->name('levels.deactivate');
});

Route::middleware(['auth', 'role:coordinator|admin', 'tenant.domain', 'tenant.prevent-central', 'campus.access'])->prefix('coordination')->name('coordination.')->group(function () {
        Route::get('groups', [CoordinationScheduleController::class, 'groupsCalendar'])->name('schedules.groups-calendar');
        Route::resource('suspensions', CoordinationStudentSuspensionController::class)->except(['show']);
        Route::get('cycle-planning', [CoordinationCyclePlanningController::class, 'index'])->name('cycle-planning.index');
        Route::get('cycle-subjects', [CoordinationCycleSubjectController::class, 'index'])->name('cycle-subjects.index');
        Route::post('cycle-planning/groups', [CoordinationCyclePlanningController::class, 'storeGroup'])->name('cycle-planning.groups.store');
        Route::post('cycle-planning/groups/new', [CoordinationCyclePlanningController::class, 'storeNewGroup'])->name('cycle-planning.groups.new');
        Route::put('cycle-planning/groups/{cycleGroup}', [CoordinationCyclePlanningController::class, 'updateGroup'])->name('cycle-planning.groups.update');
        Route::put('cycle-planning/groups/{cycleGroup}/subjects', [CoordinationCyclePlanningController::class, 'updateSubjects'])->name('cycle-planning.groups.subjects.update');
        Route::post('cycle-planning/groups/{cycleGroup}/deactivate', [CoordinationCyclePlanningController::class, 'deactivateGroup'])->name('cycle-planning.groups.deactivate');
        Route::get('cycle-promotions', [CoordinationCyclePromotionController::class, 'index'])->name('cycle-promotions.index');
        Route::post('cycle-promotions/preview', [CoordinationCyclePromotionController::class, 'preview'])->name('cycle-promotions.preview');
        Route::post('cycle-promotions/execute', [CoordinationCyclePromotionController::class, 'execute'])->name('cycle-promotions.execute');
        Route::get('students/active-cycle', [CoordinationStudentController::class, 'activeCycleRoster'])->name('students.active-cycle');
        Route::get('students/academic-summary', [CoordinationStudentController::class, 'academicSummary'])->name('students.academic-summary');
        Route::get('students/{student}/subjects/{assignment}', [CoordinationStudentController::class, 'subjectDetail'])->name('students.subject-detail');
        Route::get('students/{student}/prefect-attendance-detail', [CoordinationStudentController::class, 'prefectAttendanceDetail'])->name('students.prefect-attendance-detail');
        Route::get('students/attendances-risk', [CoordinationAttendanceRiskController::class, 'index'])->name('students.attendances-risk');
        Route::get('students/class-skips', [CoordinationClassSkipController::class, 'index'])->name('students.class-skips');
        Route::get('economic-actas', [CoordinationEconomicActaController::class, 'index'])->name('economic-actas.index');
        Route::post('economic-actas/{partial}/initialize', [CoordinationEconomicActaController::class, 'initialize'])->name('economic-actas.initialize');
        Route::post('economic-actas/{partial}/remind-pending', [CoordinationEconomicActaController::class, 'remindPending'])->name('economic-actas.remind-pending');
        Route::post('economic-actas/{partial}/{assignment}/draft', [CoordinationEconomicActaController::class, 'draft'])->name('economic-actas.draft');
        Route::post('economic-actas/{partial}/{assignment}/close', [CoordinationEconomicActaController::class, 'close'])->name('economic-actas.close');
        Route::post('economic-actas/{partial}/{assignment}/send', [CoordinationEconomicActaController::class, 'send'])->name('economic-actas.send');
        Route::get('economic-actas/{acta}/pdf', [CoordinationEconomicActaController::class, 'pdf'])->name('economic-actas.pdf');
        Route::post('economic-acta-reopen-requests/{reopenRequest}/approve', [EconomicActaReopenRequestController::class, 'approve'])->name('economic-acta-reopen-requests.approve');
        Route::post('economic-acta-reopen-requests/{reopenRequest}/reject', [EconomicActaReopenRequestController::class, 'reject'])->name('economic-acta-reopen-requests.reject');
        Route::get('follow-ups', [StudentFollowUpController::class, 'index'])->name('follow-ups.index');
        Route::get('follow-ups/critical', [StudentFollowUpController::class, 'critical'])->name('follow-ups.critical');
        Route::get('follow-ups/create', [StudentFollowUpController::class, 'create'])->name('follow-ups.create');
        Route::post('follow-ups', [StudentFollowUpController::class, 'store'])->name('follow-ups.store');
        Route::get('follow-ups/{followUp}/pdf', [StudentFollowUpController::class, 'pdf'])->name('follow-ups.pdf');
        Route::get('follow-ups/{followUp}', [StudentFollowUpController::class, 'show'])->name('follow-ups.show');
        Route::get('follow-ups/responses/{assignment}',[StudentFollowUpResponseController::class, 'show'])->name('follow-ups.responses.show');
        Route::get('reports', [TeacherStudentReportController::class, 'coordinationIndex'])->name('reports.index');
        Route::get('reports/create', [CoordinationReportController::class, 'create'])->name('reports.create');
        Route::post('reports', [CoordinationReportController::class, 'store'])->name('reports.store');
        Route::patch('reports/{report}/review', [TeacherStudentReportController::class, 'markReviewed'])->name('reports.review');
        Route::patch('coordination-reports/{report}/status', [CoordinationReportController::class, 'updateStatus'])->name('coordination-reports.update-status');
        Route::get('student-incident-reports', [StudentIncidentReportController::class, 'coordinationIndex'])->name('student-incident-reports.index');
        Route::patch('student-incident-reports/{incidentReport}/status', [StudentIncidentReportController::class, 'updateStatus'])->name('student-incident-reports.update-status');
        Route::get('prefect-reports', [PrefectIncidentReportController::class, 'coordinationIndex'])->name('prefect-reports.index');
        Route::patch('prefect-reports/{report}/status', [PrefectIncidentReportController::class, 'updateStatus'])->name('prefect-reports.update-status');
        Route::get('school-cases', [CoordinationSchoolCaseController::class, 'index'])->name('school-cases.index');
        Route::get('school-cases/create', [CoordinationSchoolCaseController::class, 'create'])->name('school-cases.create');
        Route::post('school-cases', [CoordinationSchoolCaseController::class, 'store'])->name('school-cases.store');
        Route::get('school-cases/{schoolCase}', [CoordinationSchoolCaseController::class, 'show'])->name('school-cases.show');
        Route::patch('school-cases/{schoolCase}/status', [CoordinationSchoolCaseController::class, 'updateStatus'])->name('school-cases.status');
        Route::post('school-cases/{schoolCase}/entries', [CoordinationSchoolCaseController::class, 'storeEntry'])->name('school-cases.entries.store');
        Route::post('school-cases/{schoolCase}/actions', [CoordinationSchoolCaseController::class, 'storeAction'])->name('school-cases.actions.store');
        Route::patch('school-case-actions/{action}/complete', [CoordinationSchoolCaseController::class, 'completeAction'])->name('school-cases.actions.complete');
        Route::resource('students', CoordinationStudentController::class)->only(['index', 'show']);
        Route::get('paper-exams', [CoordinationPaperExamController::class, 'index'])->name('paper-exams.index');
        Route::get('paper-exams/create', [CoordinationPaperExamController::class, 'create'])->name('paper-exams.create');
        Route::post('paper-exams', [CoordinationPaperExamController::class, 'store'])->name('paper-exams.store');
        Route::get('paper-exams/schedule', [CoordinationPaperExamController::class, 'schedule'])->name('paper-exams.schedule');
        Route::put('paper-exams/schedule', [CoordinationPaperExamController::class, 'updateSchedule'])->name('paper-exams.schedule.update');
        Route::get('paper-exams/{paperExam}', [CoordinationPaperExamController::class, 'show'])->name('paper-exams.show');
        Route::get('paper-exams/{paperExam}/pdf', [CoordinationPaperExamController::class, 'pdf'])->name('paper-exams.pdf');
        Route::put('paper-exams/{paperExam}/online', [CoordinationPaperExamController::class, 'updateOnline'])->name('paper-exams.online.update');
        Route::get('teacher-documents', [CoordinationTeacherDocumentRequestController::class, 'index'])->name('teacher-documents.index');
        Route::get('teacher-documents/tracking', [CoordinationTeacherDocumentRequestController::class, 'tracking'])->name('teacher-documents.tracking');
        Route::get('teacher-documents/create', [CoordinationTeacherDocumentRequestController::class, 'create'])->name('teacher-documents.create');
        Route::post('teacher-documents', [CoordinationTeacherDocumentRequestController::class, 'store'])->name('teacher-documents.store');
        Route::get('teacher-documents/teachers/{teacher}', [CoordinationTeacherDocumentRequestController::class, 'showTeacher'])->name('teacher-documents.teachers.show');
        Route::get('teacher-documents/items/{item}/pdf', [CoordinationTeacherDocumentRequestController::class, 'documentPdf'])->name('teacher-documents.items.pdf');
        Route::get('teacher-documents/plans/{plan}/pdf', [TeacherDidacticPlanController::class, 'pdf'])->name('teacher-documents.plans.pdf');
        Route::patch('teacher-documents/items/{item}/visibility', [CoordinationTeacherDocumentRequestController::class, 'updateItemVisibility'])->name('teacher-documents.items.visibility');
        Route::get('teacher-performance', [CoordinationTeacherPerformanceController::class, 'index'])->name('teacher-performance.index');
        Route::get('teacher-performance/{teacher}', [CoordinationTeacherPerformanceController::class, 'show'])->name('teacher-performance.show');
        Route::get('quality', [CoordinationQualityController::class, 'index'])->name('quality.index');
        Route::post('quality/bootstrap-iso-base', [CoordinationQualityController::class, 'bootstrapIsoBase'])->name('quality.bootstrap-iso-base');
        Route::get('quality/processes/create', [CoordinationQualityController::class, 'createProcess'])->name('quality.processes.create');
        Route::post('quality/processes', [CoordinationQualityController::class, 'storeProcess'])->name('quality.processes.store');
        Route::get('quality/processes/{process}/edit', [CoordinationQualityController::class, 'editProcess'])->name('quality.processes.edit');
        Route::put('quality/processes/{process}', [CoordinationQualityController::class, 'updateProcess'])->name('quality.processes.update');
        Route::delete('quality/processes/{process}', [CoordinationQualityController::class, 'destroyProcess'])->name('quality.processes.destroy');
        Route::get('quality/documents/create', [CoordinationQualityController::class, 'createDocument'])->name('quality.documents.create');
        Route::post('quality/documents', [CoordinationQualityController::class, 'storeDocument'])->name('quality.documents.store');
        Route::get('quality/documents/{document}/edit', [CoordinationQualityController::class, 'editDocument'])->name('quality.documents.edit');
        Route::put('quality/documents/{document}', [CoordinationQualityController::class, 'updateDocument'])->name('quality.documents.update');
        Route::delete('quality/documents/{document}', [CoordinationQualityController::class, 'destroyDocument'])->name('quality.documents.destroy');
        Route::get('quality/documents/{document}', [CoordinationQualityController::class, 'showDocument'])->name('quality.documents.show');
        Route::post('quality/documents/{document}/submit', [CoordinationQualityController::class, 'submitForApproval'])->name('quality.documents.submit');
        Route::post('quality/documents/{document}/approve', [CoordinationQualityController::class, 'approveDocument'])->name('quality.documents.approve');
        Route::post('quality/documents/{document}/reject', [CoordinationQualityController::class, 'rejectDocument'])->name('quality.documents.reject');
    });

Route::middleware(['auth', 'role:coordinator|admin', 'campus.access'])->group(function () {
    Route::get('groups/{group}/students',[GroupStudentController::class, 'edit'])->name('groups.students.edit');
    Route::post('groups/{group}/students',[GroupStudentController::class, 'update'])->name('groups.students.update');

    Route::get('academic-periods', [AcademicPeriodController::class, 'index'])->name('academic-periods.index');
    Route::resource('academic-periods', AcademicPeriodController::class);
    Route::post('/academic-periods/{period}/activate', [AcademicPeriodController::class, 'activate'])->name('academic-periods.activate');
    Route::post('/academic-periods/{period}/deactivate', [AcademicPeriodController::class, 'deactivate'])->name('academic-periods.deactivate');

    Route::resource('students', StudentController::class);
    Route::post('students/bulk-store', [StudentController::class, 'bulkStore'])->name('students.bulk-store');
    Route::resource('tutors', TutorController::class)->except(['show', 'destroy']);
    Route::get('coordination/tutor-assignments', [TutorAssignmentController::class, 'index'])->name('coordination.tutor-assignments.index');
    Route::post('coordination/tutor-assignments/bulk', [TutorAssignmentController::class, 'bulkAssign'])->name('coordination.tutor-assignments.bulk');
    Route::patch('coordination/tutor-assignments/{student}', [TutorAssignmentController::class, 'update'])->name('coordination.tutor-assignments.update');
    Route::resource('academic-calendar-days', AcademicCalendarDayController::class);
    Route::resource('school-cycles', SchoolCycleController::class);
    Route::get('school-cycles/{schoolCycle}/partials', [CyclePartialController::class, 'index'])->name('school-cycles.partials.index');
    Route::get('school-cycles/{schoolCycle}/partials/create', [CyclePartialController::class, 'create'])->name('school-cycles.partials.create');
    Route::post('school-cycles/{schoolCycle}/partials', [CyclePartialController::class, 'store'])->name('school-cycles.partials.store');
    Route::get('school-cycles/{schoolCycle}/partials/{partial}/edit', [CyclePartialController::class, 'edit'])->name('school-cycles.partials.edit');
    Route::put('school-cycles/{schoolCycle}/partials/{partial}', [CyclePartialController::class, 'update'])->name('school-cycles.partials.update');
    Route::delete('school-cycles/{schoolCycle}/partials/{partial}', [CyclePartialController::class, 'destroy'])->name('school-cycles.partials.destroy');
    Route::post('school-cycles/{schoolCycle}/partials/{partial}/activate', [CyclePartialController::class, 'activate'])->name('school-cycles.partials.activate');

    Route::get('groups/{group}/assignments/{assignment}/weights',[CourseWeightController::class, 'edit'])->name('weights.edit');
    Route::post('groups/{group}/assignments/{assignment}/weights',[CourseWeightController::class, 'update'])->name('weights.update');

    Route::middleware(['tenant.domain', 'tenant.prevent-central'])->group(function () {
        Route::get('groups/{group}/assignments/{assignment}/schedules',[ScheduleController::class, 'index'])->name('schedules.index');
        Route::post('groups/{group}/assignments/{assignment}/schedules',[ScheduleController::class, 'store'])->name('schedules.store');
        Route::post('schedules/{schedule}/deactivate',[ScheduleController::class, 'deactivate'])->name('schedules.deactivate');
        Route::resource('coordination/schedules', CoordinationScheduleController::class)
            ->names('coordination.schedules')
            ->except(['show']);
    });

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

    Route::middleware(['tenant.domain', 'tenant.prevent-central'])->group(function () {
        Route::get('groups/{group}/assignments', [TeachingAssignmentController::class, 'edit'])->name('groups.assignments.edit');
        Route::post('groups/{group}/assignments', [TeachingAssignmentController::class, 'update'])->name('groups.assignments.update');
        Route::get('groups/{group}/assignments/sections/{subject}', [TeachingAssignmentController::class, 'editSections'])->name('groups.assignments.sections.edit');
        Route::put('groups/{group}/assignments/sections/{subject}', [TeachingAssignmentController::class, 'updateSections'])->name('groups.assignments.sections.update');
    });

    Route::resource('groups', GroupController::class);
    Route::post('groups/{group}/deactivate', [GroupController::class, 'deactivate'])->name('groups.deactivate');
    Route::post('groups/{group}/activate', [GroupController::class, 'activate'])->name('groups.activate');

    Route::resource('subjects', SubjectController::class);
    Route::post('subjects/{subject}/deactivate',[SubjectController::class, 'deactivate'])->name('subjects.deactivate');
    Route::post('subjects/{subject}/activate',[SubjectController::class, 'activate'])->name('subjects.activate');
    Route::get('coordination/temarios', [TemarioController::class, 'teacherIndex'])->name('coordination.temarios.index');
    Route::get('subjects/{subject}/temarios', [TemarioController::class, 'index'])->name('temarios.index');
    Route::get('subjects/{subject}/temarios/create', [TemarioController::class, 'create'])->name('temarios.create');
    Route::post('subjects/{subject}/temarios', [TemarioController::class, 'store'])->name('temarios.store');
    Route::get('subjects/{subject}/temarios/import', [TemarioController::class, 'importForm'])->name('temarios.import.form');
    Route::post('subjects/{subject}/temarios/import', [TemarioController::class, 'import'])->name('temarios.import.store');
    Route::get('temarios/template/download', [TemarioController::class, 'downloadTemplate'])->name('temarios.template.download');
    Route::get('temarios/{temario}/edit', [TemarioController::class, 'edit'])->name('temarios.edit');
    Route::put('temarios/{temario}', [TemarioController::class, 'update'])->name('temarios.update');
    Route::delete('temarios/{temario}', [TemarioController::class, 'destroy'])->name('temarios.destroy');

    Route::get('/attendance-justifications', [AttendanceJustificationController::class, 'index'])->name('attendance_justifications.index');
    Route::get('/attendance-justifications/create', [AttendanceJustificationController::class, 'create'])->name('attendance_justifications.create');
    Route::post('/attendance-justifications', [AttendanceJustificationController::class, 'store'])->name('attendance_justifications.store');

    Route::prefix('coordination/finance')->name('coordination.finance.')->group(function () {
        Route::get('/', [FinanceDashboardController::class, 'index'])->name('dashboard');
        Route::resource('concepts', FinanceConceptController::class)->except(['show', 'destroy']);
        Route::resource('charges', FinanceChargeController::class)->only(['index', 'create', 'store']);
        Route::get('charges/massive/create', [FinanceChargeController::class, 'createMassive'])->name('charges.massive.create');
        Route::post('charges/massive', [FinanceChargeController::class, 'storeMassive'])->name('charges.massive.store');
        Route::get('statements', [FinanceStudentStatementController::class, 'index'])->name('statements.index');
        Route::get('statements/{student}', [FinanceStudentStatementController::class, 'show'])->name('statements.show');
        Route::post('statements/{student}/payments', [FinanceStudentStatementController::class, 'storePayment'])->name('statements.payments.store');
    });

    Route::prefix('coordination/inventory')->name('coordination.inventory.')->group(function () {
        Route::get('/', [InventoryController::class, 'index'])->name('index');
        Route::get('/create', [InventoryController::class, 'create'])->name('create');
        Route::post('/', [InventoryController::class, 'store'])->name('store');
        Route::post('/locations', [InventoryController::class, 'storeLocation'])->name('locations.store');
        Route::get('/{item}', [InventoryController::class, 'show'])->name('show');
        Route::get('/{item}/edit', [InventoryController::class, 'edit'])->name('edit');
        Route::put('/{item}', [InventoryController::class, 'update'])->name('update');
        Route::post('/{item}/movements', [InventoryController::class, 'storeMovement'])->name('movements.store');
    });
});

Route::middleware(['auth', 'role:teacher', 'campus.access'])->group(function () {
    Route::get('teacher/question-banks', [TeacherQuestionBankController::class, 'index'])->name('teacher.question-banks.index');
    Route::get('teacher/question-banks/create', [TeacherQuestionBankController::class, 'create'])->name('teacher.question-banks.create');
    Route::post('teacher/question-banks', [TeacherQuestionBankController::class, 'store'])->name('teacher.question-banks.store');
    Route::get('teacher/question-banks/template/download', [TeacherQuestionBankController::class, 'downloadTemplate'])->name('teacher.question-banks.template.download');
    Route::get('teacher/question-banks/{questionBank}/template/download', [TeacherQuestionBankController::class, 'downloadTemplate'])->name('teacher.question-banks.template.download-for-bank');
    Route::get('teacher/question-banks/{questionBank}/configure-exam', [TeacherQuestionBankController::class, 'configureExam'])->name('teacher.question-banks.exam.configure');
    Route::put('teacher/question-banks/{questionBank}/configure-exam', [TeacherQuestionBankController::class, 'updateExamConfiguration'])->name('teacher.question-banks.exam.update');
    Route::get('teacher/question-banks/{questionBank}/edit', [TeacherQuestionBankController::class, 'edit'])->name('teacher.question-banks.edit');
    Route::put('teacher/question-banks/{questionBank}', [TeacherQuestionBankController::class, 'update'])->name('teacher.question-banks.update');
    Route::delete('teacher/question-banks/{questionBank}', [TeacherQuestionBankController::class, 'destroy'])->name('teacher.question-banks.destroy');
    Route::get('teacher/question-banks/{questionBank}', [TeacherQuestionBankController::class, 'show'])->name('teacher.question-banks.show');
    Route::post('teacher/question-banks/{questionBank}/import', [TeacherQuestionBankController::class, 'importQuestions'])->name('teacher.question-banks.import');
    Route::post('teacher/question-banks/{questionBank}/questions', [TeacherQuestionBankController::class, 'storeQuestion'])->name('teacher.question-banks.questions.store');
    Route::get('teacher/question-banks/{questionBank}/questions/{question}/edit', [TeacherQuestionBankController::class, 'editQuestion'])->name('teacher.question-banks.questions.edit');
    Route::get('teacher/question-banks/{questionBank}/questions/{question}/preview', [TeacherQuestionBankController::class, 'previewQuestion'])->name('teacher.question-banks.questions.preview');
    Route::put('teacher/question-banks/{questionBank}/questions/{question}', [TeacherQuestionBankController::class, 'updateQuestion'])->name('teacher.question-banks.questions.update');
    Route::delete('teacher/question-banks/{questionBank}/questions/{question}', [TeacherQuestionBankController::class, 'destroyQuestion'])->name('teacher.question-banks.questions.destroy');
    Route::get('teacher/paper-exams', [TeacherPaperExamController::class, 'index'])->name('teacher.paper-exams.index');
    Route::get('teacher/paper-exams/{paperExam}/questions', [TeacherPaperExamController::class, 'editQuestions'])->name('teacher.paper-exams.questions.edit');
    Route::put('teacher/paper-exams/{paperExam}/questions', [TeacherPaperExamController::class, 'updateQuestions'])->name('teacher.paper-exams.questions.update');
    Route::get('teacher/paper-exams/{paperExam}/preview', [TeacherPaperExamController::class, 'previewAsStudent'])->name('teacher.paper-exams.preview');
    Route::post('teacher/paper-exams/{paperExam}/preview', [TeacherPaperExamController::class, 'submitPreview'])->name('teacher.paper-exams.preview.submit');
    Route::get('teacher/paper-exams/{paperExam}', [TeacherPaperExamController::class, 'show'])->name('teacher.paper-exams.show');
    Route::get('teacher/paper-exams/{paperExam}/attempts/{attempt}', [TeacherPaperExamController::class, 'reviewAttempt'])->name('teacher.paper-exams.attempts.review');
    Route::put('teacher/paper-exams/{paperExam}/attempts/{attempt}', [TeacherPaperExamController::class, 'gradeAttempt'])->name('teacher.paper-exams.attempts.grade');

    Route::get('teacher/attendance', [TeacherCampusAttendanceController::class, 'index'])->name('teacher.attendance.index');
    Route::post('teacher/attendance/clock', [TeacherCampusAttendanceController::class, 'clock'])->name('teacher.attendance.clock');
    Route::get('teacher/didactic-plans', [TeacherDidacticPlanController::class, 'index'])->name('teacher.didactic-plans.index');
    Route::get('teacher/didactic-plans/{assignment}/template', [TeacherDidacticPlanController::class, 'downloadTemplate'])->name('teacher.didactic-plans.template');
    Route::get('teacher/didactic-plans/{assignment}/import', [TeacherDidacticPlanController::class, 'importForm'])->name('teacher.didactic-plans.import');
    Route::post('teacher/didactic-plans/{assignment}/import', [TeacherDidacticPlanController::class, 'import'])->name('teacher.didactic-plans.import.store');
    Route::get('teacher/didactic-plans/{assignment}', [TeacherDidacticPlanController::class, 'plans'])->name('teacher.didactic-plans.plans');
    Route::get('teacher/didactic-plans/{assignment}/create', [TeacherDidacticPlanController::class, 'create'])->name('teacher.didactic-plans.create');
    Route::post('teacher/didactic-plans/{assignment}', [TeacherDidacticPlanController::class, 'store'])->name('teacher.didactic-plans.store');
    Route::post('teacher/didactic-plans/{assignment}/clone-from-peer', [TeacherDidacticPlanController::class, 'cloneFromPeer'])->name('teacher.didactic-plans.clone-from-peer');
    Route::post('teacher/didactic-plan/{plan}/clone-to-peer-groups', [TeacherDidacticPlanController::class, 'cloneToPeerGroups'])->name('teacher.didactic-plans.clone-to-peer-groups');
    Route::patch('teacher/didactic-plan/{plan}/confirm-final', [TeacherDidacticPlanController::class, 'confirmFinal'])->name('teacher.didactic-plans.confirm-final');
    Route::get('teacher/didactic-plan/{plan}/pdf', [TeacherDidacticPlanController::class, 'pdf'])->name('teacher.didactic-plans.pdf');
    Route::get('teacher/didactic-plan/{plan}/edit', [TeacherDidacticPlanController::class, 'edit'])->name('teacher.didactic-plans.edit');
    Route::put('teacher/didactic-plan/{plan}', [TeacherDidacticPlanController::class, 'update'])->name('teacher.didactic-plans.update');
    Route::delete('teacher/didactic-plan/{plan}', [TeacherDidacticPlanController::class, 'destroy'])->name('teacher.didactic-plans.destroy');
    Route::get('teacher/assignments', [TeachingAssignmentController::class, 'myAssignments'])->name('teacher.assignments');
    Route::post('assignments/{teachingAssignment}/economic-acta/submit', [TeachingAssignmentController::class, 'submitEconomicActa'])->name('teacher.economic-acta.submit');
    Route::post('economic-actas/{acta}/reopen-request', [EconomicActaReopenRequestController::class, 'store'])->name('teacher.economic-acta.reopen-request');
    Route::get('assignments/{teachingAssignment}/documents/programa-operativo', [TeacherPlanningDocumentController::class, 'programaOperativoPdf'])->name('teacher.documents.programa-operativo');
    Route::get('assignments/{teachingAssignment}/documents/planeacion-formato', [TeacherPlanningDocumentController::class, 'planeacionFormatoPdf'])->name('teacher.documents.planeacion-formato');
    Route::get('assignments/{teachingAssignment}/evaluation/create',[EvaluationCriterionController::class, 'create'])->name('teacher.evaluation.create');
    Route::get('assignments/{teachingAssignment}/evaluation/edit',[EvaluationCriterionController::class, 'edit'])->name('teacher.evaluation.edit');
    Route::post('assignments/{teachingAssignment}/evaluation',[EvaluationCriterionController::class, 'store'])->name('teacher.evaluation.store');
    Route::patch('assignments/{teachingAssignment}/evaluation',[EvaluationCriterionController::class, 'update'])->name('teacher.evaluation.update');
    Route::post(
        'assignments/{teachingAssignment}/students/{student}/remedial-exams',
        [TeachingAssignmentController::class, 'storeRemedialExam']
    )->name('assignments.remedial.store');
    Route::get('assignments/{teachingAssignment}', [TeachingAssignmentController::class, 'show'])->name('assignments.show');
    Route::get('/teacher/assignments/{teachingAssignment}/evaluation',[EvaluationCriteriaController::class, 'index'])->name('teacher.assignments.evaluation');

    Route::get('/teacher/classes', [TeacherClassController::class, 'index'])->name('teacher.classes.index');
    Route::get('/teacher/classes/calendar', [TeacherClassController::class, 'calendar'])->name('teacher.classes.calendar');
    Route::get('/teacher/classes/{teachingAssignment}', [TeacherClassController::class, 'show'])->name('teacher.classes.show');
    Route::get('/teacher/classes/{teachingAssignment}/sessions',[TeacherClassSessionController::class, 'index'])->name('teacher.classes.sessions.index');
    Route::get('/teacher/classes/{teachingAssignment}/kardex', [TeacherKardexController::class, 'download'])->name('teacher.classes.kardex');
    Route::get('/teacher/evaluation', [TeacherEvaluationController::class, 'index'])->name('teacher.evaluation.index');
    Route::get('/teacher/evaluation/{assignment}/manual/create', [TeacherEvaluationController::class, 'createManual'])->name('teacher.evaluation.manual.create');
    Route::post('/teacher/evaluation/{assignment}/manual', [TeacherEvaluationController::class, 'storeManual'])->name('teacher.evaluation.manual.store');
    Route::get('/teacher/evaluation/{assignment}', [TeacherEvaluationController::class, 'show'])->name('teacher.evaluation.activities');

    Route::get('/teacher/students', [TeacherStudentController::class, 'index'])->name('teacher.students.index');
    Route::get('/teacher/activities', [TeacherStudentController::class, 'index'])->name('teacher.activities.index');
    Route::get('/teacher/reports', [TeacherStudentReportController::class, 'index'])->name('teacher.reports.index');
    Route::get('/teacher/reports/create', [TeacherStudentReportController::class, 'create'])->name('teacher.reports.create');
    Route::post('/teacher/reports', [TeacherStudentReportController::class, 'store'])->name('teacher.reports.store');
    Route::get('/teacher/reports/{report}', [TeacherStudentReportController::class, 'show'])->name('teacher.reports.show');
    Route::get('/teacher/reports/{report}/edit', [TeacherStudentReportController::class, 'edit'])->name('teacher.reports.edit');
    Route::put('/teacher/reports/{report}', [TeacherStudentReportController::class, 'update'])->name('teacher.reports.update');
    Route::get('/teacher/students/group/{group}', [TeacherStudentController::class, 'group'])->name('teacher.students.group');
    Route::get('/teacher/students/{student}', [TeacherStudentController::class, 'show'])->name('teacher.students.show');

    Route::get('assignments/{assignment}/attendance/massive',[AttendanceController::class, 'massive'])->name('attendance.massive');
    Route::post('assignments/{assignment}/attendance/massive',[AttendanceController::class, 'storeMassive'])->name('attendance.massive.store');
    Route::post('/attendance/inline', [AttendanceController::class, 'storeInline'])->name('attendance.inline');
    Route::post('/attendance/adjust-inline', [AttendanceController::class, 'adjustScoreInline'])->name('attendance.adjustInline');

    Route::get('sessions/{academicSession}/attendance',[AttendanceController::class, 'create'])->name('attendance.take');
    Route::post('sessions/{academicSession}/attendance',[AttendanceController::class, 'store'])->name('attendance.store');
    Route::get('sessions/{academicSession}/attendance/edit',[AttendanceController::class, 'edit'])->name('attendance.edit');

    Route::get('sessions/{academicSession}/activity',[SessionActivityController::class, 'create'])->name('session.activities.create');
    Route::post('sessions/{academicSession}/activity',[SessionActivityController::class, 'store'])->name('session.activities.store');
    Route::get('assignments/{assignment}/activities/massive', [SessionActivityController::class, 'massive'])->name('session.activities.massive');
    Route::post('assignments/{assignment}/activities/massive', [SessionActivityController::class, 'storeMassive'])->name('session.activities.massive.store');

    Route::get('assignments/{assignment}/activities',[ActivityController::class, 'index'])->name('activities.index');
    Route::get('assignments/{assignment}/activities/create',[ActivityController::class, 'create'])->name('activities.create');
    Route::post('assignments/{assignment}/activities',[ActivityController::class, 'store'])->name('activities.store');
    Route::get('/activities/{activity}',[ActivityController::class, 'show'])->name('activities.show');
    Route::delete('/activities/{activity}', [ActivityController::class, 'destroy'])->name('activities.destroy');
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
    Route::get('practices/{practice}/submissions',[PracticeController::class, 'submissions'])->name('practices.submissions');
    Route::get('practice-submissions/{submission}',[PracticeController::class, 'submissionReport'])->name('practices.submissions.show');
    Route::get('practice-submissions/{submission}/review',[PracticeController::class, 'review'])->name('practices.submissions.review');
    Route::put('practice-submissions/{submission}/review',[PracticeController::class, 'storeReview'])->name('practices.submissions.review.store');
    Route::get('practice-submissions/{submission}/pdf',[PracticeController::class, 'submissionPdf'])->name('practices.submissions.pdf');

    Route::get('activities/{activity}/grades',[GradeController::class, 'index'])->name('grades.index');
    Route::post('activities/{activity}/grades',[GradeController::class, 'store'])->name('grades.store');

    Route::get('teacher/classes/{assignment}/configuration/evaluation',[EvaluationCriterionController::class, 'index'])->name('teacher.classes.evaluation.index');
    Route::post('teacher/classes/{assignment}/configuration/evaluation',[EvaluationCriterionController::class, 'store'])->name('teacher.classes.evaluation.store');
    Route::put('teacher/classes/{assignment}/configuration/evaluation',[EvaluationCriterionController::class, 'update'])->name('teacher.classes.evaluation.update');
    Route::post('teacher/classes/{assignment}/configuration/evaluation/clone',[EvaluationCriterionController::class, 'cloneFromSameSubject'])->name('teacher.classes.evaluation.clone');
    Route::delete('teacher/classes/{assignment}/configuration/evaluation/{criterion}',[EvaluationCriterionController::class, 'destroy'])->name('teacher.classes.evaluation.destroy');

    Route::post('assignments/{teachingAssignment}/clone-evaluation',[EvaluationSchemeCloneController::class, 'clone'])->name('teacher.evaluation.clone');
    Route::post('assignments/{assignment}/activities/clone', [ActivityCloneController::class, 'clone'])->name('activities.clone');
    Route::post('teacher/evaluation/{assignment}/activities/clone-same-subject', [ActivityCloneController::class, 'cloneToSameSubject'])->name('teacher.evaluation.activities.clone-same-subject');

    Route::get('teacher/justifications',[TeacherJustificationController::class, 'index'])->name('teacher.justifications.index');

    Route::get('performance',[TeacherPerformanceController::class, 'index'] )->name('teacher.performance.index');
    Route::get('performance/{assignment}',[TeacherPerformanceController::class, 'show'] )->name('teacher.performance.show');
    Route::get('performance/{assignment}/student/{student}', [TeacherPerformanceDetailController::class, 'show'])->name('performance.detail');

    Route::get('teacher/follow-ups', [TeacherFollowUpController::class, 'index'])->name('teacher.follow-ups.index');
    Route::get('teacher/follow-ups/{followUpTeacher}', [TeacherFollowUpController::class, 'show'])->name('teacher.follow-ups.show');
    Route::post('teacher/follow-ups/{followUpTeacher}/respond', [TeacherFollowUpController::class, 'respond'])->name('teacher.follow-ups.respond');
    Route::get('teacher/document-requests', [TeacherDocumentRequestController::class, 'index'])->name('teacher.document-requests.index');
    Route::get('teacher/document-requests/submissions/{submission}/pdf', [TeacherDocumentRequestController::class, 'showPdf'])->name('teacher.document-requests.submissions.pdf');
    Route::get('teacher/document-requests/{item}/content/pdf', [TeacherDocumentRequestController::class, 'showContentPdf'])->name('teacher.document-requests.content.pdf');
    Route::get('teacher/document-requests/{item}/content', [TeacherDocumentRequestController::class, 'editContent'])->name('teacher.document-requests.content.edit');
    Route::put('teacher/document-requests/{item}/content', [TeacherDocumentRequestController::class, 'updateContent'])->name('teacher.document-requests.content.update');
    Route::post('teacher/document-requests/{item}/generate', [TeacherDocumentRequestController::class, 'generate'])->name('teacher.document-requests.generate');
    Route::post('teacher/document-requests/{item}/upload', [TeacherDocumentRequestController::class, 'upload'])->name('teacher.document-requests.upload');
    Route::post('teacher/document-requests/{item}/clone-reglamento', [TeacherDocumentRequestController::class, 'cloneReglamento'])->name('teacher.document-requests.clone-reglamento');
    Route::post('teacher/document-requests/{item}/clone-criteria', [TeacherDocumentRequestController::class, 'cloneCriteria'])->name('teacher.document-requests.clone-criteria');
    
});

Route::middleware(['auth','role:student', 'campus.access'])->group(function () {
    Route::get('student/grades', [StudentPortalController::class, 'academicPerformance'])->name('student.grades');
    Route::get('student/subjects', [StudentPortalController::class, 'subjects'])->name('student.subjects');
    Route::get('student/subjects/{assignment}', [StudentPortalController::class, 'subjectShow'])->name('student.subjects.show');
    Route::get('student/reports', [StudentPortalController::class, 'reports'])->name('student.reports');
    Route::get('student/followups', [StudentPortalController::class, 'followUps'])->name('student.followups');
    Route::get('student/incident-reports', [StudentIncidentReportController::class, 'index'])->name('student.incident-reports.index');
    Route::get('student/incident-reports/create', [StudentIncidentReportController::class, 'create'])->name('student.incident-reports.create');
    Route::post('student/incident-reports', [StudentIncidentReportController::class, 'store'])->name('student.incident-reports.store');

    Route::get('student/practices', [StudentPracticeController::class, 'index'])->name('student.practices.index');
    Route::get('student/practices/{practice}', [StudentPracticeController::class, 'show'])->name('student.practices.show');
    Route::post('student/practices/{practice}', [StudentPracticeController::class, 'store'])->name('student.practices.store');
    Route::get('student/practices/{practice}/report', [StudentPracticeController::class, 'report'])->name('student.practices.report');
    Route::get('student/practices/{practice}/pdf', [StudentPracticeController::class, 'pdf'])->name('student.practices.pdf');
    Route::get('student/exams', [StudentOnlineExamController::class, 'index'])->name('student.exams.index');
    Route::get('student/exams/{paperExam}', [StudentOnlineExamController::class, 'show'])->name('student.exams.show');
    Route::post('student/exams/{paperExam}/start', [StudentOnlineExamController::class, 'start'])->name('student.exams.start');
    Route::post('student/exams/{paperExam}/attempts/{attempt}/submit', [StudentOnlineExamController::class, 'submit'])->name('student.exams.submit');
    Route::post('student/exams/{paperExam}/attempts/{attempt}/autosave', [StudentOnlineExamController::class, 'autosave'])->name('student.exams.autosave');
    Route::post('student/exams/{paperExam}/attempts/{attempt}/events', [StudentOnlineExamController::class, 'event'])->name('student.exams.event');
    Route::post('student/exams/{paperExam}/attempts/{attempt}/lock', [StudentOnlineExamController::class, 'lock'])->name('student.exams.lock');
});

Route::middleware(['auth', 'role:guardian|tutor', 'campus.access'])->prefix('tutor')->name('tutor.')->group(function () {
    Route::get('subjects', [TutorPortalController::class, 'subjects'])->name('subjects');
    Route::get('subjects/{assignment}', [TutorPortalController::class, 'subjectShow'])->name('subjects.show');
    Route::get('attendance', [TutorPortalController::class, 'attendance'])->name('attendance');
    Route::get('account-statement', [TutorPortalController::class, 'accountStatement'])->name('account-statement');
    Route::get('followups', [TutorPortalController::class, 'followUps'])->name('followups');
});

Route::middleware(['auth', 'role:prefect', 'campus.access'])->prefix('prefect')->name('prefect.')->group(function () {
    Route::get('groups', [PrefectGroupAttendanceController::class, 'index'])->name('groups.index');
    Route::get('groups/{group}/attendance', [PrefectGroupAttendanceController::class, 'attendance'])->name('groups.attendance');
    Route::get('groups/{group}/attendance/{date}', [PrefectGroupAttendanceController::class, 'attendanceDay'])->name('groups.attendance.day');
    Route::post('groups/{group}/attendance', [PrefectGroupAttendanceController::class, 'store'])->name('groups.attendance.store');
    Route::get('class-skips', [CoordinationClassSkipController::class, 'index'])->name('class-skips.index');

    Route::get('reports', [PrefectIncidentReportController::class, 'index'])->name('reports.index');
    Route::get('reports/create', [PrefectIncidentReportController::class, 'create'])->name('reports.create');
    Route::post('reports', [PrefectIncidentReportController::class, 'store'])->name('reports.store');
});

Route::middleware(['auth', 'role:coordinator|admin', 'campus.access'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('announcements', AnnouncementController::class);
});

Route::middleware(['auth', 'role:coordinator|admin', 'campus.access'])->prefix('coordination/students/{student}')->name('coordination.students.')->group(function () {
    Route::get('general', [CoordinationStudentController::class, 'general'])->name('general');
    Route::get('attendance', [CoordinationStudentController::class, 'attendance'])->name('attendance');
    Route::get('grades', [CoordinationStudentController::class, 'grades'])->name('grades');
    Route::get('followups', [CoordinationStudentController::class, 'followups'])->name('followups');
    
    Route::get('attendance-history', [CoordinationStudentController::class, 'attendanceHistory'])->name('attendance.history');
    Route::get('grades-history',[CoordinationStudentController::class, 'gradesHistory'])->name('grades.history');
});

Route::middleware(['auth', 'role:coordinator|admin', 'campus.access'])->group(function () {
    Route::get('coordination/teacher-attendance', [CoordinationTeacherAttendanceController::class, 'index'])->name('coordination.teacher-attendance.index');
    Route::post('coordination/teacher-attendance/manual', [CoordinationTeacherAttendanceController::class, 'storeManual'])->name('coordination.teacher-attendance.manual');
    Route::get('coordination/teacher-attendance/export', [CoordinationTeacherAttendanceController::class, 'export'])->name('coordination.teacher-attendance.export');
    Route::get('alerts/attendance/{modality}',[AdminAttendanceAlertController::class, 'fullDayAbsences'])->name('admin.alerts.attendance');
    Route::get('alerts/partial-attendance/{modality}',[AdminAttendanceAlertController::class, 'partialAttendance'])->name('admin.alerts.partial');
    Route::get('alerts/critical-subjects/{modality}',[AdminAcademicAlertController::class, 'criticalSubjects'])->name('admin.alerts.academic');
    Route::get('alerts/groups-in-alert',[AdminAcademicAlertController::class, 'groupsInAlert'])->name('admin.alerts.groups-in-alert');
    Route::get('alerts/teachers-low-registration',[AdminAttendanceAlertController::class, 'teachersLowRegistration'])->name('admin.alerts.teachers-low-registration');
});

Route::get('coordination/students/{student}/report-card',[StudentReportCardController::class, 'show'])->name('coordination.students.report-card');
Route::get('coordination/students/{student}/report-card/pdf',[StudentReportCardController::class, 'pdf'])->name('coordination.students.report-card.pdf');
