<?php

namespace App\Http\Controllers;

use App\Models\Practice;
use App\Models\PracticeSubmission;
use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Models\Grade;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Notifications\PracticePublishedNotification;
use App\Notifications\PracticeReviewedNotification;
use App\Services\PracticeActivityService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PracticeController extends Controller
{
    public function index(TeachingAssignment $assignment)
    {
        $this->authorizeAssignment($assignment);

        $practices = $assignment->practices()
            ->with('activity.evaluationCriterion')
            ->withCount([
                'submissions as submitted_submissions_count' => fn ($query) => $query->whereIn('status', ['submitted', 'reviewed']),
                'submissions as reviewed_submissions_count' => fn ($query) => $query->where('status', 'reviewed'),
            ])
            ->orderBy('number')
            ->get();

        return view('practices.index', compact('assignment', 'practices'));
    }

    public function create(TeachingAssignment $assignment)
    {
        $this->authorizeAssignment($assignment);

        return view('practices.create', [
            'assignment' => $assignment,
            'kindLabels' => Practice::KIND_LABELS,
            'evaluationCriteria' => $this->evaluationCriteriaFor($assignment),
        ]);
    }

    public function store(Request $request, TeachingAssignment $assignment, PracticeActivityService $service)
    {
        $this->authorizeAssignment($assignment);

        $practice = $service->createForAssignment($assignment, $this->validatedData($request, $assignment));
        $this->notifyStudentsAbout($practice);

        return redirect()
            ->route('practices.index', $assignment)
            ->with('info', 'Entregable creado correctamente');
    }

    public function edit(Practice $practice)
    {
        $assignment = $practice->teachingAssignment;
        $this->authorizeAssignment($assignment);
        $this->ensureActivityFor($practice);

        return view('practices.edit', [
            'practice' => $practice,
            'assignment' => $assignment,
            'kindLabels' => Practice::KIND_LABELS,
            'evaluationCriteria' => $this->evaluationCriteriaFor($assignment),
        ]);
    }

    public function update(Request $request, Practice $practice)
    {
        $assignment = $practice->teachingAssignment;
        $this->authorizeAssignment($assignment);

        $data = $this->validatedData($request, $assignment, $practice);
        $practiceData = $data;
        unset($practiceData['evaluation_criterion_id']);

        $practice->update($practiceData);

        $activity = $this->ensureActivityFor($practice, (int) $data['evaluation_criterion_id']);
        if ($activity) {
            $criterion = $this->evaluationCriteriaFor($assignment)->firstWhere('id', (int) $data['evaluation_criterion_id']);
            $period = $criterion?->cyclePartial?->academicPeriod ?: $activity->academicPeriod;

            $activity->update([
                'title' => $practice->kind_label . ' ' . $practice->number . ': ' . $practice->title,
                'description' => $practice->instructions,
                'due_date' => $practice->due_date,
                'evaluation_criterion_id' => $criterion?->id ?? $activity->evaluation_criterion_id,
                'academic_period_id' => $period?->id ?? $activity->academic_period_id,
            ]);
        }

        return redirect()
            ->route('practices.index', $assignment)
            ->with('info', 'Entregable actualizado');
    }

    public function destroy(Practice $practice)
    {
        $assignment = $practice->teachingAssignment;
        $this->authorizeAssignment($assignment);

        $practice->delete();

        return redirect()
            ->route('practices.index', $assignment)
            ->with('info', 'Entregable eliminado');
    }

    public function submissions(Practice $practice)
    {
        $assignment = $practice->teachingAssignment;
        $this->authorizeAssignment($assignment);

        $submissions = $practice->submissions()
            ->with(['team.students.user', 'submittedBy.student.user', 'attachments'])
            ->orderByDesc('submitted_at')
            ->get();

        $students = $this->studentsForAssignment($assignment);
        $submissionsByStudent = collect();
        foreach ($submissions as $submission) {
            foreach ($submission->team->students as $student) {
                $submissionsByStudent->put($student->id, $submission);
            }
        }

        $rosterRows = $students
            ->map(fn (Student $student) => [
                'student' => $student,
                'submission' => $submissionsByStudent->get($student->id),
            ])
            ->sortBy(fn (array $row) => $row['student']->user->name ?? '')
            ->values();

        return view('practices.submissions', compact('practice', 'assignment', 'submissions', 'rosterRows'));
    }

    public function submissionReport(PracticeSubmission $submission)
    {
        $submission->load(['practice.teachingAssignment.subject', 'practice.teachingAssignment.teacher.user', 'team.students.user', 'submittedBy.student.user', 'reviewedBy', 'attachments']);
        $practice = $submission->practice;
        $assignment = $practice->teachingAssignment;
        $this->authorizeAssignment($assignment);
        $this->ensureActivityFor($practice);
        $practice->loadMissing('activity.evaluationCriterion');

        $student = $this->studentForSubmission($submission);
        $team = $submission->team;

        return view('practices.submission_report', compact('practice', 'submission', 'team', 'student', 'assignment'));
    }

    public function review(PracticeSubmission $submission)
    {
        $submission->load([
            'practice.activity.evaluationCriterion',
            'practice.teachingAssignment.subject',
            'practice.teachingAssignment.teacher.user',
            'team.students.user',
            'submittedBy.student.user',
            'reviewedBy',
            'attachments',
        ]);

        $practice = $submission->practice;
        $assignment = $practice->teachingAssignment;
        $this->authorizeAssignment($assignment);
        $this->ensureActivityFor($practice);
        $practice->loadMissing('activity.evaluationCriterion');

        $student = $this->studentForSubmission($submission);
        $team = $submission->team;
        $grade = $this->gradeForSubmission($submission);

        return view('practices.review', compact('practice', 'submission', 'team', 'student', 'assignment', 'grade'));
    }

    public function storeReview(Request $request, PracticeSubmission $submission)
    {
        $submission->load(['practice.activity', 'practice.teachingAssignment', 'team.students']);
        $practice = $submission->practice;
        $assignment = $practice->teachingAssignment;
        $this->authorizeAssignment($assignment);
        $activity = $this->ensureActivityFor($practice);

        abort_unless($activity, 422, 'Este entregable no tiene actividad vinculada.');
        abort_unless(
            in_array($submission->status, ['submitted', 'reviewed'], true),
            422,
            'Solo puedes revisar entregas enviadas.'
        );

        $data = $request->validate([
            'score' => ['required', 'numeric', 'min:0', 'max:' . $activity->max_score],
            'teacher_corrections' => ['nullable', 'string'],
            'teacher_comments' => ['nullable', 'string'],
            'teacher_suggestions' => ['nullable', 'string'],
            'allow_resubmission' => ['nullable', 'boolean'],
            'resubmission_due_date' => ['nullable', 'required_if:allow_resubmission,1', 'date', 'after_or_equal:today'],
            'resubmission_note' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($submission, $activity, $data) {
            $allowResubmission = (bool) ($data['allow_resubmission'] ?? false);

            $studentIds = $submission->team->students
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            foreach ($studentIds as $studentId) {
                Grade::updateOrCreate(
                    [
                        'activity_id' => $activity->id,
                        'student_id' => $studentId,
                    ],
                    [
                        'score' => $data['score'],
                        'comments' => $data['teacher_comments'] ?? null,
                    ]
                );
            }

            $submission->update([
                'score' => $data['score'],
                'teacher_corrections' => $data['teacher_corrections'] ?? null,
                'teacher_comments' => $data['teacher_comments'] ?? null,
                'teacher_suggestions' => $data['teacher_suggestions'] ?? null,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'status' => 'reviewed',
                'is_resubmission_allowed' => $allowResubmission,
                'resubmission_due_date' => $allowResubmission ? $data['resubmission_due_date'] : null,
                'resubmission_note' => $allowResubmission ? ($data['resubmission_note'] ?? null) : null,
                'resubmission_requested_at' => $allowResubmission ? now() : null,
                'resubmission_requested_by' => $allowResubmission ? auth()->id() : null,
            ]);
        });

        $submission->loadMissing('practice', 'team.students.user');
        $submission->team->students
            ->pluck('user')
            ->filter()
            ->each(fn ($user) => $user->notify(new PracticeReviewedNotification($submission)));

        return redirect()
            ->route('practices.submissions', $practice)
            ->with('info', 'Entrega revisada y calificada correctamente.');
    }

    public function submissionPdf(PracticeSubmission $submission)
    {
        $submission->load(['practice.teachingAssignment.subject', 'practice.teachingAssignment.teacher.user', 'team.students.user', 'submittedBy.student.user', 'reviewedBy', 'attachments']);
        $practice = $submission->practice;
        $assignment = $practice->teachingAssignment;
        $this->authorizeAssignment($assignment);

        $student = $this->studentForSubmission($submission);
        $team = $submission->team;
        $filename = Str::slug($practice->kind_label . '-' . $practice->title . '-' . ($student?->user?->name ?? $team->name)) . '.pdf';

        return Pdf::loadView('student.practices.report_pdf', compact('practice', 'submission', 'team', 'student'))
            ->setPaper('letter')
            ->download($filename);
    }

    private function authorizeAssignment(TeachingAssignment $assignment): void
    {
        abort_unless($assignment->teacher_id === auth()->user()->teacher?->id, 403);
    }

    private function studentForSubmission(PracticeSubmission $submission): ?Student
    {
        return $submission->submittedBy?->student
            ?? $submission->team?->students?->first();
    }

    private function validatedData(Request $request, TeachingAssignment $assignment, ?Practice $practice = null): array
    {
        $data = $request->validate([
            'number' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('practices', 'number')
                    ->where('teaching_assignment_id', $assignment->id)
                    ->ignore($practice?->id),
            ],
            'kind' => ['required', Rule::in(array_keys(Practice::KIND_LABELS))],
            'evaluation_criterion_id' => [
                'required',
                Rule::exists('evaluation_criteria', 'id')
                    ->where('teaching_assignment_id', $assignment->id),
            ],
            'title' => 'required|string|max:255',
            'introduction' => 'nullable|string',
            'instructions' => 'nullable|string',
            'procedure' => 'nullable|string',
            'realization_date' => 'nullable|date',
            'due_date' => 'required|date',
            'questionnaire' => 'nullable|string',
            'custom_submission_fields' => 'nullable|string',
        ]);

        $data['questionnaire'] = $this->normalizeQuestionnaire($data['questionnaire'] ?? null);
        $data['custom_submission_fields'] = $this->normalizeDeliveryFields($data['custom_submission_fields'] ?? null);
        $data['submission_fields'] = collect($data['custom_submission_fields'])
            ->pluck('id')
            ->values()
            ->all();

        if (empty($data['custom_submission_fields'])) {
            validator([], [])->after(function ($validator) {
                $validator->errors()->add('custom_submission_fields', 'Agrega al menos un campo por entregar.');
            })->validate();
        }

        return $data;
    }

    private function normalizeDeliveryFields(?string $fields): array
    {
        if (empty($fields)) {
            return [];
        }

        $decodedFields = json_decode($fields, true);

        if (! is_array($decodedFields)) {
            return [];
        }

        $usedIds = [];

        return collect($decodedFields)
            ->filter(fn ($field) => is_array($field))
            ->values()
            ->map(function (array $field, int $index) use (&$usedIds) {
                $label = trim((string) ($field['label'] ?? ''));
                $id = trim((string) ($field['id'] ?? ''));

                if ($id === '' || isset($usedIds[$id])) {
                    $id = 'field_' . ($index + 1) . '_' . Str::random(6);
                }

                $usedIds[$id] = true;

                return [
                    'id' => $id,
                    'label' => $label,
                    'required' => (bool) ($field['required'] ?? false),
                    'legacy_field' => ! empty($field['legacy_field']) ? (string) $field['legacy_field'] : null,
                ];
            })
            ->filter(fn ($field) => $field['label'] !== '')
            ->values()
            ->all();
    }

    private function normalizeQuestionnaire(?string $questionnaire): ?array
    {
        if (empty($questionnaire)) {
            return null;
        }

        $questions = json_decode($questionnaire, true);

        if (! is_array($questions)) {
            return null;
        }

        $usedIds = [];

        return collect($questions)
            ->filter(fn ($question) => is_array($question))
            ->values()
            ->map(function (array $question, int $index) use (&$usedIds) {
                $baseId = trim((string) ($question['id'] ?? ''));

                if ($baseId === '' || isset($usedIds[$baseId])) {
                    $baseId = 'q' . ($index + 1) . '_' . Str::random(6);
                }

                $usedIds[$baseId] = true;

                return [
                    'id' => $baseId,
                    'type' => in_array(($question['type'] ?? 'text'), ['text', 'multiple_choice', 'boolean'], true)
                        ? $question['type']
                        : 'text',
                    'question' => trim((string) ($question['question'] ?? '')),
                    'required' => (bool) ($question['required'] ?? false),
                    'options' => collect($question['options'] ?? [])
                        ->filter(fn ($option) => trim((string) $option) !== '')
                        ->values()
                        ->all(),
                ];
            })
            ->filter(fn ($question) => $question['question'] !== '')
            ->values()
            ->all();
    }

    private function evaluationCriteriaFor(TeachingAssignment $assignment)
    {
        return $assignment->evaluationCriteria()
            ->with('cyclePartial.academicPeriod')
            ->orderBy('cycle_partial_id')
            ->orderBy('name')
            ->get()
            ->reject(fn ($criterion) => $criterion->isAttendance())
            ->values();
    }

    private function ensureActivityFor(Practice $practice, ?int $criterionId = null): ?Activity
    {
        $practice->loadMissing('activity', 'teachingAssignment.group.level');

        if ($practice->activity) {
            return $practice->activity;
        }

        $assignment = $practice->teachingAssignment;
        $title = $practice->kind_label . ' ' . $practice->number . ': ' . $practice->title;

        $activity = Activity::query()
            ->where('teaching_assignment_id', $assignment->id)
            ->where('title', $title)
            ->orderByDesc('id')
            ->first();

        if (! $activity) {
            $criterion = $criterionId
                ? $this->evaluationCriteriaFor($assignment)->firstWhere('id', $criterionId)
                : $this->evaluationCriteriaFor($assignment)->first();

            if (! $criterion) {
                return null;
            }

            $period = $criterion->cyclePartial?->academicPeriod
                ?: AcademicPeriod::query()
                    ->where('modality_id', $assignment->group->level->modality_id)
                    ->where('is_active', true)
                    ->first();

            if (! $period) {
                return null;
            }

            $activity = Activity::create([
                'teaching_assignment_id' => $assignment->id,
                'evaluation_criterion_id' => $criterion->id,
                'academic_period_id' => $period->id,
                'title' => $title,
                'description' => $practice->instructions,
                'max_score' => 10,
                'due_date' => $practice->due_date,
            ]);
        }

        $practice->forceFill(['activity_id' => $activity->id])->save();
        $practice->setRelation('activity', $activity);

        return $activity;
    }

    private function gradeForSubmission(PracticeSubmission $submission): ?Grade
    {
        $activity = $submission->practice?->activity;
        $studentId = $this->studentForSubmission($submission)?->id;

        if (! $activity || ! $studentId) {
            return null;
        }

        return Grade::query()
            ->where('activity_id', $activity->id)
            ->where('student_id', $studentId)
            ->first();
    }

    private function notifyStudentsAbout(Practice $practice): void
    {
        $practice->loadMissing('teachingAssignment.students.user', 'teachingAssignment.group');
        $assignment = $practice->teachingAssignment;
        $students = $assignment->students;

        if ($students->isEmpty() && $assignment->group) {
            $students = $assignment->group->students()->where('is_active', true)->with('user')->get();
        }

        $students
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->each(fn ($user) => $user->notify(new PracticePublishedNotification($practice)));
    }

    private function studentsForAssignment(TeachingAssignment $assignment)
    {
        $assignment->loadMissing('students.user', 'group.students.user');

        if ($assignment->students->isNotEmpty()) {
            return $assignment->students
                ->where('is_active', true)
                ->values();
        }

        return $assignment->group
            ? $assignment->group->students->where('is_active', true)->values()
            : collect();
    }
}
