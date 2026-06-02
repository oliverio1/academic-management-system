<?php

namespace App\Http\Controllers;

use App\Models\PaperExam;
use App\Models\PaperExamAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeacherPaperExamController extends Controller
{
    public function index()
    {
        $teacher = auth()->user()->teacher;
        abort_if(! $teacher, 403);

        $exams = PaperExam::query()
            ->with(['assignment.group', 'assignment.subject', 'partial', 'schoolCycle'])
            ->withCount('examQuestions')
            ->whereHas('assignment', fn ($q) => $q->where('teacher_id', $teacher->id))
            ->orderByDesc('id')
            ->get();

        return view('teacher.paper_exams.index', compact('exams'));
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
