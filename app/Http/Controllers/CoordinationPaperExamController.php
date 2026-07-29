<?php

namespace App\Http\Controllers;

use App\Models\PaperExam;
use App\Models\QuestionBank;
use App\Models\Schedule;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\TeachingAssignment;
use App\Models\CyclePartial;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Services\CurrentSchoolCycle;

class CoordinationPaperExamController extends Controller
{
    private const DAY_OPTIONS = [
        'monday' => 'Lunes',
        'tuesday' => 'Martes',
        'wednesday' => 'Miércoles',
        'thursday' => 'Jueves',
        'friday' => 'Viernes',
    ];

    public function index()
    {
        $activeCampusId = $this->activeCampusId();
        $activeCycle = app(CurrentSchoolCycle::class)->get(auth()->user(), $activeCampusId);

        $exams = PaperExam::query()
            ->with(['assignment.group', 'assignment.subject', 'partial', 'schoolCycle'])
            ->withCount('examQuestions')
            ->when(
                $activeCycle,
                fn ($query) => $query->where('school_cycle_id', (int) $activeCycle->id),
                fn ($query) => $query->whereRaw('1 = 0')
            )
            ->whereHas('assignment.schoolCycleGroup', fn ($query) => $query->where('is_active', true))
            ->when($activeCampusId > 0, fn ($query) => $this->applyCampusFilterToExamQuery($query, $activeCampusId))
            ->orderByDesc('id')
            ->get();

        return view('coordination.paper_exams.index', compact('exams'));
    }

    public function schedule(Request $request)
    {
        $activeCampusId = $this->activeCampusId();

        $activeCycle = app(CurrentSchoolCycle::class)->get($request->user(), $activeCampusId);

        $cycleGroups = collect();
        $selectedCycleGroup = null;
        $calendar = null;
        $examsByAssignment = collect();

        if ($activeCycle) {
            $cycleGroups = SchoolCycleGroup::query()
                ->with('group.level.modality')
                ->where('school_cycle_id', $activeCycle->id)
                ->when($activeCampusId > 0, fn ($query) => $query->where('campus_id', $activeCampusId))
                ->where('is_active', true)
                ->get()
                ->sortBy(fn (SchoolCycleGroup $cycleGroup) => mb_strtolower((string) ($cycleGroup->group->name ?? '')))
                ->values();

            $selectedCycleGroupId = (int) $request->query('cycle_group_id', $cycleGroups->first()?->id ?? 0);
            $selectedCycleGroup = $cycleGroups->firstWhere('id', $selectedCycleGroupId);

            if ($selectedCycleGroup) {
                $calendar = $this->buildExamScheduleCalendar($activeCycle, $selectedCycleGroup);

                $assignmentIds = $calendar['schedules']
                    ->pluck('teaching_assignment_id')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();

                $examsByAssignment = PaperExam::query()
                    ->with(['assignment', 'partial'])
                    ->withCount('examQuestions')
                    ->whereIn('teaching_assignment_id', $assignmentIds)
                    ->when($activeCampusId > 0, fn ($query) => $this->applyCampusFilterToExamQuery($query, $activeCampusId))
                    ->orderByDesc('id')
                    ->get()
                    ->groupBy(fn (PaperExam $exam) => (int) $exam->teaching_assignment_id);
            }
        }

        $scheduledExamsBySubject = $examsByAssignment
            ->flatten(1)
            ->filter(fn (PaperExam $exam) => ! is_null($exam->online_available_from))
            ->groupBy(fn (PaperExam $exam) => (int) ($exam->assignment?->subject_id ?? 0));

        $scheduledSubjectIds = $scheduledExamsBySubject
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->values();

        return view('coordination.paper_exams.schedule', [
            'activeCycle' => $activeCycle,
            'cycleGroups' => $cycleGroups,
            'selectedCycleGroup' => $selectedCycleGroup,
            'calendar' => $calendar,
            'dayOptions' => self::DAY_OPTIONS,
            'examsByAssignment' => $examsByAssignment,
            'scheduledExamsBySubject' => $scheduledExamsBySubject,
            'scheduledSubjectIds' => $scheduledSubjectIds,
        ]);
    }

