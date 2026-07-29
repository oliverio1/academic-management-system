<?php

namespace App\Http\Controllers;

use App\Models\Practice;
use App\Models\PracticeSubmission;
use App\Models\PracticeSubmissionAttachment;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Team;
use App\Notifications\PracticeSubmittedNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StudentPracticeController extends Controller
{
    public function index()
    {
        $student = auth()->user()->student;

        $practices = Practice::query()
            ->whereHas('teachingAssignment', function ($query) use ($student) {
                $query->where('is_active', true)
                    ->where(function ($nested) use ($student) {
                        $nested->where('group_id', $student->group_id)
                            ->orWhereHas('students', fn ($q) => $q->where('students.id', $student->id));
                    });
            })
            ->with(['teachingAssignment.subject', 'teachingAssignment.teacher.user', 'submissions.team.students'])
            ->orderBy('due_date')
            ->orderBy('number')
            ->get();

        return view('student.practices.index', compact('practices', 'student'));
    }

    public function show(Practice $practice)
    {
        $student = auth()->user()->student;
        $this->authorizePracticeForStudent($practice, $student);

        [$submission, $team] = $this->submissionForStudent($practice, $student);
        $submission->loadMissing('attachments');

        if (! $this->canStudentEditSubmission($practice, $submission)) {
            return redirect()
                ->route('student.practices.report', $practice)
                ->with('warning', $submission->status === 'reviewed'
                    ? 'Este entregable ya fue revisado por el profesor y no puede modificarse.'
                    : 'La fecha de entrega ya pasó y el reporte no puede modificarse.'
                );
        }

        return view('student.practices.show', compact('practice', 'submission', 'team', 'student'));
    }

    public function store(Request $request, Practice $practice)
    {
        $student = auth()->user()->student;
        $this->authorizePracticeForStudent($practice, $student);

        $data = $request->validate([
            'action' => 'nullable|in:draft,submitted',
            'questionnaire_answers' => 'nullable|array',
            'theoretical_framework' => 'nullable|string',
            'objectives' => 'nullable|string',
            'hypothesis' => 'nullable|string',
            'development' => 'nullable|string',
            'results' => 'nullable|string',
            'discussion' => 'nullable|string',
            'conclusions' => 'nullable|string',
            'references' => 'nullable|string',
            'custom_field_answers' => 'nullable|array',
            'custom_field_answers.*' => 'nullable|string',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|max:10240|mimes:pdf,doc,docx,jpg,jpeg,png,webp',
            'delete_attachment_ids' => 'nullable|array',
            'delete_attachment_ids.*' => 'integer|exists:practice_submission_attachments,id',
        ]);

        $data['custom_field_answers'] = $this->validatedCustomFieldAnswers($request, $practice);
        $attachments = $request->file('attachments', []);
        $deleteAttachmentIds = collect($data['delete_attachment_ids'] ?? [])->map(fn ($id) => (int) $id)->all();
        unset($data['attachments'], $data['delete_attachment_ids']);

        $status = $data['action'] ?? 'submitted';
        unset($data['action']);

        [$submission, $team] = $this->submissionForStudent($practice, $student);

        abort_if(
            ! $this->canStudentEditSubmission($practice, $submission),
            403,
            $submission->status === 'reviewed'
                ? 'Esta entrega ya fue revisada por el profesor.'
                : 'La fecha de entrega ya pasó.'
        );

        $isResubmission = $submission->status === 'reviewed'
            && $this->isResubmissionWindowOpen($submission);

        DB::transaction(function () use ($submission, $data, $status, $attachments, $deleteAttachmentIds, $isResubmission) {
            $reviewReset = [];

            if ($isResubmission) {
                $reviewReset = [
                    'score' => null,
                    'teacher_corrections' => null,
                    'teacher_comments' => null,
                    'teacher_suggestions' => null,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'is_resubmission_allowed' => $status === 'draft',
                    'resubmission_due_date' => $status === 'draft' ? $submission->resubmission_due_date : null,
                    'resubmission_note' => $status === 'draft' ? $submission->resubmission_note : null,
                    'resubmission_requested_at' => $status === 'draft' ? $submission->resubmission_requested_at : null,
                    'resubmission_requested_by' => $status === 'draft' ? $submission->resubmission_requested_by : null,
                    'resubmission_count' => $submission->resubmission_count + ($status === 'submitted' ? 1 : 0),
                ];
            }

            $submission->update([
                ...$data,
                ...$reviewReset,
                'submitted_by' => auth()->id(),
                'submitted_at' => $status === 'submitted' ? now() : $submission->submitted_at,
                'status' => $status,
            ]);

            $this->deleteAttachments($submission, $deleteAttachmentIds);
            $this->storeAttachments($submission, $attachments);

            if ($isResubmission) {
                $this->removeGradesForSubmission($submission);
            }
        });

        if ($status === 'submitted') {
            $submission->loadMissing('practice.teachingAssignment.teacher.user', 'submittedBy');
            $submission->practice->teachingAssignment->teacher?->user?->notify(new PracticeSubmittedNotification($submission));
        }

        if ($status === 'draft') {
            return redirect()
                ->route('student.practices.show', $practice)
                ->with('success', 'Borrador guardado correctamente.');
        }

        return redirect()
            ->route('student.practices.report', $practice)
            ->with('success', 'Reporte enviado correctamente.');
    }

    public function report(Practice $practice)
    {
        $student = auth()->user()->student;
        $this->authorizePracticeForStudent($practice, $student);

        [$submission, $team] = $this->submissionForStudent($practice, $student);
        $submission->loadMissing('reviewedBy', 'attachments');
        $canCapture = $this->canStudentEditSubmission($practice, $submission);

        return view('student.practices.report', compact('practice', 'submission', 'team', 'student', 'canCapture'));
    }

    public function pdf(Practice $practice)
    {
        $student = auth()->user()->student;
        $this->authorizePracticeForStudent($practice, $student);

        [$submission, $team] = $this->submissionForStudent($practice, $student);
        $submission->loadMissing('reviewedBy', 'attachments');

        $filename = Str::slug($practice->kind_label . '-' . $practice->title . '-' . $student->user->name) . '.pdf';

        return Pdf::loadView('student.practices.report_pdf', compact('practice', 'submission', 'team', 'student'))
            ->setPaper('letter')
            ->download($filename);
    }

    private function authorizePracticeForStudent(Practice $practice, Student $student): void
    {
        $practice->loadMissing('teachingAssignment.students');

        $assignment = $practice->teachingAssignment;
        $assignedByPivot = $assignment->students->contains('id', $student->id);

        abort_unless(
            $assignment->is_active && ($assignment->group_id === $student->group_id || $assignedByPivot),
            403
        );
    }

    private function teamForStudent(Practice $practice, Student $student): Team
    {
        $assignmentId = $practice->teaching_assignment_id;

        $team = Team::query()
            ->where('teaching_assignment_id', $assignmentId)
            ->whereHas('students', fn ($q) => $q->where('students.id', $student->id))
            ->first();

        if ($team) {
            return $team;
        }

        return DB::transaction(function () use ($assignmentId, $student) {
            $team = Team::firstOrCreate(
                [
                    'teaching_assignment_id' => $assignmentId,
                    'name' => 'Individual - ' . ($student->enrollment_number ?: $student->id),
                ],
                [
                    'notes' => 'Equipo individual generado automáticamente para entregables.',
                ]
            );

            $team->students()->syncWithoutDetaching([$student->id]);

            return $team;
        });
    }

    private function submissionFor(Practice $practice, Team $team): PracticeSubmission
    {
        return PracticeSubmission::firstOrCreate(
            [
                'practice_id' => $practice->id,
                'team_id' => $team->id,
            ],
            [
                'submitted_by' => auth()->id(),
                'status' => 'draft',
            ]
        );
    }

    private function submissionForStudent(Practice $practice, Student $student): array
    {
        $existingSubmission = PracticeSubmission::query()
            ->where('practice_id', $practice->id)
            ->whereHas('team.students', fn ($query) => $query->where('students.id', $student->id))
            ->with(['team.students', 'reviewedBy'])
            ->orderByRaw("CASE status WHEN 'reviewed' THEN 0 WHEN 'submitted' THEN 1 WHEN 'draft' THEN 2 ELSE 3 END")
            ->orderByDesc('reviewed_at')
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->first();

        if ($existingSubmission) {
            return [$existingSubmission, $existingSubmission->team];
        }

        $team = $this->teamForStudent($practice, $student);

        return [$this->submissionFor($practice, $team), $team];
    }

    private function canStudentEditSubmission(Practice $practice, PracticeSubmission $submission): bool
    {
        if ($this->isResubmissionWindowOpen($submission)) {
            return true;
        }

        if ($submission->status === 'reviewed') {
            return false;
        }

        return ! $this->isPracticePastDue($practice);
    }

    private function isPracticePastDue(Practice $practice): bool
    {
        return $practice->due_date
            ? $practice->due_date->copy()->endOfDay()->isPast()
            : false;
    }

    private function isResubmissionWindowOpen(PracticeSubmission $submission): bool
    {
        return $submission->is_resubmission_allowed
            && $submission->resubmission_due_date
            && ! $submission->resubmission_due_date->copy()->endOfDay()->isPast();
    }

    private function removeGradesForSubmission(PracticeSubmission $submission): void
    {
        $submission->loadMissing('practice.activity', 'team.students');
        $activity = $submission->practice?->activity;

        if (! $activity) {
            return;
        }

        Grade::query()
            ->where('activity_id', $activity->id)
            ->whereIn('student_id', $submission->team->students->pluck('id'))
            ->delete();
    }

    private function storeAttachments(PracticeSubmission $submission, array $attachments): void
    {
        foreach ($attachments as $file) {
            if (! $file) {
                continue;
            }

            $path = $file->store('practice-submissions/' . $submission->id, 'local');

            PracticeSubmissionAttachment::create([
                'practice_submission_id' => $submission->id,
                'uploaded_by' => auth()->id(),
                'disk' => 'local',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize() ?: 0,
            ]);
        }
    }

    private function deleteAttachments(PracticeSubmission $submission, array $attachmentIds): void
    {
        if (empty($attachmentIds)) {
            return;
        }

        $submission->attachments()
            ->whereIn('id', $attachmentIds)
            ->get()
            ->each(function (PracticeSubmissionAttachment $attachment) {
                Storage::disk($attachment->disk)->delete($attachment->path);
                $attachment->delete();
            });
    }

    private function validatedCustomFieldAnswers(Request $request, Practice $practice): array
    {
        $answers = $request->input('custom_field_answers', []);

        if (! is_array($answers)) {
            $answers = [];
        }

        $cleanAnswers = [];
        $validator = validator([], []);

        foreach ($practice->delivery_field_definitions as $field) {
            $fieldId = (string) $field['id'];
            $value = trim((string) ($answers[$fieldId] ?? ''));

            if (($field['required'] ?? false) && $value === '') {
                $validator->after(function ($validator) use ($field) {
                    $validator->errors()->add('custom_field_answers.' . $field['id'], 'El campo "' . $field['label'] . '" es obligatorio.');
                });
            }

            $cleanAnswers[$fieldId] = $value;
        }

        $validator->validate();

        return $cleanAnswers;
    }
}
