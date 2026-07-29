<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\CyclePartial;
use App\Models\DidacticPlan;
use App\Models\EvaluationCriterion;
use App\Models\PaperExam;
use App\Models\SchoolCycle;
use App\Models\Teacher;
use App\Models\TeacherDocumentContent;
use App\Models\TeacherDocumentRequestItem;
use App\Models\TeachingAssignment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AcademicCycleArtifactCloneService
{
    public function clone(SchoolCycle $sourceCycle, SchoolCycle $targetCycle, Teacher $teacher, array $options = []): array
    {
        $artifacts = collect($options['artifacts'] ?? ['rubrics', 'plans', 'exams', 'documents'])
            ->map(fn ($artifact) => trim((string) $artifact))
            ->filter()
            ->values();
        $dryRun = (bool) ($options['dry_run'] ?? true);
        $replace = (bool) ($options['replace'] ?? false);

        $sourceAssignments = $this->assignmentsForCycle($sourceCycle, $teacher)
            ->groupBy(fn (TeachingAssignment $assignment) => (int) $assignment->subject_id);
        $targetAssignments = $this->assignmentsForCycle($targetCycle, $teacher);
        $partialMap = $this->partialMap($sourceCycle, $targetCycle);

        $summary = [
            'dry_run' => $dryRun,
            'source_cycle' => $sourceCycle->name,
            'target_cycle' => $targetCycle->name,
            'teacher' => $teacher->user?->name ?? ('ID '.$teacher->id),
            'targets' => $targetAssignments->count(),
            'rubrics' => ['created' => 0, 'skipped' => []],
            'plans' => ['created' => 0, 'skipped' => []],
            'exams' => ['created' => 0, 'skipped' => []],
            'documents' => ['created' => 0, 'skipped' => []],
        ];

        $runner = function () use ($artifacts, $sourceAssignments, $targetAssignments, $partialMap, $sourceCycle, $targetCycle, $teacher, $replace, &$summary) {
            foreach ($targetAssignments as $target) {
                $sourcesForSubject = $sourceAssignments->get((int) $target->subject_id, collect());

                if ($sourcesForSubject->isEmpty()) {
                    foreach ($artifacts as $artifact) {
                        $summary[$artifact]['skipped'][] = $this->assignmentLabel($target).' sin asignacion origen equivalente';
                    }
                    continue;
                }

                if ($artifacts->contains('rubrics')) {
                    $this->cloneRubrics($sourcesForSubject, $target, $partialMap, $replace, $summary);
                }

                if ($artifacts->contains('plans')) {
                    $this->clonePlan($sourcesForSubject, $target, $sourceCycle, $targetCycle, $replace, $summary);
                }

                if ($artifacts->contains('exams')) {
                    $this->cloneExams($sourcesForSubject, $target, $sourceCycle, $targetCycle, $partialMap, $teacher, $replace, $summary);
                }

                if ($artifacts->contains('documents')) {
                    $this->cloneDocuments($sourcesForSubject, $target, $replace, $summary);
                }
            }
        };

        if ($dryRun) {
            DB::beginTransaction();
            try {
                $runner();
            } finally {
                DB::rollBack();
            }

            return $summary;
        }

        DB::transaction($runner);

        return $summary;
    }

    private function assignmentsForCycle(SchoolCycle $cycle, Teacher $teacher): Collection
    {
        return TeachingAssignment::query()
            ->with(['subject', 'group', 'schoolCycleGroup.schoolCycle'])
            ->where('teacher_id', $teacher->id)
            ->where('is_active', true)
            ->whereHas('schoolCycleGroup', fn ($query) => $query->where('school_cycle_id', $cycle->id)->where('is_active', true))
            ->orderBy('subject_id')
            ->orderBy('group_id')
            ->get();
    }

    private function partialMap(SchoolCycle $sourceCycle, SchoolCycle $targetCycle): Collection
    {
        $targetPartials = $targetCycle->partials()->get();

        return $sourceCycle->partials()->get()
            ->mapWithKeys(function (CyclePartial $sourcePartial) use ($targetPartials) {
                $target = $targetPartials->firstWhere('sort_order', $sourcePartial->sort_order)
                    ?: $targetPartials->firstWhere('code', $sourcePartial->code)
                    ?: $targetPartials->firstWhere('name', $sourcePartial->name);

                return $target ? [(int) $sourcePartial->id => $target] : [];
            });
    }

    private function cloneRubrics(Collection $sources, TeachingAssignment $target, Collection $partialMap, bool $replace, array &$summary): void
    {
        foreach ($partialMap as $sourcePartialId => $targetPartial) {
            $source = $sources->first(fn (TeachingAssignment $assignment) => $assignment->evaluationCriteria()
                ->where('cycle_partial_id', (int) $sourcePartialId)
                ->exists());

            if (! $source) {
                continue;
            }

            $targetQuery = $target->evaluationCriteria()->where('cycle_partial_id', $targetPartial->id);
            if ($targetQuery->exists()) {
                if (! $replace) {
                    $summary['rubrics']['skipped'][] = $this->assignmentLabel($target).' '.$targetPartial->name.' ya tiene rubros';
                    continue;
                }
                $targetQuery->delete();
            }

            $source->evaluationCriteria()
                ->where('cycle_partial_id', (int) $sourcePartialId)
                ->orderBy('id')
                ->get()
                ->each(function (EvaluationCriterion $criterion) use ($target, $targetPartial, &$summary) {
                    $target->evaluationCriteria()->create([
                        'cycle_partial_id' => $targetPartial->id,
                        'name' => $criterion->name,
                        'percentage' => $criterion->percentage,
                    ]);
                    $summary['rubrics']['created']++;
                });
        }
    }

    private function clonePlan(Collection $sources, TeachingAssignment $target, SchoolCycle $sourceCycle, SchoolCycle $targetCycle, bool $replace, array &$summary): void
    {
        $sourcePlan = DidacticPlan::query()
            ->whereIn('teaching_assignment_id', $sources->pluck('id')->all())
            ->where('school_cycle_id', $sourceCycle->id)
            ->with('items')
            ->orderByDesc('updated_at')
            ->first();

        if (! $sourcePlan) {
            $summary['plans']['skipped'][] = $this->assignmentLabel($target).' sin planeacion origen';
            return;
        }

        $existing = $target->didacticPlans()->where('school_cycle_id', $targetCycle->id);
        if ($existing->exists()) {
            if (! $replace) {
                $summary['plans']['skipped'][] = $this->assignmentLabel($target).' ya tiene planeacion';
                return;
            }
            $existing->delete();
        }

        $sessions = $this->targetSessions($target, $targetCycle);
        if ($sessions->isEmpty()) {
            $summary['plans']['skipped'][] = $this->assignmentLabel($target).' sin sesiones para recalendizar';
            return;
        }

        $sourceItems = $sourcePlan->items->sortBy('position')->values();
        if ($sourceItems->isEmpty()) {
            $summary['plans']['skipped'][] = $this->assignmentLabel($target).' planeacion origen sin sesiones';
            return;
        }

        $newPlan = $target->didacticPlans()->create([
            'school_cycle_id' => $targetCycle->id,
            'academic_period_id' => $targetCycle->partials()->whereNotNull('academic_period_id')->orderBy('sort_order')->value('academic_period_id'),
            'temario_unit_point_id' => $sourcePlan->temario_unit_point_id,
            'title' => 'Planeacion '.($target->subject->name ?? 'Materia').' - Grupo '.($target->group->name ?? ''),
            'unam_incorporation_key' => $sourcePlan->unam_incorporation_key,
            'teacher_dgire_file' => $sourcePlan->teacher_dgire_file,
            'technical_review_date' => $sourcePlan->technical_review_date,
            'subject_character' => $sourcePlan->subject_character,
            'subject_key' => $sourcePlan->subject_key,
            'total_annual_hours' => $sourcePlan->total_annual_hours,
            'field_training' => $sourcePlan->field_training,
            'objective' => $sourcePlan->objective,
            'evaluation_instruments' => $sourcePlan->evaluation_instruments,
            'general_resources' => $sourcePlan->general_resources,
            'bibliography' => $sourcePlan->bibliography,
            'complementary_bibliography' => $sourcePlan->complementary_bibliography,
            'start_date' => $sessions->first()['date'] ?? null,
            'end_date' => $sessions->last()['date'] ?? null,
            'notes' => $sourcePlan->notes,
            'dgire_metadata' => $sourcePlan->dgire_metadata,
            'is_active' => (bool) $sourcePlan->is_active,
        ]);

        $sessions->values()->each(function (array $session, int $index) use ($sourceItems, $newPlan) {
            $sourceIndex = min((int) floor($index * $sourceItems->count() / max(1, $session['total_sessions'])), $sourceItems->count() - 1);
            $sourceItem = $sourceItems->get($sourceIndex);

            $newPlan->items()->create([
                'position' => $index + 1,
                'field_training_point_id' => $sourceItem->field_training_point_id,
                'objective' => $sourceItem->objective,
                'temario_point_id' => $sourceItem->temario_point_id,
                'temario_subtopic_ids' => $sourceItem->temario_subtopic_ids,
                'opening' => $sourceItem->opening,
                'development' => $sourceItem->development,
                'closing' => $sourceItem->closing,
                'resources' => $sourceItem->resources,
                'evaluation' => $sourceItem->evaluation,
                'start_date' => $session['date'],
                'end_date' => $session['date'],
            ]);
        });

        $summary['plans']['created']++;
    }

    private function cloneExams(Collection $sources, TeachingAssignment $target, SchoolCycle $sourceCycle, SchoolCycle $targetCycle, Collection $partialMap, Teacher $teacher, bool $replace, array &$summary): void
    {
        $sourceExams = PaperExam::query()
            ->with('examQuestions')
            ->whereIn('teaching_assignment_id', $sources->pluck('id')->all())
            ->where('school_cycle_id', $sourceCycle->id)
            ->get();

        foreach ($sourceExams as $sourceExam) {
            $targetPartial = $partialMap->get((int) $sourceExam->cycle_partial_id);
            if (! $targetPartial) {
                $summary['exams']['skipped'][] = $this->assignmentLabel($target).' examen '.$sourceExam->title.' sin parcial destino';
                continue;
            }

            $existing = PaperExam::query()
                ->where('teaching_assignment_id', $target->id)
                ->where('school_cycle_id', $targetCycle->id)
                ->where('cycle_partial_id', $targetPartial->id);

            if ($existing->exists()) {
                if (! $replace) {
                    $summary['exams']['skipped'][] = $this->assignmentLabel($target).' '.$targetPartial->name.' ya tiene examen';
                    continue;
                }
                $existing->each(function (PaperExam $exam) {
                    $exam->examQuestions()->delete();
                    $exam->delete();
                });
            }

            $newExam = PaperExam::create([
                'created_by' => $teacher->user_id ?: $sourceExam->created_by,
                'teaching_assignment_id' => $target->id,
                'school_cycle_id' => $targetCycle->id,
                'cycle_partial_id' => $targetPartial->id,
                'title' => $sourceExam->title,
                'instructions' => $sourceExam->instructions,
                'duration_minutes' => $sourceExam->duration_minutes,
                'is_online_enabled' => false,
                'online_available_from' => null,
                'online_available_until' => null,
                'online_max_attempts' => $sourceExam->online_max_attempts,
                'online_show_result' => $sourceExam->online_show_result,
                'is_active' => (bool) $sourceExam->is_active,
            ]);

            foreach ($sourceExam->examQuestions as $examQuestion) {
                $newExam->examQuestions()->create([
                    'question_id' => $examQuestion->question_id,
                    'sort_order' => $examQuestion->sort_order,
                    'points_override' => $examQuestion->points_override,
                ]);
            }

            $summary['exams']['created']++;
        }
    }

    private function cloneDocuments(Collection $sources, TeachingAssignment $target, bool $replace, array &$summary): void
    {
        $targetItems = TeacherDocumentRequestItem::query()
            ->where('teaching_assignment_id', $target->id)
            ->get()
            ->keyBy('document_type');

        if ($targetItems->isEmpty()) {
            $summary['documents']['skipped'][] = $this->assignmentLabel($target).' sin items destino';
            return;
        }

        $sourceItems = TeacherDocumentRequestItem::query()
            ->with('editableContent')
            ->whereIn('teaching_assignment_id', $sources->pluck('id')->all())
            ->whereHas('editableContent')
            ->get()
            ->unique('document_type');

        foreach ($sourceItems as $sourceItem) {
            $targetItem = $targetItems->get($sourceItem->document_type);
            if (! $targetItem || ! $sourceItem->editableContent) {
                continue;
            }

            if ($targetItem->editableContent && ! $replace) {
                $summary['documents']['skipped'][] = $this->assignmentLabel($target).' '.$sourceItem->document_type.' ya tiene contenido';
                continue;
            }

            TeacherDocumentContent::updateOrCreate(
                ['item_id' => $targetItem->id],
                [
                    'teacher_id' => $sourceItem->editableContent->teacher_id,
                    'updated_by' => $sourceItem->editableContent->updated_by,
                    'title' => $sourceItem->editableContent->title,
                    'content_html' => $sourceItem->editableContent->content_html,
                    'submitted_at' => $sourceItem->editableContent->submitted_at,
                ]
            );

            $summary['documents']['created']++;
        }
    }

    private function targetSessions(TeachingAssignment $target, SchoolCycle $cycle): Collection
    {
        app(AcademicSessionGeneratorService::class)->generateForAssignment($target);

        $periodIds = $cycle->partials()
            ->whereNotNull('academic_period_id')
            ->pluck('academic_period_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return AcademicSession::query()
            ->where('teaching_assignment_id', $target->id)
            ->whereIn('academic_period_id', $periodIds)
            ->where('is_cancelled', false)
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get()
            ->map(fn (AcademicSession $session) => [
                'date' => $session->session_date->toDateString(),
                'total_sessions' => 1,
            ])
            ->values()
            ->tap(function (Collection $sessions) {
                $total = max(1, $sessions->count());
                $sessions->transform(fn (array $session) => array_merge($session, ['total_sessions' => $total]));
            });
    }

    private function assignmentLabel(TeachingAssignment $assignment): string
    {
        return trim(($assignment->subject->name ?? 'Materia').' grupo '.($assignment->group->name ?? ('ID '.$assignment->id)));
    }
}