    public function updateSchedule(Request $request)
    {
        $data = $request->validate([
            'schedule_id' => ['required', 'integer', 'exists:schedules,id'],
            'exam_date' => ['required', 'date'],
        ]);

        $activeCampusId = $this->activeCampusId();

        $schedule = Schedule::query()
            ->with(['assignment.schoolCycleGroup', 'assignment.subject', 'assignment.group'])
            ->findOrFail((int) $data['schedule_id']);

        if ($activeCampusId > 0) {
            $scheduleCampusId = (int) ($schedule->assignment?->schoolCycleGroup?->campus_id ?? 0);

            if ($scheduleCampusId !== $activeCampusId) {
                abort(403, 'El horario no pertenece al campus activo.');
            }
        }

        $examDate = Carbon::parse($data['exam_date'])->startOfDay();
        $dateDayKey = [
            1 => 'monday',
            2 => 'tuesday',
            3 => 'wednesday',
            4 => 'thursday',
            5 => 'friday',
        ][$examDate->dayOfWeek] ?? null;

        $scheduleForDate = Schedule::query()
            ->with(['assignment.schoolCycleGroup', 'assignment.subject', 'assignment.group'])
            ->where('is_active', true)
            ->where('school_cycle_id', $schedule->school_cycle_id)
            ->where('day_of_week', $dateDayKey)
            ->whereHas('assignment', function ($query) use ($schedule) {
                $query->where('school_cycle_group_id', $schedule->assignment->school_cycle_group_id)
                    ->where('subject_id', $schedule->assignment->subject_id);
            })
            ->orderBy('start_time')
            ->first();

        if (! $scheduleForDate) {
            throw ValidationException::withMessages([
                'exam_date' => 'La fecha seleccionada no corresponde a un dia en que se imparta esta materia.',
            ]);
        }

        $schedule = $scheduleForDate;

        $startTime = substr((string) $schedule->start_time, 0, 5);
        $endTime = substr((string) $schedule->end_time, 0, 5);
        $availableFrom = Carbon::parse($examDate->format('Y-m-d') . ' ' . $startTime);
        $availableUntil = Carbon::parse($examDate->format('Y-m-d') . ' ' . $endTime);
        $partial = $this->partialForDate((int) $schedule->school_cycle_id, $examDate);
        $title = $this->scheduledExamTitle($schedule, $partial, $examDate);

        DB::transaction(function () use ($schedule, $partial, $title, $availableFrom, $availableUntil) {
            $examQuery = PaperExam::query()
                ->where('school_cycle_id', $schedule->school_cycle_id)
                ->whereHas('assignment', function ($query) use ($schedule) {
                    $query->where('school_cycle_group_id', $schedule->assignment->school_cycle_group_id)
                        ->where('subject_id', $schedule->assignment->subject_id);
                });

            if ($partial) {
                $examQuery->where('cycle_partial_id', $partial->id);
            } else {
                $examQuery->whereNull('cycle_partial_id');
            }

            $paperExam = $examQuery->orderByDesc('id')->first();

            $payload = [
                'title' => $title,
                'school_cycle_id' => $schedule->school_cycle_id,
                'cycle_partial_id' => $partial?->id,
                'duration_minutes' => max(1, $availableFrom->diffInMinutes($availableUntil)),
                'is_online_enabled' => true,
                'online_available_from' => $availableFrom,
                'online_available_until' => $availableUntil,
                'online_max_attempts' => 1,
                'online_show_result' => false,
                'is_active' => true,
            ];

            if ($paperExam) {
                $paperExam->update($payload);
                return;
            }

            PaperExam::create(array_merge($payload, [
                'created_by' => auth()->id(),
                'teaching_assignment_id' => $schedule->teaching_assignment_id,
                'instructions' => null,
            ]));
        });

        return redirect()
            ->route('coordination.paper-exams.schedule', ['cycle_group_id' => $schedule->assignment->school_cycle_group_id])
            ->with('info', 'Horario de examen actualizado correctamente.');
    }

