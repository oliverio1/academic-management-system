<?php

namespace App\Services;

use App\Models\TeacherDocumentRequest;
use App\Models\TeacherDocumentRequestItem;
use App\Models\TeachingAssignment;
use App\Models\User;
use Carbon\Carbon;

class TeacherDocumentChecklistService
{
    private const BASE_REQUIRED_DOCUMENT_TYPES = [
        'reglamento',
        'criterios_evaluacion',
        'cuadernillo_actividades',
        'guias_parciales',
        'planeacion',
        'temario',
    ];

    public const REQUIRED_DOCUMENT_TYPES = [
        'reglamento',
        'examen_parcial_1',
        'examen_parcial_2',
        'examen_parcial_3',
        'examen_parcial_4',
        'criterios_evaluacion',
        'cuadernillo_actividades',
        'guias_parciales',
        'planeacion',
        'temario',
    ];

    private const STUDENT_VISIBLE_TYPES = [
        'reglamento',
        'cuadernillo_actividades',
        'guias_parciales',
        'temario',
    ];

    public function ensureForAssignment(TeachingAssignment $assignment): void
    {
        $assignment->loadMissing(['schoolCycleGroup.schoolCycle', 'subject', 'group']);

        if (! $assignment->is_active || ! $assignment->teacher_id || ! $assignment->schoolCycleGroup) {
            return;
        }

        if (! $this->isCanonicalAssignment($assignment)) {
            return;
        }

        $cycleGroup = $assignment->schoolCycleGroup;
        $cycle = $cycleGroup->schoolCycle;

        if (! $cycle || ! $cycleGroup->campus_id) {
            return;
        }

        $tenantId = (string) ($assignment->tenant_id ?: tenant('id'));
        if ($tenantId === '') {
            return;
        }

        $systemUserId = $this->systemUserId();
        if ($systemUserId <= 0) {
            return;
        }

        $request = TeacherDocumentRequest::query()->firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'campus_id' => (int) $cycleGroup->campus_id,
                'school_cycle_id' => (int) $cycle->id,
                'title' => $this->requestTitle($assignment),
            ],
            [
                'instructions' => 'Expediente docente obligatorio generado automaticamente al dar de alta la asignacion.',
                'due_date' => $this->defaultDueDate($cycle),
                'status' => 'open',
                'created_by' => $systemUserId,
            ]
        );

        if ($request->status !== 'open') {
            $request->update(['status' => 'open']);
        }

        $requiredTypes = $this->requiredDocumentTypesForCycle($cycle);

        foreach ($requiredTypes as $documentType) {
            TeacherDocumentRequestItem::query()->firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'request_id' => (int) $request->id,
                    'teaching_assignment_id' => (int) $assignment->id,
                    'document_type' => $documentType,
                ],
                [
                    'notes' => null,
                    'is_required' => true,
                    'is_student_visible' => in_array($documentType, self::STUDENT_VISIBLE_TYPES, true),
                ]
            );
        }

        TeacherDocumentRequestItem::query()
            ->where('request_id', (int) $request->id)
            ->where('teaching_assignment_id', (int) $assignment->id)
            ->whereNotIn('document_type', $requiredTypes)
            ->where('document_type', 'like', 'examen_parcial_%')
            ->whereDoesntHave('submissions')
            ->delete();

        TeacherDocumentRequestItem::query()
            ->where('request_id', (int) $request->id)
            ->where('teaching_assignment_id', (int) $assignment->id)
            ->where('document_type', 'examenes')
            ->whereDoesntHave('submissions')
            ->delete();
    }

    public function requiredDocumentTypesForCycle($cycle): array
    {
        $partialCount = (int) $cycle->partials()
            ->where('is_active', true)
            ->count();

        if ($partialCount <= 0) {
            $partialCount = 4;
        }

        $partialCount = min($partialCount, 4);
        $examTypes = [];
        for ($index = 1; $index <= $partialCount; $index++) {
            $examTypes[] = 'examen_parcial_' . $index;
        }

        return [
            'reglamento',
            ...$examTypes,
            ...array_values(array_filter(
                self::BASE_REQUIRED_DOCUMENT_TYPES,
                fn (string $type) => $type !== 'reglamento'
            )),
        ];
    }

    public function isCanonicalAssignment(TeachingAssignment $assignment): bool
    {
        $canonicalId = $this->canonicalAssignmentId($assignment);

        return $canonicalId > 0 && (int) $assignment->id === $canonicalId;
    }

    public function canonicalAssignmentId(TeachingAssignment $assignment): int
    {
        if (! $assignment->teacher_id || ! $assignment->school_cycle_group_id || ! $assignment->subject_id) {
            return (int) $assignment->id;
        }

        return (int) TeachingAssignment::query()
            ->where('is_active', true)
            ->where('teacher_id', (int) $assignment->teacher_id)
            ->where('school_cycle_group_id', (int) $assignment->school_cycle_group_id)
            ->where('subject_id', (int) $assignment->subject_id)
            ->orderByRaw("CASE WHEN section_type IS NULL OR section_type = '' THEN 0 ELSE 1 END")
            ->orderBy('section_number')
            ->orderBy('id')
            ->value('id');
    }

    private function requestTitle(TeachingAssignment $assignment): string
    {
        $subject = $assignment->subject?->name ?: 'Materia';
        $group = $assignment->group?->name ?: 'Grupo';

        return "Expediente docente automatico - {$subject} - {$group}";
    }

    private function defaultDueDate($cycle): string
    {
        $base = $cycle?->start_date
            ? Carbon::parse($cycle->start_date)
            : now();

        if ($base->isPast()) {
            $base = now();
        }

        return $base->copy()->addDays(15)->toDateString();
    }

    private function systemUserId(): int
    {
        $authId = (int) (auth()->id() ?? 0);
        if ($authId > 0) {
            return $authId;
        }

        return (int) (User::role('coordinator')->orderBy('id')->value('id')
            ?: User::role('admin')->orderBy('id')->value('id')
            ?: User::query()->orderBy('id')->value('id'));
    }
}
