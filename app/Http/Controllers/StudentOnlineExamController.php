<?php

namespace App\Http\Controllers;

use App\Models\PaperExam;
use App\Models\PaperExamAttempt;
use App\Models\PaperExamAttemptAnswer;
use App\Models\PaperExamAttemptEvent;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class StudentOnlineExamController extends Controller
{
    private const QUESTION_TYPE_ORDER = [
        'multiple_choice',
        'matching',
        'fill_blank',
        'open',
    ];

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
            $this->ensureAttemptPayload($paperExam, $activeAttempt);
            $orderedQuestions = $this->questionsFromAttemptPayload($activeAttempt->exam_payload ?? []);

            if ($paperExam->duration_minutes && $activeAttempt->started_at) {
                $endsAt = $activeAttempt->started_at->copy()->addMinutes((int) $paperExam->duration_minutes);
                $secondsRemaining = max(0, now()->diffInSeconds($endsAt, false));
            }
        }

        $savedAnswers = $activeAttempt
            ? $this->savedAnswersForAttempt($activeAttempt)
            : [];

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
            'savedAnswers' => $savedAnswers,
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

        $paperExam->load([
            'examQuestions.question.options',
            'examQuestions.question.matchingPairs',
        ]);
        [$questionSequence, $optionsSequence] = $this->buildAttemptSequences($paperExam);
        $examPayload = $this->buildAttemptPayload($paperExam, $questionSequence, $optionsSequence);

        PaperExamAttempt::create([
            'paper_exam_id' => $paperExam->id,
            'student_id' => $student->id,
            'attempt_number' => $attemptNumber,
            'started_at' => now(),
            'status' => 'in_progress',
            'question_sequence' => $questionSequence,
            'options_sequence' => $optionsSequence,
            'exam_payload' => $examPayload,
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

        $this->ensureAttemptPayload($paperExam, $attempt);

        $answers = $request->input('answers', []);
        if (empty($answers)) {
            $answers = $this->savedAnswersForAttempt($attempt);
        }
        $totalScore = 0.0;
        $totalMax = 0.0;
        $expired = $this->attemptExpired($paperExam, $attempt);

        DB::transaction(function () use ($paperExam, $attempt, $answers, $expired, $request, &$totalScore, &$totalMax) {
            [$totalScore, $totalMax] = $this->saveAttemptAnswers($paperExam, $attempt, $answers, true);
            $attempt->update([
                'status' => $expired ? 'locked' : 'submitted',
                'submitted_at' => now(),
                'locked_at' => $expired ? now() : $attempt->locked_at,
                'lock_reason' => $expired ? 'time_expired' : $attempt->lock_reason,
                'score' => round($totalScore, 2),
                'max_score' => round($totalMax, 2),
            ]);

            if ($expired) {
                $this->recordAttemptEvent($attempt, $request, 'time_expired_submit');
            }
        });

        return redirect()
            ->route('student.exams.show', $paperExam)
            ->with('clear_exam_draft_attempt_id', $attempt->id)
            ->with('info', $expired ? 'El tiempo termino. Se guardaron las respuestas capturadas y el intento fue bloqueado.' : 'Examen enviado correctamente.');
    }

    public function autosave(Request $request, PaperExam $paperExam, PaperExamAttempt $attempt)
    {
        $student = auth()->user()->student;
        $this->authorizeExam($paperExam, $student->group_id);
        abort_if((int) $attempt->paper_exam_id !== (int) $paperExam->id, 404);
        abort_if((int) $attempt->student_id !== (int) $student->id, 403);

        if ($attempt->status !== 'in_progress') {
            return response()->json(['ok' => false, 'status' => $attempt->status], 409);
        }

        if ($this->attemptExpired($paperExam, $attempt)) {
            $this->ensureAttemptPayload($paperExam, $attempt);

            DB::transaction(function () use ($paperExam, $attempt, $request) {
                $answers = $request->input('answers', []);
                if (empty($answers)) {
                    $answers = $this->savedAnswersForAttempt($attempt);
                }

                [$totalScore, $totalMax] = $this->saveAttemptAnswers($paperExam, $attempt, $answers, true);
                $attempt->update([
                    'status' => 'locked',
                    'submitted_at' => now(),
                    'locked_at' => now(),
                    'lock_reason' => 'time_expired',
                    'score' => $totalScore,
                    'max_score' => $totalMax,
                ]);
                $this->recordAttemptEvent($attempt, $request, 'time_expired_autosave');
            });

            return response()->json(['ok' => false, 'status' => 'locked', 'reason' => 'time_expired'], 409);
        }

        $attempt->forceFill([
            'autosave_payload' => $request->input('answers', []),
            'autosaved_at' => now(),
        ])->save();

        return response()->json(['ok' => true, 'saved_at' => now()->toIso8601String()]);
    }

    public function event(Request $request, PaperExam $paperExam, PaperExamAttempt $attempt)
    {
        $student = auth()->user()->student;
        $this->authorizeExam($paperExam, $student->group_id);
        abort_if((int) $attempt->paper_exam_id !== (int) $paperExam->id, 404);
        abort_if((int) $attempt->student_id !== (int) $student->id, 403);

        if ($request->has('events')) {
            $data = $request->validate([
                'events' => ['required', 'array', 'max:50'],
                'events.*.event_type' => ['required', 'string', 'max:80'],
                'events.*.metadata' => ['nullable', 'array'],
            ]);

            DB::transaction(function () use ($attempt, $request, $data) {
                foreach ($data['events'] as $event) {
                    $this->recordAttemptEvent($attempt, $request, $event['event_type'], $event['metadata'] ?? []);
                }
            });

            return response()->json(['ok' => true, 'count' => count($data['events'])]);
        }

        $data = $request->validate([
            'event_type' => ['required', 'string', 'max:80'],
            'metadata' => ['nullable', 'array'],
        ]);

        $this->recordAttemptEvent($attempt, $request, $data['event_type'], $data['metadata'] ?? []);

        return response()->json(['ok' => true]);
    }

    public function lock(Request $request, PaperExam $paperExam, PaperExamAttempt $attempt)
    {
        $student = auth()->user()->student;
        $this->authorizeExam($paperExam, $student->group_id);
        abort_if((int) $attempt->paper_exam_id !== (int) $paperExam->id, 404);
        abort_if((int) $attempt->student_id !== (int) $student->id, 403);

        if ($attempt->status === 'in_progress') {
            $this->ensureAttemptPayload($paperExam, $attempt);
            $answers = $request->input('answers', []);
            if (empty($answers)) {
                $answers = $this->savedAnswersForAttempt($attempt);
            }

            [$totalScore, $totalMax] = $this->saveAttemptAnswers($paperExam, $attempt, $answers, true);

            $attempt->update([
                'status' => 'locked',
                'locked_at' => now(),
                'submitted_at' => now(),
                'lock_reason' => $request->input('reason', 'tab_switch'),
                'score' => $totalScore,
                'max_score' => $totalMax,
            ]);

            $this->recordAttemptEvent($attempt, $request, 'lock', [
                'reason' => $request->input('reason', 'tab_switch'),
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

    private function buildAttemptSequences(PaperExam $paperExam): array
    {
        $questions = $paperExam->examQuestions
            ->pluck('question')
            ->filter()
            ->values();

        $questionSequence = collect(self::QUESTION_TYPE_ORDER)
            ->flatMap(function (string $type) use ($questions) {
                return $questions
                    ->where('type', $type)
                    ->shuffle()
                    ->pluck('id');
            });

        $knownTypes = collect(self::QUESTION_TYPE_ORDER);
        $unknownSequence = $questions
            ->reject(fn (Question $question) => $knownTypes->contains($question->type))
            ->shuffle()
            ->pluck('id');

        $questionSequence = $questionSequence
            ->merge($unknownSequence)
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $optionsSequence = [];
        $questionsById = $questions->keyBy('id');

        foreach ($questionSequence as $questionId) {
            /** @var Question|null $question */
            $question = $questionsById->get((int) $questionId);

            if (! $question) {
                continue;
            }

            if ($question->type === 'multiple_choice') {
                $optionsSequence[(string) $questionId] = $question->options
                    ->pluck('id')
                    ->shuffle()
                    ->values()
                    ->all();
            }

            if ($question->type === 'matching') {
                $optionsSequence[(string) $questionId] = $question->matchingPairs
                    ->pluck('right_text')
                    ->shuffle()
                    ->values()
                    ->all();
            }
        }

        return [$questionSequence, $optionsSequence];
    }

    private function ensureAttemptPayload(PaperExam $paperExam, PaperExamAttempt $attempt): array
    {
        if (! empty($attempt->exam_payload['questions'] ?? [])) {
            return $attempt->exam_payload;
        }

        $paperExam->loadMissing([
            'examQuestions.question.options',
            'examQuestions.question.matchingPairs',
            'examQuestions.question.fillBlanks',
        ]);

        $questionSequence = $attempt->question_sequence ?: $paperExam->examQuestions
            ->pluck('question_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();
        $optionsSequence = $attempt->options_sequence ?: [];
        $payload = $this->buildAttemptPayload($paperExam, $questionSequence, $optionsSequence);

        if ($attempt->exists) {
            $attempt->forceFill(['exam_payload' => $payload])->save();
        } else {
            $attempt->exam_payload = $payload;
        }

        return $payload;
    }

    private function buildAttemptPayload(PaperExam $paperExam, array $questionSequence, array $optionsSequence): array
    {
        $paperExam->loadMissing([
            'examQuestions.question.options',
            'examQuestions.question.matchingPairs',
            'examQuestions.question.fillBlanks',
        ]);

        $examQuestionsByQuestionId = $paperExam->examQuestions
            ->filter(fn ($examQuestion) => $examQuestion->question)
            ->keyBy('question_id');

        $sequence = collect($questionSequence)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        if ($sequence->isEmpty()) {
            $sequence = $examQuestionsByQuestionId->keys()
                ->map(fn ($id) => (int) $id)
                ->values();
        }

        $missingQuestionIds = $examQuestionsByQuestionId->keys()
            ->map(fn ($id) => (int) $id)
            ->diff($sequence)
            ->values();
        $sequence = $sequence->merge($missingQuestionIds)->values();

        $questions = $sequence
            ->map(function (int $questionId) use ($examQuestionsByQuestionId, $optionsSequence) {
                $examQuestion = $examQuestionsByQuestionId->get($questionId);
                $question = $examQuestion?->question;

                if (! $question) {
                    return null;
                }

                $optionSequence = collect(data_get($optionsSequence, (string) $question->id, []));
                $options = $question->options;
                if ($question->type === 'multiple_choice' && $optionSequence->isNotEmpty()) {
                    $options = $optionSequence
                        ->map(fn ($optionId) => $question->options->firstWhere('id', (int) $optionId))
                        ->filter()
                        ->values();
                }

                return [
                    'id' => (int) $question->id,
                    'type' => (string) $question->type,
                    'type_label' => (string) $question->type_label,
                    'prompt' => (string) $question->prompt,
                    'points' => (float) ($question->points ?: 1),
                    'points_override' => $examQuestion->points_override !== null ? (float) $examQuestion->points_override : null,
                    'meta' => is_array($question->meta) ? $question->meta : [],
                    'options' => $options
                        ->map(fn ($option) => [
                            'id' => (int) $option->id,
                            'option_text' => (string) $option->option_text,
                            'is_correct' => (bool) $option->is_correct,
                            'sort_order' => (int) ($option->sort_order ?? 0),
                        ])
                        ->values()
                        ->all(),
                    'matching_pairs' => $question->matchingPairs
                        ->map(fn ($pair) => [
                            'id' => (int) $pair->id,
                            'left_text' => (string) $pair->left_text,
                            'right_text' => (string) $pair->right_text,
                            'sort_order' => (int) ($pair->sort_order ?? 0),
                        ])
                        ->values()
                        ->all(),
                    'fill_blanks' => $question->fillBlanks
                        ->map(fn ($blank) => [
                            'id' => (int) $blank->id,
                            'expected_answer' => (string) $blank->expected_answer,
                            'sort_order' => (int) ($blank->sort_order ?? 0),
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->filter()
            ->values()
            ->all();

        return [
            'version' => 1,
            'generated_at' => now()->toIso8601String(),
            'questions' => $questions,
        ];
    }

    private function questionsFromAttemptPayload(array $payload)
    {
        return collect($payload['questions'] ?? [])
            ->map(function (array $question) {
                return (object) [
                    'id' => (int) $question['id'],
                    'type' => (string) $question['type'],
                    'type_label' => (string) ($question['type_label'] ?? $question['type']),
                    'prompt' => (string) ($question['prompt'] ?? ''),
                    'points' => (float) ($question['points'] ?? 1),
                    'meta' => $question['meta'] ?? [],
                    'options' => collect($question['options'] ?? [])
                        ->map(fn (array $option) => (object) $option)
                        ->values(),
                    'matchingPairs' => collect($question['matching_pairs'] ?? [])
                        ->map(fn (array $pair) => (object) $pair)
                        ->values(),
                    'fillBlanks' => collect($question['fill_blanks'] ?? [])
                        ->map(fn (array $blank) => (object) $blank)
                        ->values(),
                ];
            })
            ->values();
    }

    private function attemptExpired(PaperExam $paperExam, PaperExamAttempt $attempt): bool
    {
        if ($paperExam->online_available_until && now()->greaterThan($paperExam->online_available_until)) {
            return true;
        }

        if (! $paperExam->duration_minutes || ! $attempt->started_at) {
            return false;
        }

        return now()->greaterThan($attempt->started_at->copy()->addMinutes((int) $paperExam->duration_minutes));
    }

    private function saveAttemptAnswers(PaperExam $paperExam, PaperExamAttempt $attempt, array $answers, bool $scoreAnswers): array
    {
        $totalScore = 0.0;
        $totalMax = 0.0;

        $examQuestions = $this->scoringExamQuestions($paperExam, $attempt);

        foreach ($examQuestions as $examQuestion) {
            $question = $examQuestion->question;
            if (! $question) {
                continue;
            }

            $max = round((float) ($examQuestion->points_override ?: $question->points ?: 1), 2);
            $score = null;
            $isCorrect = null;
            $payload = $answers[$question->id] ?? $answers[(string) $question->id] ?? [];

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
                    'is_correct' => $scoreAnswers ? $isCorrect : null,
                    'score' => $scoreAnswers && $question->type !== 'open' ? $score : null,
                    'max_score' => $max,
                ]
            );

            $totalMax += $max;
            if ($question->type !== 'open' && $score !== null) {
                $totalScore += $score;
            }
        }

        return [round($totalScore, 2), round($totalMax, 2)];
    }

    private function scoringExamQuestions(PaperExam $paperExam, PaperExamAttempt $attempt): Collection
    {
        $payload = $this->ensureAttemptPayload($paperExam, $attempt);

        if (! empty($payload['questions'] ?? [])) {
            return collect($payload['questions'])
                ->map(function (array $questionData) {
                    $question = (object) [
                        'id' => (int) $questionData['id'],
                        'type' => (string) $questionData['type'],
                        'points' => (float) ($questionData['points'] ?? 1),
                        'options' => collect($questionData['options'] ?? [])
                            ->map(fn (array $option) => (object) $option)
                            ->values(),
                        'matchingPairs' => collect($questionData['matching_pairs'] ?? [])
                            ->map(fn (array $pair) => (object) $pair)
                            ->values(),
                        'fillBlanks' => collect($questionData['fill_blanks'] ?? [])
                            ->map(fn (array $blank) => (object) $blank)
                            ->values(),
                    ];

                    return (object) [
                        'points_override' => $questionData['points_override'] ?? null,
                        'question' => $question,
                    ];
                })
                ->values();
        }

        $paperExam->loadMissing([
            'examQuestions.question.options',
            'examQuestions.question.matchingPairs',
            'examQuestions.question.fillBlanks',
        ]);

        return $paperExam->examQuestions;
    }

    private function savedAnswersForAttempt(PaperExamAttempt $attempt): array
    {
        $attempt->loadMissing('answers');

        if (! empty($attempt->autosave_payload) && is_array($attempt->autosave_payload)) {
            return $attempt->autosave_payload;
        }

        return $attempt->answers
            ->mapWithKeys(fn ($answer) => [
                (string) $answer->question_id => $answer->answer_payload ?: [],
            ])
            ->all();
    }

    private function recordAttemptEvent(PaperExamAttempt $attempt, Request $request, string $eventType, array $metadata = []): void
    {
        PaperExamAttemptEvent::create([
            'paper_exam_attempt_id' => $attempt->id,
            'event_type' => mb_substr($eventType, 0, 80),
            'occurred_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => $metadata,
        ]);
    }
}
