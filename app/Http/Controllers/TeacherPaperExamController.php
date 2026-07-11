<?php

namespace App\Http\Controllers;

use App\Models\PaperExam;
use App\Models\PaperExamAttempt;
use App\Models\Question;
use App\Models\QuestionBank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeacherPaperExamController extends Controller
{
    public function index(Request $request)
    {
        $teacher = auth()->user()->teacher;
        abort_if(! $teacher, 403);

        $selectedAssignmentId = (int) $request->query('assignment_id', 0);
        $selectedSubjectId = (int) $request->query('subject_id', 0);
        $selectedPartialId = (int) $request->query('cycle_partial_id', 0);

        $exams = PaperExam::query()
            ->with(['assignment.group', 'assignment.subject', 'partial', 'schoolCycle'])
            ->withCount('examQuestions')
            ->whereHas('assignment', fn ($q) => $q->where('teacher_id', $teacher->id))
            ->when($selectedAssignmentId > 0, fn ($q) => $q->where('teaching_assignment_id', $selectedAssignmentId))
            ->when($selectedSubjectId > 0, fn ($q) => $q->whereHas('assignment', fn ($sq) => $sq->where('subject_id', $selectedSubjectId)))
            ->when($selectedPartialId > 0, fn ($q) => $q->where('cycle_partial_id', $selectedPartialId))
            ->orderByDesc('id')
            ->get();

        $selectedAssignment = $selectedAssignmentId > 0
            ? $teacher->teachingAssignments()
                ->with(['subject', 'group'])
                ->whereKey($selectedAssignmentId)
                ->first()
            : null;

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
        ]);

        return view('teacher.paper_exams.show', compact('paperExam'));
    }

    public function editQuestions(PaperExam $paperExam)
    {
        $this->authorizeTeacherExam($paperExam);

        $teacher = auth()->user()->teacher;

        $paperExam->load([
            'assignment.group',
            'assignment.subject',
            'partial',
            'schoolCycle',
            'examQuestions.question.bank',
        ]);

        $banks = QuestionBank::query()
            ->with([
                'partial',
                'questions' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('sort_order'),
            ])
            ->where('teacher_id', $teacher->id)
            ->where('subject_id', $paperExam->assignment->subject_id)
            ->where('is_active', true)
            ->whereHas('questions', fn ($query) => $query->where('is_active', true))
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
        $paperExam->load('assignment');

        $questionIds = collect($data['question_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $validQuestionIds = Question::query()
            ->whereIn('id', $questionIds)
            ->where('is_active', true)
            ->whereHas('bank', function ($query) use ($teacher, $paperExam) {
                $query->where('teacher_id', $teacher->id)
                    ->where('subject_id', $paperExam->assignment->subject_id)
                    ->where('is_active', true);
            })
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
        abort_if(! in_array($attempt->status, ['submitted', 'graded'], true), 422);

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

    protected function authorizeTeacherExam(PaperExam $paperExam): void
    {
        $teacher = auth()->user()->teacher;
        abort_if(! $teacher, 403);
        abort_if(! $paperExam->assignment || (int) $paperExam->assignment->teacher_id !== (int) $teacher->id, 403);
    }
}
