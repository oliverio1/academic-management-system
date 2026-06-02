<?php

namespace App\Http\Controllers;

use App\Models\PaperExam;
use App\Models\PaperExamAttempt;
use App\Models\PaperExamAttemptAnswer;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentOnlineExamController extends Controller
{
    public function index()
    {
        $student = auth()->user()->student;

        $exams = PaperExam::query()
            ->with(['assignment.subject', 'assignment.group', 'partial'])
            ->withCount(['attempts as attempts_count' => fn ($q) => $q->where('student_id', $student->id)])
            ->where('is_active', true)
            ->where('is_online_enabled', true)
            ->whereHas('assignment', fn ($q) => $q->where('group_id', $student->group_id)->where('is_active', true))
            ->orderByDesc('online_available_from')
            ->orderByDesc('id')
            ->get();

        return view('student.portal.exams.index', compact('exams', 'student'));
    }

    public function show(PaperExam $paperExam)
    {
        $student = auth()->user()->student;
        $this->authorizeExam($paperExam, $student->group_id);

        $paperExam->load([
            'assignment.subject',
            'assignment.group',
            'partial',
            'examQuestions.question.options',
            'examQuestions.question.matchingPairs',
            'examQuestions.question.fillBlanks',
        ]);

        $activeAttempt = PaperExamAttempt::query()
            ->where('paper_exam_id', $paperExam->id)
            ->where('student_id', $student->id)
            ->where('status', 'in_progress')
            ->latest('id')
            ->first();

        $orderedQuestions = collect();
        $secondsRemaining = null;
        if ($activeAttempt) {
            $questionIds = collect($activeAttempt->question_sequence ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->values();

            if ($questionIds->isEmpty()) {
                $questionIds = $paperExam->examQuestions
                    ->pluck('question_id')
                    ->map(fn ($id) => (int) $id)
                    ->filter()
                    ->values();
            }

            $questionsById = $paperExam->examQuestions
                ->pluck('question')
                ->filter()
                ->keyBy('id');

            $orderedQuestions = $questionIds
                ->map(fn ($qid) => $questionsById->get($qid))
                ->filter()
                ->values();

            if ($paperExam->duration_minutes && $activeAttempt->started_at) {
                $endsAt = $activeAttempt->started_at->copy()->addMinutes((int) $paperExam->duration_minutes);
                $secondsRemaining = max(0, now()->diffInSeconds($endsAt, false));
            }
        }

        $completedAttempts = PaperExamAttempt::query()
            ->where('paper_exam_id', $paperExam->id)
            ->where('student_id', $student->id)
            ->whereIn('status', ['submitted', 'graded', 'locked'])
            ->orderByDesc('attempt_number')
            ->get();

        $now = now();
        $isWindowOpen = (! $paperExam->online_available_from || $paperExam->online_available_from <= $now)
            && (! $paperExam->online_available_until || $paperExam->online_available_until >= $now);

        $completedCount = $completedAttempts->count();
        $canStartAttempt = $isWindowOpen
            && $paperExam->is_online_enabled
            && ! $activeAttempt
            && $completedCount < (int) $paperExam->online_max_attempts;

        $startBlockReason = null;
        if (! $paperExam->is_online_enabled) {
            $startBlockReason = 'El examen no está habilitado en línea.';
        } elseif (! $isWindowOpen) {
            $startBlockReason = 'El examen está fuera de la ventana de aplicación.';
        } elseif ($activeAttempt) {
            $startBlockReason = 'Ya tienes un intento en curso para este examen.';
        } elseif ($completedCount >= (int) $paperExam->online_max_attempts) {
            $startBlockReason = 'Ya agotaste el número máximo de intentos.';
        }

        return view('student.portal.exams.show', [
            'paperExam' => $paperExam,
            'activeAttempt' => $activeAttempt,
            'orderedQuestions' => $orderedQuestions,
            'secondsRemaining' => $secondsRemaining,
            'submittedAttempts' => $completedAttempts,
            'canStartAttempt' => $canStartAttempt,
            'startBlockReason' => $startBlockReason,
        ]);
    }

    public function start(PaperExam $paperExam)
    {
        $student = auth()->user()->student;
        $this->authorizeExam($paperExam, $student->group_id);

        $now = now();
        $withinWindow = (! $paperExam->online_available_from || $paperExam->online_available_from <= $now)
            && (! $paperExam->online_available_until || $paperExam->online_available_until >= $now);

        abort_unless($paperExam->is_online_enabled && $withinWindow, 403, 'El examen no está disponible.');

        $existingProgress = PaperExamAttempt::query()
            ->where('paper_exam_id', $paperExam->id)
            ->where('student_id', $student->id)
            ->where('status', 'in_progress')
            ->first();

        if ($existingProgress) {
            return redirect()->route('student.exams.show', $paperExam);
        }

        $attemptNumber = (int) PaperExamAttempt::query()
            ->where('paper_exam_id', $paperExam->id)
            ->where('student_id', $student->id)
            ->count() + 1;

        abort_if($attemptNumber > (int) $paperExam->online_max_attempts, 403, 'Ya no tienes intentos disponibles.');

        $paperExam->load(['examQuestions.question.options']);
        $questionSequence = $paperExam->examQuestions
            ->pluck('question_id')
            ->shuffle()
            ->values()
            ->all();

        $optionsSequence = [];
        $questionMap = $paperExam->examQuestions
            ->pluck('question')
            ->filter()
            ->keyBy('id');

        foreach ($questionSequence as $questionId) {
            /** @var Question|null $question */
            $question = $questionMap->get((int) $questionId);
            if (! $question || $question->type !== 'multiple_choice') {
                continue;
            }
            $optionsSequence[(string) $questionId] = $question->options
                ->pluck('id')
                ->shuffle()
                ->values()
                ->all();
        }

        PaperExamAttempt::create([
            'paper_exam_id' => $paperExam->id,
            'student_id' => $student->id,
            'attempt_number' => $attemptNumber,
            'started_at' => now(),
            'status' => 'in_progress',
            'question_sequence' => $questionSequence,
            'options_sequence' => $optionsSequence,
        ]);

        return redirect()
            ->route('student.exams.show', $paperExam)
            ->with('info', 'Intento iniciado. Ya puedes responder el examen.');
    }

    public function submit(Request $request, PaperExam $paperExam, PaperExamAttempt $attempt)
    {
        $student = auth()->user()->student;
        $this->authorizeExam($paperExam, $student->group_id);
        abort_if((int) $attempt->paper_exam_id !== (int) $paperExam->id, 404);
        abort_if((int) $attempt->student_id !== (int) $student->id, 403);
        abort_if($attempt->status !== 'in_progress', 422, 'Este intento ya fue enviado.');

        $paperExam->load([
            'examQuestions.question.options',
            'examQuestions.question.matchingPairs',
            'examQuestions.question.fillBlanks',
        ]);

        $answers = $request->input('answers', []);
        $totalScore = 0.0;
        $totalMax = 0.0;

        DB::transaction(function () use ($paperExam, $attempt, $answers, &$totalScore, &$totalMax) {
            foreach ($paperExam->examQuestions as $examQuestion) {
                $question = $examQuestion->question;
                if (! $question) {
                    continue;
                }

                $max = round((float) ($examQuestion->points_override ?: $question->points ?: 1), 2);
                $score = 0.0;
                $isCorrect = null;
                $payload = $answers[$question->id] ?? [];

                if ($question->type === 'multiple_choice') {
                    $selectedOptionId = (int) ($payload['option_id'] ?? 0);
                    $correctOption = $question->options->firstWhere('is_correct', true);
                    $isCorrect = $correctOption ? ((int) $correctOption->id === $selectedOptionId) : null;
                    $score = $isCorrect ? $max : 0.0;
                    $payload = ['option_id' => $selectedOptionId];
                }

                if ($question->type === 'fill_blank') {
                    $blankInputs = collect($payload['blanks'] ?? [])->map(fn ($v) => trim((string) $v))->values();
                    $expected = $question->fillBlanks->sortBy('sort_order')->values();
                    $matches = 0;
                    $total = max(1, $expected->count());
                    foreach ($expected as $index => $blank) {
                        $given = mb_strtolower((string) ($blankInputs[$index] ?? ''));
                        $exp = mb_strtolower(trim((string) $blank->expected_answer));
                        if ($given !== '' && $given === $exp) {
                            $matches++;
                        }
                    }
                    $score = round(($matches / $total) * $max, 2);
                    $isCorrect = $matches === $expected->count();
                    $payload = ['blanks' => $blankInputs->all()];
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
                    $isCorrect = $matches === $pairs->count();
                    $payload = ['pairs' => $selected->all()];
                }

                if ($question->type === 'open') {
                    $payload = ['text' => trim((string) ($payload['text'] ?? ''))];
                }

                PaperExamAttemptAnswer::updateOrCreate(
                    [
                        'paper_exam_attempt_id' => $attempt->id,
                        'question_id' => $question->id,
                    ],
                    [
                        'answer_payload' => $payload,
                        'is_correct' => $isCorrect,
                        'score' => $question->type === 'open' ? null : $score,
                        'max_score' => $max,
                    ]
                );

                $totalMax += $max;
                if ($question->type !== 'open') {
                    $totalScore += $score;
                }
            }

            $attempt->update([
                'status' => 'submitted',
                'submitted_at' => now(),
                'score' => round($totalScore, 2),
                'max_score' => round($totalMax, 2),
            ]);
        });

        return redirect()
            ->route('student.exams.show', $paperExam)
            ->with('info', 'Examen enviado correctamente.');
    }

    public function lock(Request $request, PaperExam $paperExam, PaperExamAttempt $attempt)
    {
        $student = auth()->user()->student;
        $this->authorizeExam($paperExam, $student->group_id);
        abort_if((int) $attempt->paper_exam_id !== (int) $paperExam->id, 404);
        abort_if((int) $attempt->student_id !== (int) $student->id, 403);

        if ($attempt->status === 'in_progress') {
            $attempt->update([
                'status' => 'locked',
                'locked_at' => now(),
                'submitted_at' => now(),
                'lock_reason' => 'tab_switch',
            ]);
        }

        return response()->json(['ok' => true]);
    }

    protected function authorizeExam(PaperExam $paperExam, int $groupId): void
    {
        abort_unless(
            $paperExam->is_active
            && $paperExam->is_online_enabled
            && $paperExam->assignment
            && (int) $paperExam->assignment->group_id === $groupId,
            403
        );
    }
}
