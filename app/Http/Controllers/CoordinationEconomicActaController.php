<?php

namespace App\Http\Controllers;

use App\Models\CyclePartial;
use App\Models\EconomicActa;
use App\Models\EconomicActaEvent;
use App\Models\EconomicActaReopenRequest;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\TeachingAssignment;
use App\Notifications\CoordinatorReviewNotification;
use App\Services\AttendanceService;
use App\Services\CurrentSchoolCycle;
use App\Services\GradeService;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CoordinationEconomicActaController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = $this->tenantId();

        $cycles = SchoolCycle::query()
            ->with('modality')
            ->whereHas('cycleGroups', fn ($q) => $q->where('tenant_id', $tenantId))
            ->when((int) $request->session()->get('active_campus_id', 0) > 0, fn ($q) => $this->applyCampusFilterToCycleQuery($q, (int) $request->session()->get('active_campus_id')))
            ->orderByDesc('start_date')
            ->get();

        $currentCycle = app(CurrentSchoolCycle::class)->get($request->user(), (int) $request->session()->get('active_campus_id', 0));
        $defaultCycle = $currentCycle && $cycles->contains(fn ($cycle) => (int) $cycle->id === (int) $currentCycle->id)
            ? $currentCycle
            : ($cycles->firstWhere('is_active', true) ?? $cycles->first());
        $selectedCycleId = (int) ($request->query('school_cycle_id') ?: optional($defaultCycle)->id);
        $selectedCycle = $selectedCycleId > 0 ? $cycles->firstWhere('id', $selectedCycleId) : null;

        $partials = collect();
        $selectedPartial = null;
        $assignments = collect();
        $actasByAssignment = collect();
        $pendingReopenRequestsByActa = collect();
        $resume = [
            'submitted' => 0,
            'draft' => 0,
            'closed' => 0,
            'sent' => 0,
            'pending' => 0,
        ];

        if ($selectedCycle) {
            $activeCampusId = (int) $request->session()->get('active_campus_id', 0);
            $partials = $selectedCycle->partials()
                ->with('academicPeriod')
                ->orderBy('sort_order')
                ->get();

            $defaultPartial = $partials->firstWhere('is_active', true) ?? $partials->first();
            $selectedPartialId = (int) ($request->query('partial_id') ?: optional($defaultPartial)->id);
            $selectedPartial = $selectedPartialId > 0
                ? $partials->firstWhere('id', $selectedPartialId)
                : null;

            $groupIds = SchoolCycleGroup::query()
                ->where('school_cycle_id', $selectedCycle->id)
                ->where('is_active', true)
                ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId))
                ->pluck('group_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (!empty($groupIds)) {
                $assignments = TeachingAssignment::query()
                    ->with(['group', 'subject', 'teacher.user'])
                    ->where('is_active', true)
                    ->whereIn('group_id', $groupIds)
                    ->whereHas('schedules', function ($query) use ($selectedCycle) {
                        $query->where('school_cycle_id', $selectedCycle->id)
                            ->where('is_active', true);
                    })
                    ->get()
                    ->sortBy(fn (TeachingAssignment $a) => mb_strtolower(($a->group->name ?? '') . '|' . ($a->subject->name ?? '')))
                    ->values();
            }

            if ($selectedPartial && $assignments->isNotEmpty()) {
                $actasByAssignment = EconomicActa::query()
                    ->where('cycle_partial_id', $selectedPartial->id)
                    ->whereIn('teaching_assignment_id', $assignments->pluck('id')->all())
                    ->with(['submittedByUser', 'draftedByUser', 'closedByUser', 'sentByUser'])
                    ->get()
                    ->keyBy('teaching_assignment_id');

                $resume['submitted'] = $actasByAssignment->where('status', 'submitted')->count();
                $resume['draft'] = $actasByAssignment->where('status', 'draft')->count();
                $resume['closed'] = $actasByAssignment->where('status', 'closed')->count();
                $resume['sent'] = $actasByAssignment->where('status', 'sent')->count();
                $resume['pending'] = max(0, $assignments->count() - $actasByAssignment->count());

                $pendingReopenRequestsByActa = EconomicActaReopenRequest::query()
                    ->whereIn('economic_acta_id', $actasByAssignment->pluck('id')->all())
                    ->where('status', 'pending')
                    ->with('requestedByUser')
                    ->get()
                    ->keyBy('economic_acta_id');
            }
        }

        return view('coordination.economic_actas.index', [
            'cycles' => $cycles,
            'selectedCycle' => $selectedCycle,
            'partials' => $partials,
            'selectedPartial' => $selectedPartial,
            'assignments' => $assignments,
            'actasByAssignment' => $actasByAssignment,
            'pendingReopenRequestsByActa' => $pendingReopenRequestsByActa,
            'resume' => $resume,
        ]);
    }

    public function draft(CyclePartial $partial, TeachingAssignment $assignment, Request $request)
    {
        $this->ensurePartialCampusByPartial($partial, $request);
        $this->assertAssignmentBelongsToPartialCycle($assignment, $partial);

        $userId = (int) auth()->id();

        DB::transaction(function () use ($partial, $assignment, $userId, $request) {
            $acta = EconomicActa::query()
                ->where('teaching_assignment_id', $assignment->id)
                ->where('cycle_partial_id', $partial->id)
                ->first();

            abort_if(!$acta, 422, 'El profesor debe enviar primero la materia a coordinacion.');
            abort_if($acta->status === 'sent', 422, 'El acta ya fue enviada y no puede volver a borrador.');
            abort_if(!in_array($acta->status, ['submitted', 'draft', 'closed'], true), 422, 'Solo se puede generar borrador desde estado enviado o cerrado.');

            $fromStatus = $acta->status;

            $acta->update([
                'status' => 'draft',
                'drafted_by' => $userId,
                'drafted_at' => now(),
            ]);

            EconomicActaEvent::create([
                'economic_acta_id' => $acta->id,
                'from_status' => $fromStatus,
                'to_status' => 'draft',
                'changed_by' => $userId,
                'changed_at' => now(),
                'comment' => $request->input('comment'),
            ]);
        });

        return back()->with('info', 'Acta economica en borrador.');
    }

    public function initialize(CyclePartial $partial, Request $request)
    {
        $cycle = SchoolCycle::query()
            ->where('id', $partial->school_cycle_id)
            ->whereHas('cycleGroups', fn ($q) => $q->where('tenant_id', $this->tenantId()))
            ->firstOrFail();

        $this->ensurePartialMatchesCampus($cycle, $request);

        $groupIds = SchoolCycleGroup::query()
            ->where('school_cycle_id', $cycle->id)
            ->where('is_active', true)
            ->when((int) $request->session()->get('active_campus_id', 0) > 0, fn ($q) => $q->where('campus_id', (int) $request->session()->get('active_campus_id')))
            ->pluck('group_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($groupIds)) {
            return back()->with('warning', 'No hay grupos activos para inicializar actas en este ciclo.');
        }

        $assignments = TeachingAssignment::query()
            ->with(['group', 'subject', 'teacher.user'])
            ->where('is_active', true)
            ->whereIn('group_id', $groupIds)
            ->whereHas('schedules', fn ($query) => $query
                ->where('school_cycle_id', $cycle->id)
                ->where('is_active', true)
            )
            ->get();

        $created = 0;
        $createdAssignmentIds = [];
        $coordinatorId = (int) auth()->id();

        DB::transaction(function () use ($assignments, $partial, &$created, &$createdAssignmentIds, $coordinatorId) {
            foreach ($assignments as $assignment) {
                $acta = EconomicActa::query()->firstOrCreate(
                    [
                        'teaching_assignment_id' => (int) $assignment->id,
                        'cycle_partial_id' => (int) $partial->id,
                    ],
                    [
                        'status' => 'draft',
                        'drafted_by' => $coordinatorId,
                        'drafted_at' => now(),
                        'notes' => 'Proceso iniciado por coordinación.',
                    ]
                );

                if ($acta->wasRecentlyCreated) {
                    $created++;
                    $createdAssignmentIds[] = (int) $assignment->id;

                    EconomicActaEvent::create([
                        'economic_acta_id' => $acta->id,
                        'from_status' => null,
                        'to_status' => 'draft',
                        'changed_by' => $coordinatorId,
                        'changed_at' => now(),
                        'comment' => 'Acta inicializada por coordinación.',
                    ]);
                }
            }
        });

        $notified = $this->notifyTeachersForAssignments(
            $assignments->whereIn('id', $createdAssignmentIds),
            $partial,
            'economic_acta_initialized',
            'Captura de acta habilitada',
            'Coordinación inició el proceso de actas para '
        );

        return back()->with('info', "Proceso iniciado. Actas nuevas: {$created}. Docentes notificados: {$notified}.");
    }

    public function remindPending(CyclePartial $partial, Request $request)
    {
        $cycle = SchoolCycle::query()
            ->where('id', $partial->school_cycle_id)
            ->whereHas('cycleGroups', fn ($q) => $q->where('tenant_id', $this->tenantId()))
            ->firstOrFail();

        $this->ensurePartialMatchesCampus($cycle, $request);

        $groupIds = SchoolCycleGroup::query()
            ->where('school_cycle_id', $cycle->id)
            ->where('is_active', true)
            ->when((int) $request->session()->get('active_campus_id', 0) > 0, fn ($q) => $q->where('campus_id', (int) $request->session()->get('active_campus_id')))
            ->pluck('group_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($groupIds)) {
            return back()->with('warning', 'No hay grupos activos para recordar pendientes.');
        }

        $assignments = TeachingAssignment::query()
            ->with(['group', 'subject', 'teacher.user'])
            ->where('is_active', true)
            ->whereIn('group_id', $groupIds)
            ->whereHas('schedules', fn ($query) => $query
                ->where('school_cycle_id', $cycle->id)
                ->where('is_active', true)
            )
            ->get();

        $existingActasByAssignment = EconomicActa::query()
            ->where('cycle_partial_id', (int) $partial->id)
            ->whereIn('teaching_assignment_id', $assignments->pluck('id')->all())
            ->get()
            ->keyBy('teaching_assignment_id');

        $pendingAssignments = $assignments->filter(function (TeachingAssignment $assignment) use ($existingActasByAssignment) {
            $acta = $existingActasByAssignment->get((int) $assignment->id);
            return ! $acta || in_array((string) $acta->status, ['draft'], true);
        })->values();

        if ($pendingAssignments->isEmpty()) {
            return back()->with('info', 'No hay pendientes para recordar en este parcial.');
        }

        $notified = $this->notifyTeachersForAssignments(
            $pendingAssignments,
            $partial,
            'economic_acta_reminder',
            'Recordatorio de acta económica',
            'Sigue pendiente la captura/envío de acta para '
        );

        return back()->with('info', "Recordatorio enviado a {$notified} docente(s).");
    }

    public function close(CyclePartial $partial, TeachingAssignment $assignment, Request $request)
    {
        $this->ensurePartialCampusByPartial($partial, $request);
        $this->assertAssignmentBelongsToPartialCycle($assignment, $partial);

        $acta = EconomicActa::query()
            ->where('cycle_partial_id', $partial->id)
            ->where('teaching_assignment_id', $assignment->id)
            ->first();

        abort_if(!$acta, 422, 'Primero debes generar el borrador.');
        abort_if(!in_array($acta->status, ['submitted', 'draft'], true), 422, 'Solo se puede cerrar un acta enviada por docente o en borrador.');

        $userId = (int) auth()->id();

        DB::transaction(function () use ($acta, $userId, $request) {
            $fromStatus = $acta->status;

            $acta->update([
                'status' => 'closed',
                'closed_by' => $userId,
                'closed_at' => now(),
                'notes' => $request->input('notes', $acta->notes),
            ]);

            EconomicActaEvent::create([
                'economic_acta_id' => $acta->id,
                'from_status' => $fromStatus,
                'to_status' => 'closed',
                'changed_by' => $userId,
                'changed_at' => now(),
                'comment' => $request->input('comment'),
            ]);
        });

        return back()->with('info', 'Acta economica cerrada.');
    }

    public function send(CyclePartial $partial, TeachingAssignment $assignment, Request $request)
    {
        $this->ensurePartialCampusByPartial($partial, $request);
        $this->assertAssignmentBelongsToPartialCycle($assignment, $partial);

        $acta = EconomicActa::query()
            ->where('cycle_partial_id', $partial->id)
            ->where('teaching_assignment_id', $assignment->id)
            ->first();

        abort_if(!$acta, 422, 'Primero debes generar el borrador.');
        abort_if($acta->status !== 'closed', 422, 'Solo se puede enviar un acta cerrada.');

        $data = $request->validate([
            'sent_reference' => 'nullable|string|max:120',
        ]);

        $userId = (int) auth()->id();

        DB::transaction(function () use ($acta, $userId, $data, $request) {
            $fromStatus = $acta->status;

            $acta->update([
                'status' => 'sent',
                'sent_by' => $userId,
                'sent_at' => now(),
                'sent_reference' => $data['sent_reference'] ?? $acta->sent_reference,
            ]);

            EconomicActaEvent::create([
                'economic_acta_id' => $acta->id,
                'from_status' => $fromStatus,
                'to_status' => 'sent',
                'changed_by' => $userId,
                'changed_at' => now(),
                'comment' => $request->input('comment'),
            ]);
        });

        return back()->with('info', 'Acta economica enviada.');
    }

    public function pdf(
        EconomicActa $acta,
        GradeService $gradeService,
        AttendanceService $attendanceService
    ) {
        $acta->loadMissing([
            'assignment.group.students.user',
            'assignment.subject',
            'assignment.teacher.user',
            'partial.schoolCycle.modality',
            'partial.academicPeriod',
            'submittedByUser',
            'draftedByUser',
            'closedByUser',
            'sentByUser',
            'events.changedByUser',
        ]);

        $assignment = $acta->assignment;
        $partial = $acta->partial;
        $this->ensurePartialCampusByPartial($partial, request());

        $this->assertAssignmentBelongsToPartialCycle($assignment, $partial);

        $from = $partial->start_date ? Carbon::parse($partial->start_date)->startOfDay() : null;
        $to = $partial->end_date ? Carbon::parse($partial->end_date)->endOfDay() : null;

        $students = $assignment->group->students
            ->where('is_active', true)
            ->sortBy(fn ($student) => mb_strtolower((string) optional($student->user)->name))
            ->values();

        $rows = $students->map(function ($student, $idx) use ($assignment, $gradeService, $attendanceService, $from, $to) {
            return [
                'num' => $idx + 1,
                'enrollment' => $student->enrollment_number,
                'name' => $student->user->name ?? '-',
                'grade' => $gradeService->finalGrade($assignment, $student, $from, $to),
                'attendance' => $attendanceService->attendancePercentage($assignment, $student, $from, $to),
            ];
        });

        $filename = 'ACTA_ECONOMICA_' . $assignment->group->name . '_' . preg_replace('/\s+/', '_', $assignment->subject->name) . '.pdf';

        return SnappyPdf::loadView('coordination.economic_actas.pdf', [
            'acta' => $acta,
            'assignment' => $assignment,
            'partial' => $partial,
            'rows' => $rows,
        ])
            ->setPaper('letter')
            ->setOption('encoding', 'UTF-8')
            ->setOption('disable-javascript', true)
            ->setOption('enable-local-file-access', true)
            ->setOption('footer-right', 'Pagina [page] de [toPage]')
            ->inline($filename);
    }

    private function assertAssignmentBelongsToPartialCycle(TeachingAssignment $assignment, CyclePartial $partial): void
    {
        $belongs = $assignment->schedules()
            ->where('school_cycle_id', $partial->school_cycle_id)
            ->exists();

        abort_if(!$belongs, 404);
    }

    private function tenantId(): string
    {
        $tenantId = (string) tenant('id');
        abort_if($tenantId === '', 403, 'Tenant no identificado.');

        return $tenantId;
    }

    private function ensurePartialMatchesCampus(SchoolCycle $cycle, Request $request): void
    {
        $activeCampusId = (int) $request->session()->get('active_campus_id', 0);
        if ($activeCampusId > 0) {
            $belongsToCampus = SchoolCycle::query()
                ->whereKey((int) $cycle->id)
                ->where(function ($q) use ($activeCampusId) {
                    $q->where('campus_id', $activeCampusId)
                        ->orWhereHas('campuses', fn ($campuses) => $campuses->where('campuses.id', $activeCampusId));
                })
                ->exists();
            abort_if(! $belongsToCampus, 404);
        }
    }

    private function notifyTeachersForAssignments(
        $assignments,
        CyclePartial $partial,
        string $type,
        string $title,
        string $messagePrefix
    ): int {
        $notifiedUserIds = [];

        foreach ($assignments as $assignment) {
            $teacherUser = $assignment->teacher->user ?? null;
            if (! $teacherUser) {
                continue;
            }

            $teacherUser->notify(new CoordinatorReviewNotification([
                'type' => $type,
                'title' => $title,
                'message' => $messagePrefix . ($assignment->subject->name ?? 'la materia')
                    . ' del grupo ' . ($assignment->group->name ?? '-')
                    . '. Parcial: ' . ($partial->name ?? 'N/D') . '.',
                'url' => route('assignments.show', [$assignment, 'tab' => 'grades']),
                'meta' => [
                    'teaching_assignment_id' => (int) $assignment->id,
                    'cycle_partial_id' => (int) $partial->id,
                ],
            ]));

            $notifiedUserIds[(int) $teacherUser->id] = true;
        }

        return count($notifiedUserIds);
    }

    private function ensurePartialCampusByPartial(CyclePartial $partial, Request $request): void
    {
        $cycle = SchoolCycle::query()
            ->where('id', $partial->school_cycle_id)
            ->whereHas('cycleGroups', fn ($q) => $q->where('tenant_id', $this->tenantId()))
            ->firstOrFail();

        $this->ensurePartialMatchesCampus($cycle, $request);
    }

    private function applyCampusFilterToCycleQuery($query, int $activeCampusId): void
    {
        $query->where(function ($nested) use ($activeCampusId) {
            $nested->where('campus_id', $activeCampusId)
                ->orWhereHas('campuses', fn ($campuses) => $campuses->where('campuses.id', $activeCampusId));
        });
    }
}
