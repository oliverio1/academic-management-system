<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\CoordinationReport;
use App\Models\PrefectIncidentReport;
use App\Models\SchoolCase;
use App\Models\SchoolCaseAction;
use App\Models\SchoolCaseEntry;
use App\Models\Student;
use App\Models\StudentIncidentReport;
use App\Models\Teacher;
use App\Models\TeacherStudentReport;
use App\Models\User;
use App\Notifications\SchoolCaseAssignedNotification;
use App\Services\CurrentSchoolCycle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CoordinationSchoolCaseController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['status', 'priority', 'source_type', 'target_type', 'assigned_to', 'q']);

        $cases = SchoolCase::query()
            ->with(['sourceUser', 'student.user', 'group', 'teacher.user', 'guardian', 'assignee'])
            ->when(session('active_campus_id'), fn ($query, $campusId) => $query->where(function ($inner) use ($campusId) {
                $inner->whereNull('campus_id')->orWhere('campus_id', $campusId);
            }))
            ->when(app(CurrentSchoolCycle::class)->id(request()->user()), fn ($query, $cycleId) => $query->where(function ($inner) use ($cycleId) {
                $inner->whereNull('school_cycle_id')->orWhere('school_cycle_id', (int) $cycleId);
            }))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['priority'] ?? null, fn ($query, $value) => $query->where('priority', $value))
            ->when($filters['source_type'] ?? null, fn ($query, $value) => $query->where('source_type', $value))
            ->when($filters['target_type'] ?? null, fn ($query, $value) => $query->where('target_type', $value))
            ->when($filters['assigned_to'] ?? null, fn ($query, $value) => $query->where('assigned_to', $value))
            ->when($filters['q'] ?? null, function ($query, $value) {
                $query->where(function ($inner) use ($value) {
                    $inner->where('case_number', 'like', "%{$value}%")
                        ->orWhere('subject', 'like', "%{$value}%")
                        ->orWhere('description', 'like', "%{$value}%")
                        ->orWhere('location', 'like', "%{$value}%");
                });
            })
            ->orderByRaw("FIELD(status, 'new', 'in_progress', 'assigned', 'waiting_response', 'reviewed', 'resolved', 'closed')")
            ->orderByRaw("FIELD(priority, 'critical', 'high', 'medium', 'low')")
            ->latest()
            ->get();

        return view('coordination.school_cases.index', $this->catalogs() + [
            'cases' => $cases,
            'filters' => $filters,
            'users' => $this->users(),
        ]);
    }

    public function create(Request $request)
    {
        return view('coordination.school_cases.create', $this->catalogs() + $this->formData() + [
            'prefill' => $this->prefillFromReport($request),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedCase($request);

        $case = DB::transaction(function () use ($data, $request) {
            $caseData = $data;
            unset($caseData['source_report_type'], $caseData['source_report_id']);

            $case = SchoolCase::create($caseData + [
                'campus_id' => session('active_campus_id'),
                'school_cycle_id' => app(CurrentSchoolCycle::class)->id($request->user()),
                'case_number' => $this->nextCaseNumber(),
            ]);

            $case->entries()->create([
                'user_id' => $request->user()->id,
                'entry_type' => 'created',
                'visibility' => 'internal',
                'body' => 'Caso creado.',
            ]);

            if ($request->filled('initial_note')) {
                $case->entries()->create([
                    'user_id' => $request->user()->id,
                    'entry_type' => 'note',
                    'visibility' => 'internal',
                    'body' => $request->input('initial_note'),
                ]);
            }

            if ($request->filled('public_response')) {
                $case->entries()->create([
                    'user_id' => $request->user()->id,
                    'entry_type' => 'public_response',
                    'visibility' => 'reporter',
                    'body' => $request->input('public_response'),
                ]);
            }

            if ($request->filled('action_title')) {
                $action = $case->actions()->create([
                    'title' => $request->input('action_title'),
                    'assigned_to' => $request->input('action_assigned_to'),
                    'due_at' => $request->input('action_due_at'),
                ]);

                $this->notifyAssignedUser($case, $action);
            }

            if ($request->filled('source_report_type') && $request->filled('source_report_id')) {
                $case->entries()->create([
                    'user_id' => $request->user()->id,
                    'entry_type' => 'linked_report',
                    'visibility' => 'internal',
                    'body' => 'Caso generado desde la bandeja central de reportes.',
                    'meta' => [
                        'source_report_type' => $request->input('source_report_type'),
                        'source_report_id' => (int) $request->input('source_report_id'),
                    ],
                ]);

                $this->markSourceReportAsReviewed(
                    $request->input('source_report_type'),
                    (int) $request->input('source_report_id'),
                    $request->user()->id
                );
            }

            $this->notifyAssignedUser($case);

            return $case;
        });

        return redirect()
            ->route('coordination.school-cases.show', $case)
            ->with('info', 'Caso escolar creado correctamente.');
    }

    public function show(SchoolCase $schoolCase)
    {
        $this->authorizeCampus($schoolCase);

        $schoolCase->load([
            'sourceUser',
            'student.user',
            'group',
            'teacher.user',
            'guardian',
            'assignee',
            'closer',
            'entries.user',
            'actions.assignee',
        ]);

        return view('coordination.school_cases.show', $this->catalogs() + [
            'case' => $schoolCase,
            'users' => $this->users(),
        ]);
    }

    public function updateStatus(Request $request, SchoolCase $schoolCase)
    {
        $this->authorizeCampus($schoolCase);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys($this->statuses()))],
            'status_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $previous = $schoolCase->status;
        $closing = in_array($data['status'], [SchoolCase::STATUS_RESOLVED, SchoolCase::STATUS_CLOSED], true);

        $schoolCase->update([
            'status' => $data['status'],
            'closed_at' => $closing ? now() : null,
            'closed_by' => $closing ? $request->user()->id : null,
        ]);

        $schoolCase->entries()->create([
            'user_id' => $request->user()->id,
            'entry_type' => 'status_change',
            'visibility' => 'internal',
            'body' => $data['status_note'] ?: 'Cambio de estatus: '.$this->statuses()[$previous].' -> '.$this->statuses()[$data['status']].'.',
            'meta' => [
                'from' => $previous,
                'to' => $data['status'],
            ],
        ]);

        return back()->with('info', 'Estatus actualizado.');
    }

    public function storeEntry(Request $request, SchoolCase $schoolCase)
    {
        $this->authorizeCampus($schoolCase);

        $data = $request->validate([
            'entry_type' => ['required', Rule::in(['note', 'public_response'])],
            'visibility' => ['required', Rule::in(['internal', 'reporter', 'involved'])],
            'body' => ['required', 'string', 'max:8000'],
        ]);

        $entry = $schoolCase->entries()->create($data + [
            'user_id' => $request->user()->id,
        ]);

        if ($entry->entry_type === 'public_response') {
            $schoolCase->update(['public_response' => $entry->body]);
        }

        return back()->with('info', 'Nota agregada al caso.');
    }

    public function storeAction(Request $request, SchoolCase $schoolCase)
    {
        $this->authorizeCampus($schoolCase);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'assigned_to' => ['nullable', Rule::in($this->assignableUserIds())],
            'due_at' => ['nullable', 'date'],
        ]);

        $action = $schoolCase->actions()->create($data);

        $this->notifyAssignedUser($schoolCase, $action);

        return back()->with('info', 'Accion agregada al caso.');
    }

    public function completeAction(Request $request, SchoolCaseAction $action)
    {
        $this->authorizeCampus($action->schoolCase);

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $action->update([
            'status' => 'completed',
            'completed_at' => now(),
            'notes' => $data['notes'] ?? $action->notes,
        ]);

        $action->schoolCase->entries()->create([
            'user_id' => $request->user()->id,
            'entry_type' => 'action_completed',
            'visibility' => 'internal',
            'body' => 'Accion completada: '.$action->title,
        ]);

        $schoolCase = $action->schoolCase()->with('actions')->first();
        $hasPendingActions = $schoolCase->actions()
            ->where('status', '!=', 'completed')
            ->exists();

        if (! $hasPendingActions && ! in_array($schoolCase->status, [SchoolCase::STATUS_RESOLVED, SchoolCase::STATUS_CLOSED], true)) {
            $previousStatus = $schoolCase->status;

            $schoolCase->update([
                'status' => SchoolCase::STATUS_CLOSED,
                'closed_at' => now(),
                'closed_by' => $request->user()->id,
            ]);

            $schoolCase->entries()->create([
                'user_id' => $request->user()->id,
                'entry_type' => 'status_change',
                'visibility' => 'internal',
                'body' => 'Caso cerrado automaticamente al completar todas las acciones.',
                'meta' => [
                    'from' => $previousStatus,
                    'to' => SchoolCase::STATUS_CLOSED,
                ],
            ]);
        }

        return back()->with('info', 'Accion completada.');
    }

    private function validatedCase(Request $request): array
    {
        return $request->validate([
            'source_type' => ['required', Rule::in(array_keys($this->sourceTypes()))],
            'source_user_id' => ['nullable', 'exists:users,id'],
            'target_type' => ['required', Rule::in(array_keys($this->targetTypes()))],
            'student_id' => ['nullable', 'exists:students,id'],
            'group_id' => ['nullable', 'exists:groups,id'],
            'teacher_id' => ['nullable', 'exists:teachers,id'],
            'guardian_user_id' => ['nullable', 'exists:users,id'],
            'location' => ['nullable', 'string', 'max:255'],
            'category' => ['required', Rule::in(array_keys($this->categories()))],
            'priority' => ['required', Rule::in(array_keys($this->priorities()))],
            'status' => ['required', Rule::in(array_keys($this->statuses()))],
            'subject' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'max:8000'],
            'assigned_to' => ['nullable', Rule::in($this->assignableUserIds())],
            'due_at' => ['nullable', 'date'],
            'public_response' => ['nullable', 'string', 'max:8000'],
            'initial_note' => ['nullable', 'string', 'max:8000'],
            'action_title' => ['nullable', 'string', 'max:180'],
            'action_assigned_to' => ['nullable', Rule::in($this->assignableUserIds())],
            'action_due_at' => ['nullable', 'date'],
            'source_report_type' => ['nullable', 'in:teacher,student,prefect,coordination'],
            'source_report_id' => ['nullable', 'integer'],
        ]);
    }

    private function prefillFromReport(Request $request): array
    {
        $type = $request->query('source_report_type');
        $id = (int) $request->query('source_report_id');

        if (! $type || ! $id) {
            return [];
        }

        return match ($type) {
            'teacher' => $this->prefillFromTeacherReport($id),
            'student' => $this->prefillFromStudentReport($id),
            'prefect' => $this->prefillFromPrefectReport($id),
            'coordination' => $this->prefillFromCoordinationReport($id),
            default => [],
        };
    }

    private function prefillFromTeacherReport(int $id): array
    {
        $report = TeacherStudentReport::query()
            ->with(['teacher.user', 'student.user', 'student.group', 'group'])
            ->find($id);

        if (! $report) {
            return [];
        }

        return [
            'source_report_type' => 'teacher',
            'source_report_id' => $report->id,
            'source_type' => 'teacher',
            'source_user_id' => $report->teacher?->user_id,
            'target_type' => 'student',
            'student_id' => $report->student_id,
            'group_id' => $report->group_id,
            'teacher_id' => $report->teacher_id,
            'guardian_user_id' => $report->student?->guardian_user_id,
            'category' => match ($report->report_type) {
                'academic' => 'academic',
                'behavioral' => 'behavioral',
                default => 'other',
            },
            'priority' => match ((int) $report->severity) {
                3 => 'high',
                2 => 'medium',
                default => 'low',
            },
            'status' => SchoolCase::STATUS_NEW,
            'subject' => 'Seguimiento a reporte docente',
            'description' => trim(implode("\n", array_filter([
                'Reporte realizado por: '.$report->teacher?->user?->name,
                'Alumno: '.$report->student?->user?->name,
                'Grupo: '.$report->group?->name,
                'Tipo: '.$report->report_type,
                'Motivo: '.$report->reason,
            ]))),
        ];
    }

    private function prefillFromStudentReport(int $id): array
    {
        $report = StudentIncidentReport::query()
            ->with(['student.user', 'student.group'])
            ->find($id);

        if (! $report) {
            return [];
        }

        return [
            'source_report_type' => 'student',
            'source_report_id' => $report->id,
            'source_type' => 'student',
            'source_user_id' => $report->student?->user_id,
            'target_type' => 'student',
            'student_id' => $report->student_id,
            'group_id' => $report->student?->group_id,
            'guardian_user_id' => $report->student?->guardian_user_id,
            'category' => $this->mapIncidentCategory($report->category),
            'priority' => 'medium',
            'status' => SchoolCase::STATUS_NEW,
            'subject' => $report->subject,
            'description' => trim(implode("\n", array_filter([
                'Reporte realizado por alumno: '.$report->student?->user?->name,
                'Grupo: '.$report->student?->group?->name,
                'Categoria original: '.$report->category,
                'Descripcion: '.$report->description,
            ]))),
        ];
    }

    private function prefillFromPrefectReport(int $id): array
    {
        $report = PrefectIncidentReport::query()
            ->with('reporter')
            ->find($id);

        if (! $report) {
            return [];
        }

        return [
            'source_report_type' => 'prefect',
            'source_report_id' => $report->id,
            'source_type' => 'prefect',
            'source_user_id' => $report->reported_by,
            'target_type' => $report->category === 'facilities' ? 'classroom' : 'general',
            'category' => $this->mapIncidentCategory($report->category),
            'priority' => 'medium',
            'status' => SchoolCase::STATUS_NEW,
            'subject' => $report->subject,
            'description' => trim(implode("\n", array_filter([
                'Reporte realizado por prefectura: '.$report->reporter?->name,
                'Categoria original: '.$report->category,
                'Descripcion: '.$report->description,
            ]))),
        ];
    }

    private function prefillFromCoordinationReport(int $id): array
    {
        $report = CoordinationReport::query()
            ->with('reporter')
            ->find($id);

        if (! $report) {
            return [];
        }

        return [
            'source_report_type' => 'coordination',
            'source_report_id' => $report->id,
            'source_type' => 'coordination',
            'source_user_id' => $report->reported_by,
            'target_type' => 'general',
            'category' => $report->category,
            'priority' => match ((int) $report->priority) {
                3 => 'high',
                2 => 'medium',
                default => 'low',
            },
            'status' => SchoolCase::STATUS_NEW,
            'subject' => $report->subject,
            'description' => trim(implode("\n", array_filter([
                'Reporte levantado por coordinacion: '.$report->reporter?->name,
                'Persona que reporta: '.$report->reporter_name,
                'Contacto: '.$report->reporter_contact,
                'Medio: '.$report->received_via,
                'Descripcion: '.$report->description,
            ]))),
        ];
    }

    private function mapIncidentCategory(?string $category): string
    {
        return match ($category) {
            'facilities' => 'facilities',
            'academic' => 'academic',
            'behavioral', 'classmates' => 'behavioral',
            default => 'other',
        };
    }

    private function markSourceReportAsReviewed(?string $type, int $id, int $userId): void
    {
        $model = match ($type) {
            'teacher' => TeacherStudentReport::query()->find($id),
            'student' => StudentIncidentReport::query()->find($id),
            'prefect' => PrefectIncidentReport::query()->find($id),
            'coordination' => CoordinationReport::query()->find($id),
            default => null,
        };

        if ($model && $model->status === 'open') {
            $model->update([
                'status' => 'reviewed',
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
            ]);
        }
    }

    private function notifyAssignedUser(SchoolCase $schoolCase, ?SchoolCaseAction $action = null): void
    {
        $assignedUserId = $action?->assigned_to ?? $schoolCase->assigned_to;

        if (! $assignedUserId) {
            return;
        }

        $user = User::query()->find($assignedUserId);

        if (! $user) {
            return;
        }

        $user->notify(new SchoolCaseAssignedNotification($schoolCase, $action));
    }

    private function formData(): array
    {
        $campusId = session('active_campus_id');

        return [
            'users' => $this->users(),
            'students' => Student::query()
                ->with(['user', 'group'])
                ->when($campusId, fn ($query) => $query->whereHas('user.campuses', fn ($inner) => $inner->whereKey($campusId)))
                ->orderBy('id')
                ->get(),
            'groups' => Group::query()->orderBy('name')->get(),
            'teachers' => Teacher::query()->with('user')->orderBy('id')->get(),
            'guardians' => User::role(['guardian', 'tutor'])->orderBy('name')->get(),
        ];
    }

    private function users()
    {
        return User::query()
            ->whereDoesntHave('roles', fn ($query) => $query->whereIn('name', $this->excludedAssigneeRoles()))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    private function assignableUserIds(): array
    {
        return $this->users()
            ->pluck('id')
            ->all();
    }

    private function excludedAssigneeRoles(): array
    {
        return ['teacher', 'student', 'guardian', 'tutor'];
    }

    private function catalogs(): array
    {
        return [
            'sourceTypes' => $this->sourceTypes(),
            'targetTypes' => $this->targetTypes(),
            'categories' => $this->categories(),
            'priorities' => $this->priorities(),
            'statuses' => $this->statuses(),
            'visibilityOptions' => [
                'internal' => 'Interna',
                'reporter' => 'Visible para quien reporto',
                'involved' => 'Visible para involucrados',
            ],
        ];
    }

    private function sourceTypes(): array
    {
        return [
            'teacher' => 'Docente',
            'student' => 'Alumno',
            'prefect' => 'Prefectura',
            'guardian' => 'Tutor',
            'coordination' => 'Coordinacion',
            'direction' => 'Direccion',
            'admin' => 'Administrativo',
            'system' => 'Sistema',
            'other' => 'Otro',
        ];
    }

    private function targetTypes(): array
    {
        return [
            'student' => 'Alumno',
            'group' => 'Grupo',
            'teacher' => 'Docente',
            'guardian' => 'Tutor',
            'classroom' => 'Salon',
            'equipment' => 'Equipo',
            'service' => 'Servicio',
            'general' => 'General',
            'other' => 'Otro',
        ];
    }

    private function categories(): array
    {
        return [
            'academic' => 'Academico',
            'behavioral' => 'Conductual',
            'attendance' => 'Asistencia',
            'communication' => 'Comunicacion',
            'facilities' => 'Instalaciones',
            'technology' => 'Tecnologia',
            'maintenance' => 'Mantenimiento',
            'administrative' => 'Administrativo',
            'safety' => 'Seguridad',
            'health' => 'Salud',
            'other' => 'Otro',
        ];
    }

    private function priorities(): array
    {
        return [
            'low' => 'Baja',
            'medium' => 'Media',
            'high' => 'Alta',
            'critical' => 'Critica',
        ];
    }

    private function statuses(): array
    {
        return [
            SchoolCase::STATUS_NEW => 'Nuevo',
            SchoolCase::STATUS_REVIEWED => 'Revisado',
            SchoolCase::STATUS_ASSIGNED => 'Asignado',
            SchoolCase::STATUS_IN_PROGRESS => 'En seguimiento',
            SchoolCase::STATUS_WAITING_RESPONSE => 'Esperando respuesta',
            SchoolCase::STATUS_RESOLVED => 'Resuelto',
            SchoolCase::STATUS_CLOSED => 'Cerrado',
        ];
    }

    private function nextCaseNumber(): string
    {
        $year = now()->format('Y');
        $next = SchoolCase::query()
            ->whereYear('created_at', $year)
            ->lockForUpdate()
            ->count() + 1;

        return 'CASE-'.$year.'-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function authorizeCampus(SchoolCase $case): void
    {
        $campusId = session('active_campus_id');
        $cycleId = app(CurrentSchoolCycle::class)->id(auth()->user(), (int) $campusId);

        abort_if($campusId && $case->campus_id && (int) $case->campus_id !== (int) $campusId, 403);
        abort_if($cycleId && $case->school_cycle_id && (int) $case->school_cycle_id !== (int) $cycleId, 403);
    }
}
