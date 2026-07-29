<?php

namespace App\Http\Controllers;

use App\Models\PaperExam;
use App\Models\PaperExamAttempt;
use App\Models\Question;
use App\Models\QuestionBank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Services\CurrentSchoolCycle;

class TeacherPaperExamController extends Controller
{
    private const QUESTION_TYPE_ORDER = [
        'multiple_choice',
        'matching',
        'fill_blank',
        'open',
    ];

    public function index(Request $request)
    {
        $teacher = auth()->user()->teacher;
        abort_if(! $teacher, 403);

        $selectedAssignmentId = (int) $request->query('assignment_id', 0);
        $selectedSubjectId = (int) $request->query('subject_id', 0);
        $selectedPartialId = (int) $request->query('cycle_partial_id', 0);
        $activeCampusId = $this->activeCampusId();
        $activeCycle = $this->activeCycle();

        $exams = PaperExam::query()
            ->with(['assignment.group', 'assignment.subject', 'partial', 'schoolCycle'])
            ->withCount('examQuestions')
            ->when(
                $activeCycle,
                fn ($q) => $q->where('school_cycle_id', $activeCycle->id),
                fn ($q) => $q->whereRaw('1 = 0')
            )
            ->whereHas('assignment', fn ($q) => $this->applyActiveTeacherAssignmentScope($q, (int) $teacher->id, $activeCampusId, $activeCycle?->id))
            ->when($selectedAssignmentId > 0, fn ($q) => $q->where('teaching_assignment_id', $selectedAssignmentId))
            ->when($selectedSubjectId > 0, fn ($q) => $q->whereHas('assignment', fn ($sq) => $sq->where('subject_id', $selectedSubjectId)))
            ->when($selectedPartialId > 0, fn ($q) => $q->where('cycle_partial_id', $selectedPartialId))
            ->orderByDesc('id')
            ->get();

        $selectedAssignment = null;
        if ($selectedAssignmentId > 0) {
            $selectedAssignmentQuery = $teacher->teachingAssignments()
                ->with(['subject', 'group'])
                ->whereKey($selectedAssignmentId);

            $this->applyActiveTeacherAssignmentScope($selectedAssignmentQuery, (int) $teacher->id, $activeCampusId, $activeCycle?->id);
            $selectedAssignment = $selectedAssignmentQuery->first();
        }

        abort_if($selectedAssignmentId > 0 && ! $selectedAssignment, 403);

        $selectedFilters = [
            'subject_id' => $selectedSubjectId,
            'cycle_partial_id' => $selectedPartialId,
        ];

        return view('teacher.paper_exams.index', compact('exams', 'selectedAssignment', 'selectedFilters'));
    }

    public function show(PaperExam $paperExam)
    {
        $this->authorizeTeacherExam($paperExam);

        $paperExam->load([
            'assignment.group',
            'assignment.subject',
            'partial',
            'schoolCycle',
            'examQuestions.question.options',
            'examQuestions.question.matchingPairs',
            'examQuestions.question.fillBlanks',
            'attempts.student.user',
            'attempts.events',
        ]);

        return view('teacher.paper_exams.show', compact('paperExam'));
    }

