<?php

namespace App\Console\Commands;

use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use App\Models\Attendance;
use App\Models\CyclePartial;
use App\Models\Group;
use App\Models\PaperExam;
use App\Models\PaperExamAttempt;
use App\Models\PaperExamAttemptAnswer;
use App\Models\PaperExamAttemptEvent;
use App\Models\PaperExamQuestion;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionFillBlank;
use App\Models\QuestionMatchingPair;
use App\Models\QuestionOption;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\Student;
use App\Models\TeachingAssignment;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class StressOperationalFlowsCommand extends Command
{
    protected $signature = 'stress:operational-flows
        {--mode=run : run|worker-attendance|worker-exam|cleanup}
        {--scenario=all : all|attendance|exams}
        {--group=5005 : Grupo base para datos piloto si hace falta}
        {--exam-groups=5003,5004,5005 : Grupos para examen simultaneo}
        {--exam-batch-size=15 : Alumnos por lote para simular envio escalonado}
        {--exam-batch-delay=8 : Segundos de desfase entre lotes de examen}
        {--token= : Token interno para workers}
        {--session-id= : ID de sesion para worker de asistencia}
        {--exam-id= : ID de examen para worker de examen}
        {--student-id= : ID de alumno para worker de examen}';

    protected $description = 'Estresa flujos operativos: asistencia concurrente y examenes en linea simultaneos.';

    private const MARKER = '[STRESS]';

    public function handle(): int
    {
        return match ((string) $this->option('mode')) {
            'worker-attendance' => $this->workerAttendance(),
            'worker-exam' => $this->workerExam(),
            'cleanup' => $this->cleanup(),
            default => $this->runStress(),
        };
    }

    private function runStress(): int
    {
        $scenario = (string) $this->option('scenario');
        $token = Str::uuid()->toString();
        $results = [];

        if (in_array($scenario, ['all', 'attendance'], true)) {
            $sessions = $this->prepareAttendanceSessions($token);
            $this->line('Asistencia: ' . $sessions->count() . ' profesores/sesiones en paralelo.');
            $results['attendance'] = $this->runWorkers($sessions->map(fn ($session) => [
                '--mode=worker-attendance',
                '--token=' . $token,
                '--session-id=' . $session->id,
            ])->all());
        }

        if (in_array($scenario, ['all', 'exams'], true)) {
            $examJobs = $this->prepareExamJobs($token);
            $batchSize = max(1, (int) $this->option('exam-batch-size'));
            $batchDelay = max(0, (int) $this->option('exam-batch-delay'));
            $this->line('Examenes: ' . $examJobs->count() . " alumnos en lotes de {$batchSize} con {$batchDelay}s de desfase.");
            $results['exams'] = $this->runWorkers($examJobs->map(fn ($job) => [
                '--mode=worker-exam',
                '--token=' . $token,
                '--exam-id=' . $job['exam_id'],
                '--student-id=' . $job['student_id'],
            ])->all(), 90, $batchSize, $batchDelay);
        }

        $this->printResults($results);

        return collect($results)->flatten(1)->contains(fn ($row) => (int) $row['exit_code'] !== 0)
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function workerAttendance(): int
    {
        $session = AcademicSession::query()
            ->with('teachingAssignment.group.students')
            ->findOrFail((int) $this->option('session-id'));

        $students = $this->studentsForAssignment($session->teachingAssignment)->values();
        $statuses = ['present', 'present', 'present', 'late', 'absent'];

        DB::transaction(function () use ($session, $students, $statuses) {
            foreach ($students as $index => $student) {
                Attendance::updateOrCreate(
                    [
                        'academic_session_id' => $session->id,
                        'student_id' => $student->id,
                    ],
                    [
                        'status' => $statuses[$index % count($statuses)],
                        'is_suspension_locked' => false,
                    ]
                );
            }
        });

        $this->line(json_encode([
            'kind' => 'attendance',
            'session_id' => $session->id,
            'students' => $students->count(),
        ]));

        return self::SUCCESS;
    }

    private function workerExam(): int
    {
        $exam = PaperExam::query()
            ->with([
                'examQuestions.question.options',
                'examQuestions.question.matchingPairs',
                'examQuestions.question.fillBlanks',
            ])
            ->findOrFail((int) $this->option('exam-id'));
        $studentId = (int) $this->option('student-id');

        DB::transaction(function () use ($exam, $studentId) {
            $attempt = PaperExamAttempt::create([
                'paper_exam_id' => $exam->id,
                'student_id' => $studentId,
                'attempt_number' => 1,
                'started_at' => now(),
                'status' => 'in_progress',
                'question_sequence' => $exam->examQuestions->pluck('question_id')->shuffle()->values()->all(),
                'options_sequence' => $this->optionsSequence($exam),
            ]);

            $payload = [];
            foreach ($exam->examQuestions as $examQuestion) {
                $payload[(string) $examQuestion->question_id] = $this->answerPayload($examQuestion->question, 1);
            }

            $attempt->forceFill([
                'autosave_payload' => $payload,
                'autosaved_at' => now(),
            ])->save();

            foreach ($exam->examQuestions as $examQuestion) {
                PaperExamAttemptAnswer::updateOrCreate(
                    [
                        'paper_exam_attempt_id' => $attempt->id,
                        'question_id' => $examQuestion->question_id,
                    ],
                    [
                        'answer_payload' => $payload[(string) $examQuestion->question_id] ?? [],
                        'is_correct' => null,
                        'score' => null,
                        'max_score' => (float) ($examQuestion->points_override ?: $examQuestion->question->points ?: 1),
                    ]
                );
            }

            [$score, $maxScore] = $this->scoreAttempt($exam, $attempt);
            $attempt->update([
                'status' => 'submitted',
                'submitted_at' => now(),
                'score' => $score,
                'max_score' => $maxScore,
            ]);
        });

        $this->line(json_encode([
            'kind' => 'exam',
            'exam_id' => $exam->id,
            'student_id' => $studentId,
            'questions' => $exam->examQuestions->count(),
        ]));

        return self::SUCCESS;
    }

    private function prepareAttendanceSessions(string $token): Collection
    {
        $cycle = $this->activeCycle();
        $period = $this->firstPeriod($cycle);
        $date = now()->toDateString();

        return SchoolCycleGroup::query()
            ->where('school_cycle_id', $cycle->id)
            ->where('is_active', true)
            ->with('group')
            ->get()
            ->map(function (SchoolCycleGroup $cycleGroup) use ($period, $date, $token) {
                $assignment = TeachingAssignment::query()
                    ->where('school_cycle_group_id', $cycleGroup->id)
                    ->where('is_active', true)
                    ->whereNull('section_type')
                    ->orderBy('id')
                    ->first()
                    ?: TeachingAssignment::query()
                        ->where('school_cycle_group_id', $cycleGroup->id)
                        ->where('is_active', true)
                        ->orderBy('id')
                        ->first();

                if (! $assignment) {
                    return null;
                }

                $schedule = $assignment->schedules()->where('is_active', true)->orderBy('id')->first();
                if (! $schedule) {
                    return null;
                }

                $session = AcademicSession::updateOrCreate(
                    [
                        'schedule_id' => $schedule->id,
                        'session_date' => $date,
                    ],
                    [
                        'teaching_assignment_id' => $assignment->id,
                        'academic_period_id' => $period->id,
                        'start_time' => '00:02:00',
                        'end_time' => '00:52:00',
                        'is_cancelled' => false,
                    ]
                );

                $session->forceFill([
                    'attendance_closed_by' => self::MARKER . ' ' . $token,
                ])->save();

                return $session;
            })
            ->filter()
            ->values();
    }

    private function prepareExamJobs(string $token): Collection
    {
        $cycle = $this->activeCycle();
        $partial = CyclePartial::query()->where('school_cycle_id', $cycle->id)->orderBy('sort_order')->firstOrFail();
        $groups = collect(explode(',', (string) $this->option('exam-groups')))
            ->map(fn ($group) => trim($group))
            ->filter()
            ->values();

        return $groups->flatMap(function (string $groupName) use ($cycle, $partial, $token) {
            $group = Group::query()->where('name', $groupName)->firstOrFail();
            $assignment = TeachingAssignment::query()
                ->where('group_id', $group->id)
                ->where('is_active', true)
                ->whereHas('subject', fn ($query) => $query->where('name', 'like', '%QUÍMICA%')->orWhere('name', 'like', '%QUIMICA%'))
                ->whereNull('section_type')
                ->first()
                ?: TeachingAssignment::query()->where('group_id', $group->id)->where('is_active', true)->whereNull('section_type')->firstOrFail();

            $exam = $this->ensureStressExam($assignment, $cycle, $partial, $token);
            $students = Student::query()
                ->where('group_id', $group->id)
                ->where('is_active', true)
                ->orderBy('id')
                ->take(25)
                ->pluck('id');

            return $students->map(fn ($studentId) => [
                'exam_id' => $exam->id,
                'student_id' => (int) $studentId,
            ]);
        })->values();
    }

    private function ensureStressExam(TeachingAssignment $assignment, SchoolCycle $cycle, CyclePartial $partial, string $token): PaperExam
    {
        $bank = QuestionBank::create([
            'teacher_id' => $assignment->teacher_id,
            'subject_id' => $assignment->subject_id,
            'school_cycle_id' => $cycle->id,
            'cycle_partial_id' => $partial->id,
            'name' => self::MARKER . ' Banco ' . $assignment->group_id . ' ' . $token,
            'description' => 'Banco temporal para prueba de estres.',
            'is_active' => true,
        ]);

        $questions = collect();
        for ($i = 1; $i <= 8; $i++) {
            $question = Question::create([
                'question_bank_id' => $bank->id,
                'type' => $i % 4 === 0 ? 'fill_blank' : 'multiple_choice',
                'prompt' => self::MARKER . " Pregunta {$i}",
                'points' => 1,
                'sort_order' => $i,
                'is_active' => true,
            ]);

            if ($question->type === 'multiple_choice') {
                foreach (['A', 'B', 'C', 'D'] as $index => $letter) {
                    QuestionOption::create([
                        'question_id' => $question->id,
                        'option_text' => "Opcion {$letter}",
                        'is_correct' => $index === 0,
                        'sort_order' => $index + 1,
                    ]);
                }
            } else {
                QuestionFillBlank::create([
                    'question_id' => $question->id,
                    'expected_answer' => 'respuesta',
                    'sort_order' => 1,
                ]);
            }

            $questions->push($question);
        }

        $exam = PaperExam::create([
            'created_by' => 178,
            'teaching_assignment_id' => $assignment->id,
            'school_cycle_id' => $cycle->id,
            'cycle_partial_id' => $partial->id,
            'title' => self::MARKER . ' Examen simultaneo ' . $assignment->group_id . ' ' . $token,
            'instructions' => 'Examen temporal para prueba de estres.',
            'duration_minutes' => 30,
            'is_online_enabled' => true,
            'online_available_from' => now()->subHour(),
            'online_available_until' => now()->addHour(),
            'online_max_attempts' => 1,
            'online_show_result' => true,
            'is_active' => true,
        ]);

        $questions->each(fn (Question $question, int $index) => PaperExamQuestion::create([
            'paper_exam_id' => $exam->id,
            'question_id' => $question->id,
            'sort_order' => $index + 1,
            'points_override' => 1,
        ]));

        return $exam->load('examQuestions.question.options', 'examQuestions.question.fillBlanks');
    }

    private function runWorkers(array $workerOptions, int $timeout = 60, ?int $batchSize = null, int $batchDelay = 0): array
    {
        $php = PHP_BINARY;
        $artisan = base_path('artisan');
        $processes = [];
        $started = microtime(true);
        $batchSize = $batchSize ?: count($workerOptions);
        $rows = [];

        $startProcess = function (array $options, int $index) use ($php, $artisan, $timeout): array {
            $process = new Process(array_merge([$php, $artisan, 'stress:operational-flows'], $options), base_path(), null, null, $timeout);
            $process->start();

            return [
                'process' => $process,
                'started_at' => microtime(true),
                'index' => $index,
            ];
        };

        foreach (array_chunk($workerOptions, $batchSize, true) as $batchIndex => $batch) {
            if ($batchIndex > 0 && $batchDelay > 0) {
                sleep($batchDelay);
            }

            $processes = [];
            foreach ($batch as $index => $options) {
                $processes[$index] = $startProcess($options, $index);
            }

            foreach ($processes as $entry) {
                /** @var Process $process */
                $process = $entry['process'];
                $process->wait();
                $rows[] = [
                    'index' => $entry['index'] + 1,
                    'exit_code' => $process->getExitCode(),
                    'seconds' => round(microtime(true) - $entry['started_at'], 3),
                    'output' => trim($process->getOutput()),
                    'error' => trim($process->getErrorOutput()),
                ];
            }
        }

        $this->line('Tiempo total paralelo: ' . round(microtime(true) - $started, 3) . 's');

        return $rows;
    }

    private function printResults(array $results): void
    {
        foreach ($results as $label => $rows) {
            $collection = collect($rows);
            $success = $collection->where('exit_code', 0)->count();
            $failed = $collection->count() - $success;
            $avg = round((float) $collection->avg('seconds'), 3);
            $max = round((float) $collection->max('seconds'), 3);

            $this->newLine();
            $this->info(strtoupper($label));
            $this->line("OK: {$success} | Errores: {$failed} | Promedio: {$avg}s | Max: {$max}s");

            $collection->where('exit_code', '!=', 0)->take(5)->each(function ($row) {
                $this->error('#' . $row['index'] . ' ' . $row['error']);
            });
        }
    }

    private function cleanup(): int
    {
        DB::transaction(function () {
            $examIds = PaperExam::query()->where('title', 'like', self::MARKER . '%')->pluck('id');
            if ($examIds->isNotEmpty()) {
                PaperExam::query()->whereIn('id', $examIds)->delete();
            }

            $bankIds = QuestionBank::query()->where('name', 'like', self::MARKER . '%')->pluck('id');
            if ($bankIds->isNotEmpty()) {
                QuestionBank::query()->whereIn('id', $bankIds)->delete();
            }

            $sessionIds = AcademicSession::query()
                ->where(function ($query) {
                    $query->where('attendance_closed_by', 'like', self::MARKER . '%')
                        ->orWhereTime('start_time', '00:02:00');
                })
                ->pluck('id');
            if ($sessionIds->isNotEmpty()) {
                Attendance::query()->whereIn('academic_session_id', $sessionIds)->delete();
                AcademicSession::query()->whereIn('id', $sessionIds)->delete();
            }
        });

        $this->info('Datos de estres eliminados.');

        return self::SUCCESS;
    }

    private function activeCycle(): SchoolCycle
    {
        return SchoolCycle::query()->where('is_active', true)->orderByDesc('start_date')->firstOrFail();
    }

    private function firstPeriod(SchoolCycle $cycle): AcademicPeriod
    {
        $periodId = $cycle->partials()->whereNotNull('academic_period_id')->orderBy('sort_order')->value('academic_period_id');
        return AcademicPeriod::query()->findOrFail((int) $periodId);
    }

    private function studentsForAssignment(TeachingAssignment $assignment): Collection
    {
        if ($assignment->students()->exists()) {
            return $assignment->students()
                ->where('students.is_active', true)
                ->where('students.group_id', $assignment->group_id)
                ->get();
        }

        return $assignment->group->students()->where('is_active', true)->get();
    }

    private function optionsSequence(PaperExam $exam): array
    {
        $sequence = [];
        foreach ($exam->examQuestions as $examQuestion) {
            $question = $examQuestion->question;
            if ($question->type === 'multiple_choice') {
                $sequence[(string) $question->id] = $question->options->pluck('id')->shuffle()->values()->all();
            }
        }

        return $sequence;
    }

    private function answerPayload(Question $question, int $autosaveIndex): array
    {
        if ($question->type === 'multiple_choice') {
            return ['option_id' => (int) ($question->options->first()?->id ?? 0)];
        }

        if ($question->type === 'fill_blank') {
            return ['blanks' => [$autosaveIndex === 3 ? 'respuesta' : 'borrador']];
        }

        if ($question->type === 'matching') {
            return ['pairs' => $question->matchingPairs->mapWithKeys(fn ($pair) => [(string) $pair->id => $pair->right_text])->all()];
        }

        return ['text' => 'Respuesta abierta de prueba'];
    }

    private function scoreAttempt(PaperExam $exam, PaperExamAttempt $attempt): array
    {
        $score = 0;
        $maxScore = 0;

        foreach ($exam->examQuestions as $examQuestion) {
            $question = $examQuestion->question;
            $max = (float) ($examQuestion->points_override ?: $question->points ?: 1);
            $answer = PaperExamAttemptAnswer::query()
                ->where('paper_exam_attempt_id', $attempt->id)
                ->where('question_id', $question->id)
                ->first();

            $isCorrect = null;
            $questionScore = null;
            if ($question->type === 'multiple_choice') {
                $correct = $question->options->firstWhere('is_correct', true);
                $isCorrect = $correct && (int) ($answer->answer_payload['option_id'] ?? 0) === (int) $correct->id;
                $questionScore = $isCorrect ? $max : 0;
            } elseif ($question->type === 'fill_blank') {
                $expected = mb_strtolower((string) ($question->fillBlanks->first()?->expected_answer ?? ''));
                $given = mb_strtolower((string) (($answer->answer_payload['blanks'][0] ?? '')));
                $isCorrect = $expected !== '' && $given === $expected;
                $questionScore = $isCorrect ? $max : 0;
            }

            $answer?->update([
                'is_correct' => $isCorrect,
                'score' => $questionScore,
                'max_score' => $max,
            ]);

            $maxScore += $max;
            $score += $questionScore ?? 0;
        }

        return [round($score, 2), round($maxScore, 2)];
    }
}