    public function create()
    {
        $activeCampusId = $this->activeCampusId();
        $activeCycle = app(CurrentSchoolCycle::class)->get(auth()->user(), $activeCampusId);

        $assignments = TeachingAssignment::query()
            ->with(['group', 'subject'])
            ->when(
                $activeCycle,
                fn ($q) => $q->whereHas('schoolCycleGroup', fn ($sq) => $sq->where('school_cycle_id', $activeCycle->id)->where('is_active', true))
            )
            ->when($activeCampusId > 0, fn ($q) => $this->applyCampusFilterToAssignmentQuery($q, $activeCampusId))
            ->where('is_active', true)
            ->orderBy('group_id')
            ->get();

        $banks = QuestionBank::query()
            ->with(['subject', 'teacher.user', 'partial', 'questions'])
            ->where('is_active', true)
            ->when(
                $activeCycle,
                fn ($q) => $q->where('school_cycle_id', $activeCycle->id),
                fn ($q) => $q->whereRaw('1 = 0')
            )
            ->orderByDesc('id')
            ->get();

        return view('coordination.paper_exams.create', compact('assignments', 'banks', 'activeCycle'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'instructions' => ['nullable', 'string'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'is_online_enabled' => ['nullable', 'boolean'],
            'online_available_from' => ['nullable', 'date'],
            'online_available_until' => ['nullable', 'date', 'after:online_available_from'],
            'online_max_attempts' => ['nullable', 'integer', 'min:1', 'max:10'],
            'online_show_result' => ['nullable', 'boolean'],
            'teaching_assignment_id' => ['required', 'integer', 'exists:teaching_assignments,id'],
            'school_cycle_id' => ['nullable', 'integer', 'exists:school_cycles,id'],
            'cycle_partial_id' => ['nullable', 'integer', 'exists:cycle_partials,id'],
            'question_ids' => ['required', 'array', 'min:1'],
            'question_ids.*' => ['integer', 'exists:questions,id'],
        ]);

        $assignment = TeachingAssignment::query()
            ->with('schoolCycleGroup')
            ->whereKey((int) $data['teaching_assignment_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $schoolCycleId = (int) ($assignment->schoolCycleGroup?->school_cycle_id ?? 0);
        if ($schoolCycleId <= 0) {
            throw ValidationException::withMessages([
                'teaching_assignment_id' => 'La asignacion seleccionada no pertenece a un ciclo activo.',
            ]);
        }

        if (! empty($data['cycle_partial_id'])) {
            $partialBelongsToCycle = CyclePartial::query()
                ->whereKey((int) $data['cycle_partial_id'])
                ->where('school_cycle_id', $schoolCycleId)
                ->exists();

            if (! $partialBelongsToCycle) {
                throw ValidationException::withMessages([
                    'cycle_partial_id' => 'El parcial seleccionado no pertenece al ciclo de la asignacion.',
                ]);
            }
        }

        $questionIds = collect($data['question_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $validQuestionIds = QuestionBank::query()
            ->where('subject_id', $assignment->subject_id)
            ->where('school_cycle_id', $schoolCycleId)
            ->where('is_active', true)
            ->whereHas('questions', fn ($query) => $query->whereIn('id', $questionIds)->where('is_active', true))
            ->with(['questions' => fn ($query) => $query->whereIn('id', $questionIds)->where('is_active', true)])
            ->get()
            ->flatMap(fn (QuestionBank $bank) => $bank->questions->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($validQuestionIds->count() !== $questionIds->count()) {
            throw ValidationException::withMessages([
                'question_ids' => 'Solo puedes seleccionar preguntas activas de bancos del ciclo y materia de la asignacion.',
            ]);
        }

        $activeCampusId = $this->activeCampusId();
        if ($activeCampusId > 0) {
            $assignmentBelongsToCampus = TeachingAssignment::query()
                ->whereKey((int) $data['teaching_assignment_id'])
                ->whereHas('schoolCycleGroup', fn ($query) => $query->where('campus_id', $activeCampusId))
                ->exists();

            if (! $assignmentBelongsToCampus) {
                abort(403, 'La asignación seleccionada no pertenece al campus activo.');
            }
        }

        $exam = null;
        DB::transaction(function () use ($data, $questionIds, $schoolCycleId, &$exam) {
            $exam = PaperExam::create([
                'created_by' => auth()->id(),
                'teaching_assignment_id' => $data['teaching_assignment_id'],
                'school_cycle_id' => $schoolCycleId,
                'cycle_partial_id' => $data['cycle_partial_id'] ?? null,
                'title' => $data['title'],
                'instructions' => $data['instructions'] ?? null,
                'duration_minutes' => $data['duration_minutes'] ?? null,
                'is_online_enabled' => (bool) ($data['is_online_enabled'] ?? false),
                'online_available_from' => $data['online_available_from'] ?? null,
                'online_available_until' => $data['online_available_until'] ?? null,
                'online_max_attempts' => (int) ($data['online_max_attempts'] ?? 1),
                'online_show_result' => (bool) ($data['online_show_result'] ?? false),
                'is_active' => true,
            ]);

            $questionIds->each(function ($questionId, $index) use ($exam) {
                $exam->examQuestions()->create([
                    'question_id' => $questionId,
                    'sort_order' => $index + 1,
                ]);
            });
        });

        return redirect()
            ->route('coordination.paper-exams.show', $exam)
            ->with('info', 'Examen creado correctamente.');
    }

    public function show(PaperExam $paperExam)
    {
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

        return view('coordination.paper_exams.show', compact('paperExam'));
    }

    public function updateOnline(Request $request, PaperExam $paperExam)
    {
        $data = $request->validate([
            'is_online_enabled' => ['nullable', 'boolean'],
            'online_available_from' => ['nullable', 'date'],
            'online_available_until' => ['nullable', 'date', 'after:online_available_from'],
            'online_max_attempts' => ['nullable', 'integer', 'min:1', 'max:10'],
            'online_show_result' => ['nullable', 'boolean'],
        ]);

        $paperExam->update([
            'is_online_enabled' => (bool) ($data['is_online_enabled'] ?? false),
            'online_available_from' => $data['online_available_from'] ?? null,
            'online_available_until' => $data['online_available_until'] ?? null,
            'online_max_attempts' => (int) ($data['online_max_attempts'] ?? 1),
            'online_show_result' => (bool) ($data['online_show_result'] ?? false),
        ]);

        return redirect()
            ->route('coordination.paper-exams.show', $paperExam)
            ->with('info', 'Configuración de examen en línea actualizada.');
    }

    public function pdf(PaperExam $paperExam)
    {
        $paperExam->load([
            'assignment.group',
            'assignment.subject',
            'partial',
            'schoolCycle',
            'examQuestions.question.options',
            'examQuestions.question.matchingPairs',
            'examQuestions.question.fillBlanks',
        ]);

        return SnappyPdf::loadView('coordination.paper_exams.pdf', compact('paperExam'))
            ->setPaper('letter')
            ->setOption('encoding', 'UTF-8')
            ->setOption('enable-local-file-access', true)
            ->download('EXAMEN_'.$paperExam->id.'.pdf');
    }

    private function applyCampusFilterToCycleQuery($query, int $activeCampusId): void
    {
        $query->where(function ($nested) use ($activeCampusId) {
            $nested->where('campus_id', $activeCampusId)
                ->orWhereHas('campuses', fn ($campuses) => $campuses->where('campuses.id', $activeCampusId));
        });
    }

    private function applyCampusFilterToAssignmentQuery($query, int $activeCampusId): void
    {
        $query->whereHas('schoolCycleGroup', fn ($cycleGroup) => $cycleGroup->where('campus_id', $activeCampusId));
    }

    private function applyCampusFilterToExamQuery($query, int $activeCampusId): void
    {
        $query->whereHas('assignment.schoolCycleGroup', fn ($cycleGroup) => $cycleGroup->where('campus_id', $activeCampusId));
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

    private function partialForDate(int $schoolCycleId, Carbon $examDate): ?CyclePartial
    {
        return CyclePartial::query()
            ->where('school_cycle_id', $schoolCycleId)
            ->whereDate('start_date', '<=', $examDate->toDateString())
            ->whereDate('end_date', '>=', $examDate->toDateString())
            ->orderBy('sort_order')
            ->first();
    }

    private function scheduledExamTitle(Schedule $schedule, ?CyclePartial $partial, Carbon $examDate): string
    {
        $subject = $schedule->assignment?->subject?->name ?: 'Materia';
        $group = $schedule->assignment?->group?->name ?: 'Grupo';
        $partialName = $partial?->name ?: 'Examen';

        return "{$partialName} - {$subject} - Grupo {$group} - " . $examDate->format('d/m/Y');
    }

    private function buildExamScheduleCalendar(SchoolCycle $activeCycle, SchoolCycleGroup $selectedCycleGroup): array
    {
        $schedules = Schedule::query()
            ->with(['assignment.group', 'assignment.subject', 'assignment.teacher.user'])
            ->where('is_active', true)
            ->where('school_cycle_id', $activeCycle->id)
            ->whereHas('assignment', fn ($query) => $query->where('school_cycle_group_id', $selectedCycleGroup->id))
            ->get();

        $timeSlots = $schedules
            ->map(function (Schedule $schedule) {
                $start = substr((string) $schedule->start_time, 0, 5);
                $end = substr((string) $schedule->end_time, 0, 5);

                return [
                    'key' => $start . '-' . $end,
                    'start' => $start,
                    'end' => $end,
                ];
            })
            ->unique('key')
            ->sortBy('start')
            ->values();

        $matrix = [];
        foreach ($timeSlots as $slot) {
            foreach (array_keys(self::DAY_OPTIONS) as $dayKey) {
                $matrix[$slot['key']][$dayKey] = $schedules
                    ->filter(function (Schedule $schedule) use ($slot, $dayKey) {
                        return $schedule->day_of_week === $dayKey
                            && substr((string) $schedule->start_time, 0, 5) === $slot['start']
                            && substr((string) $schedule->end_time, 0, 5) === $slot['end'];
                    })
                    ->sortBy(fn (Schedule $schedule) => (int) ($schedule->section_number ?: ($schedule->assignment?->section_number ?? 1)))
                    ->values();
            }
        }

        return [
            'schedules' => $schedules,
            'timeSlots' => $timeSlots,
            'matrix' => $matrix,
        ];
    }
}