    public function editQuestions(PaperExam $paperExam)
    {
        $this->authorizeTeacherExam($paperExam);

        $teacher = auth()->user()->teacher;

        $paperExam->load([
            'assignment.group',
            'assignment.group.level',
            'assignment.schoolCycleGroup',
            'assignment.subject',
            'partial',
            'schoolCycle',
            'examQuestions.question.bank',
        ]);

        $modalityId = $this->examModalityId($paperExam);

        $banksQuery = QuestionBank::query()
            ->with([
                'schoolCycle',
                'partial',
                'questions' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('sort_order'),
            ]);

        $this->applyReusableBankScope(
            $banksQuery,
            (int) $teacher->id,
            (int) $paperExam->assignment->subject_id,
            $modalityId
        );

        $banks = $banksQuery
            ->whereHas('questions', fn ($query) => $query->where('is_active', true))
            ->orderByRaw('CASE WHEN school_cycle_id = ? THEN 0 ELSE 1 END', [(int) $paperExam->school_cycle_id])
            ->orderByDesc('id')
            ->get();

        $selectedQuestionIds = $paperExam->examQuestions
            ->pluck('question_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $hasAttempts = $paperExam->attempts()->exists();

        return view('teacher.paper_exams.questions', compact(
            'paperExam',
            'banks',
            'selectedQuestionIds',
            'hasAttempts'
        ));
    }

    public function updateQuestions(Request $request, PaperExam $paperExam)
    {
        $this->authorizeTeacherExam($paperExam);

        if ($paperExam->attempts()->exists()) {
            throw ValidationException::withMessages([
                'question_ids' => 'No se pueden modificar las preguntas porque ya existen intentos registrados.',
            ]);
        }

        $data = $request->validate([
            'question_ids' => ['required', 'array', 'min:1'],
            'question_ids.*' => ['integer', 'exists:questions,id'],
        ]);

        $teacher = auth()->user()->teacher;
        $paperExam->load(['assignment.schoolCycleGroup', 'assignment.group.level']);
        $modalityId = $this->examModalityId($paperExam);

        $questionIds = collect($data['question_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $validQuestionIds = Question::query()
            ->whereIn('id', $questionIds)
            ->where('is_active', true)
            ->whereHas('bank', fn ($query) => $this->applyReusableBankScope(
                $query,
                (int) $teacher->id,
                (int) $paperExam->assignment->subject_id,
                $modalityId
            ))
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        if ($validQuestionIds->count() !== $questionIds->count()) {
            throw ValidationException::withMessages([
                'question_ids' => 'Solo puedes seleccionar preguntas activas de tus bancos para esta materia.',
            ]);
        }

        DB::transaction(function () use ($paperExam, $questionIds) {
            $paperExam->examQuestions()->delete();

            $questionIds->each(function ($questionId, $index) use ($paperExam) {
                $paperExam->examQuestions()->create([
                    'question_id' => $questionId,
                    'sort_order' => $index + 1,
                ]);
            });
        });

        return redirect()
            ->route('teacher.paper-exams.show', $paperExam)
            ->with('info', 'Preguntas del examen actualizadas correctamente.');
    }

    public function reviewAttempt(PaperExam $paperExam, PaperExamAttempt $attempt)
    {
        $this->authorizeTeacherExam($paperExam);
        abort_if((int) $attempt->paper_exam_id !== (int) $paperExam->id, 404);

        $paperExam->load([
            'assignment.group',
            'assignment.subject',
            'examQuestions.question.options',
            'examQuestions.question.matchingPairs',
            'examQuestions.question.fillBlanks',
        ]);

        $attempt->load([
            'student.user',
            'events',
            'answers.question.options',
            'answers.question.matchingPairs',
            'answers.question.fillBlanks',
        ]);

        return view('teacher.paper_exams.review_attempt', compact('paperExam', 'attempt'));
    }

    public function gradeAttempt(Request $request, PaperExam $paperExam, PaperExamAttempt $attempt)
    {
        $this->authorizeTeacherExam($paperExam);
        abort_if((int) $attempt->paper_exam_id !== (int) $paperExam->id, 404);
        abort_if(! in_array($attempt->status, ['submitted', 'graded', 'locked'], true), 422);

        $attempt->load(['answers.question']);

        $answersById = $attempt->answers->keyBy('id');
        $manualScores = $request->input('manual_scores', []);

        DB::transaction(function () use ($attempt, $answersById, $manualScores) {
            foreach ($manualScores as $answerId => $value) {
                $answer = $answersById->get((int) $answerId);
                if (! $answer || ! $answer->question || $answer->question->type !== 'open') {
                    continue;
                }

                $max = round((float) ($answer->max_score ?? 0), 2);
                $score = round(is_numeric($value) ? (float) $value : 0.0, 2);

                if ($score < 0) {
                    $score = 0.0;
                }

                if ($score > $max) {
                    throw ValidationException::withMessages([
                        "manual_scores.{$answerId}" => "La calificación no puede ser mayor a {$max}.",
                    ]);
                }

                $answer->update([
                    'score' => $score,
                ]);
            }

            $attempt->load('answers');
            $totalScore = (float) $attempt->answers->sum(fn ($a) => (float) ($a->score ?? 0));
            $totalMax = (float) $attempt->answers->sum(fn ($a) => (float) ($a->max_score ?? 0));

            $attempt->update([
                'status' => 'graded',
                'score' => round($totalScore, 2),
                'max_score' => round($totalMax, 2),
            ]);
        });

        return redirect()
            ->route('teacher.paper-exams.show', $paperExam)
            ->with('info', 'Calificación guardada correctamente.');
    }

    public function previewAsStudent(PaperExam $paperExam)
    {
        $this->authorizeTeacherExam($paperExam);

        $paperExam->load([
            'assignment.subject',
            'assignment.group',
            'partial',
            'examQuestions.question.options',
            'examQuestions.question.matchingPairs',
            'examQuestions.question.fillBlanks',
        ]);

        $orderedQuestions = $this->orderedPreviewQuestions($paperExam);

        $activeAttempt = new PaperExamAttempt([
            'attempt_number' => 'Prueba',
            'started_at' => now(),
            'status' => 'in_progress',
            'question_sequence' => $orderedQuestions->pluck('id')->values()->all(),
            'options_sequence' => $this->previewOptionsSequence($orderedQuestions),
        ]);

        return view('student.portal.exams.show', [
            'paperExam' => $paperExam,
            'activeAttempt' => $activeAttempt,
            'orderedQuestions' => $orderedQuestions,
            'secondsRemaining' => null,
            'savedAnswers' => [],
            'submittedAttempts' => collect(),
            'canStartAttempt' => false,
            'startBlockReason' => null,
            'isTeacherPreview' => true,
            'previewResult' => null,
        ]);
    }

    public function submitPreview(Request $request, PaperExam $paperExam)
    {
        $this->authorizeTeacherExam($paperExam);

        $paperExam->load([
            'assignment.subject',
            'assignment.group',
            'partial',
            'examQuestions.question.options',
            'examQuestions.question.matchingPairs',
            'examQuestions.question.fillBlanks',
        ]);

        return view('student.portal.exams.show', [
            'paperExam' => $paperExam,
            'activeAttempt' => null,
            'orderedQuestions' => collect(),
            'secondsRemaining' => null,
            'savedAnswers' => [],
            'submittedAttempts' => collect(),
            'canStartAttempt' => false,
            'startBlockReason' => null,
            'isTeacherPreview' => true,
            'previewResult' => $this->evaluatePreviewAnswers($paperExam, $request->input('answers', [])),
        ]);
    }

    private function previewOptionsSequence($questions): array
    {
        $sequence = [];

        foreach ($questions as $question) {
            if ($question->type === 'multiple_choice') {
                $sequence[(string) $question->id] = $question->options
                    ->pluck('id')
                    ->shuffle()
                    ->values()
                    ->all();
            }

            if ($question->type === 'matching') {
                $sequence[(string) $question->id] = $question->matchingPairs
                    ->pluck('right_text')
                    ->shuffle()
                    ->values()
                    ->all();
            }
        }

        return $sequence;
    }

    private function orderedPreviewQuestions(PaperExam $paperExam)
    {
        $questions = $paperExam->examQuestions
            ->pluck('question')
            ->filter()
            ->values();

        $ordered = collect(self::QUESTION_TYPE_ORDER)
            ->flatMap(function (string $type) use ($questions) {
                return $questions
                    ->where('type', $type)
                    ->shuffle();
            });

        $knownTypes = collect(self::QUESTION_TYPE_ORDER);
        $unknown = $questions
            ->reject(fn (Question $question) => $knownTypes->contains($question->type))
            ->shuffle();

        return $ordered
            ->merge($unknown)
            ->values();
    }

    private function evaluatePreviewAnswers(PaperExam $paperExam, array $answers): array
    {
        $totalScore = 0.0;
        $totalMax = 0.0;
        $openQuestions = 0;

        foreach ($paperExam->examQuestions as $examQuestion) {
            $question = $examQuestion->question;
            if (! $question) {
                continue;
            }

            $max = round((float) ($examQuestion->points_override ?: $question->points ?: 1), 2);
            $score = 0.0;
            $payload = $answers[$question->id] ?? [];

            if ($question->type === 'multiple_choice') {
                $selectedOptionId = (int) ($payload['option_id'] ?? 0);
                $correctOption = $question->options->firstWhere('is_correct', true);
                $score = $correctOption && (int) $correctOption->id === $selectedOptionId ? $max : 0.0;
            }

            if ($question->type === 'fill_blank') {
                $blankInputs = collect($payload['blanks'] ?? [])->map(fn ($value) => trim((string) $value))->values();
                $expected = $question->fillBlanks->sortBy('sort_order')->values();
                $matches = 0;
                $total = max(1, $expected->count());

                foreach ($expected as $index => $blank) {
                    $given = mb_strtolower((string) ($blankInputs[$index] ?? ''));
                    $expectedAnswer = mb_strtolower(trim((string) $blank->expected_answer));
                    if ($given !== '' && $given === $expectedAnswer) {
                        $matches++;
                    }
                }

                $score = round(($matches / $total) * $max, 2);
            }

            if ($question->type === 'matching') {
                $pairs = $question->matchingPairs->sortBy('sort_order')->values();
                $selected = collect($payload['pairs'] ?? []);
                $matches = 0;
                $total = max(1, $pairs->count());

                foreach ($pairs as $pair) {
                    $selectedRight = trim((string) $selected->get((string) $pair->id, ''));
                    if ($selectedRight !== '' && $selectedRight === (string) $pair->right_text) {
                        $matches++;
                    }
                }

                $score = round(($matches / $total) * $max, 2);
            }

            if ($question->type === 'open') {
                $openQuestions++;
            } else {
                $totalScore += $score;
            }

            $totalMax += $max;
        }

        return [
            'score' => round($totalScore, 2),
            'max_score' => round($totalMax, 2),
            'base_ten' => $totalMax > 0 ? round(($totalScore / $totalMax) * 10, 1) : null,
            'open_questions' => $openQuestions,
        ];
    }

    protected function authorizeTeacherExam(PaperExam $paperExam): void
    {
        $teacher = auth()->user()->teacher;
        abort_if(! $teacher, 403);
        abort_if(! $paperExam->assignment || (int) $paperExam->assignment->teacher_id !== (int) $teacher->id, 403);
        abort_if(! $this->teacherExamIsInActiveContext($paperExam, (int) $teacher->id), 403);
    }

    private function teacherExamIsInActiveContext(PaperExam $paperExam, int $teacherId): bool
    {
        $paperExam->loadMissing('schoolCycle');
        if (! $paperExam->schoolCycle || ! $paperExam->schoolCycle->is_active) {
            return false;
        }

        return PaperExam::query()
            ->whereKey($paperExam->id)
            ->whereHas('assignment', fn ($query) => $this->applyActiveTeacherAssignmentScope($query, $teacherId, $this->activeCampusId(), $this->activeCycle()?->id))
            ->exists();
    }

    private function applyActiveTeacherAssignmentScope($query, int $teacherId, int $activeCampusId, ?int $activeCycleId): void
    {
        $query->where('teacher_id', $teacherId)
            ->where('is_active', true)
            ->whereHas('subject', fn ($subject) => $subject->where('is_active', true))
            ->whereHas('schedules', function ($schedule) use ($activeCycleId) {
                $schedule->where('is_active', true)
                    ->when($activeCycleId, fn ($q) => $q->where('school_cycle_id', $activeCycleId))
                    ->whereHas('schoolCycle', fn ($cycle) => $cycle->where('is_active', true));
            })
            ->whereHas('schoolCycleGroup', function ($cycleGroup) use ($activeCampusId, $activeCycleId) {
                $cycleGroup->where('is_active', true)
                    ->when($activeCycleId, fn ($q) => $q->where('school_cycle_id', $activeCycleId))
                    ->whereHas('schoolCycle', fn ($cycle) => $cycle->where('is_active', true))
                    ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId));
            });
    }

    private function activeCampusId(): int
    {
        $activeCampusId = (int) session('active_campus_id', 0);
        if ($activeCampusId > 0) {
            return $activeCampusId;
        }

        $user = auth()->user();
        $activeCampusId = (int) ($user?->default_campus_id ?? 0);
        if ($activeCampusId <= 0 && $user) {
            $activeCampusId = (int) $user->campuses()->orderBy('name')->value('campuses.id');
        }

        if ($activeCampusId > 0) {
            session(['active_campus_id' => $activeCampusId]);
        }

        return $activeCampusId;
    }

    private function activeCycle(): ?\App\Models\SchoolCycle
    {
        $activeCampusId = $this->activeCampusId();

        return app(CurrentSchoolCycle::class)->get(auth()->user(), $activeCampusId);
    }

    private function examModalityId(PaperExam $paperExam): int
    {
        return (int) (
            $paperExam->assignment?->schoolCycleGroup?->modality_id
            ?: $paperExam->assignment?->group?->level?->modality_id
            ?: 0
        );
    }

    private function applyReusableBankScope($query, int $teacherId, int $subjectId, int $modalityId): void
    {
        $query
            ->where('teacher_id', $teacherId)
            ->where('subject_id', $subjectId)
            ->where('is_active', true)
            ->whereNotNull('school_cycle_id')
            ->whereHas('schoolCycle', function ($cycleQuery) use ($modalityId) {
                $cycleQuery->where(function ($modalityQuery) use ($modalityId) {
                    $modalityQuery->where('modality_id', $modalityId)
                        ->orWhereHas('modalities', fn ($modalities) => $modalities->where('modalities.id', $modalityId));
                });
            });
    }
}
