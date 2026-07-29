<?php

namespace App\Http\Controllers;

use App\Models\CyclePartial;
use App\Models\PaperExam;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionFillBlank;
use App\Models\QuestionMatchingPair;
use App\Models\QuestionOption;
use App\Models\SchoolCycle;
use App\Models\TeachingAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Services\CurrentSchoolCycle;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class TeacherQuestionBankController extends Controller
{
    public function index(Request $request)
    {
        $teacher = auth()->user()->teacher;
        abort_if(! $teacher, 403);
        $activeCycle = $this->activeCycle();
        $partials = $activeCycle
            ? CyclePartial::query()
                ->where('school_cycle_id', (int) $activeCycle->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
            : collect();
        $selectedPartialId = (int) $request->query('cycle_partial_id', 0);

        if ($selectedPartialId > 0 && ! $partials->pluck('id')->contains($selectedPartialId)) {
            $selectedPartialId = 0;
        }

        $banks = QuestionBank::query()
            ->where('teacher_id', $teacher->id)
            ->when($activeCycle, fn ($query) => $query->where('school_cycle_id', $activeCycle->id))
            ->when(! $activeCycle, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($selectedPartialId > 0, fn ($query) => $query->where('cycle_partial_id', $selectedPartialId))
            ->with(['subject', 'schoolCycle', 'partial'])
            ->withCount('questions')
            ->orderByRaw('cycle_partial_id is null')
            ->orderBy(
                CyclePartial::select('sort_order')
                    ->whereColumn('cycle_partials.id', 'question_banks.cycle_partial_id')
                    ->limit(1)
            )
            ->orderByDesc('id')
            ->get();

        return view('teacher.question_banks.index', compact('banks', 'partials', 'selectedPartialId', 'activeCycle'));
    }

    public function create(Request $request)
    {
        $teacher = auth()->user()->teacher;
        abort_if(! $teacher, 403);

        $cycles = $this->teacherCycles((int) $teacher->id);
        $activeCycle = $this->selectedTeacherCycle($request, $cycles);
        $subjects = $activeCycle
            ? $this->teacherSubjectsForCycle((int) $teacher->id, (int) $activeCycle->id)
            : collect();

        $partials = CyclePartial::query()
            ->when($activeCycle, fn ($query) => $query->where('school_cycle_id', $activeCycle->id))
            ->when(! $activeCycle, fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('sort_order')
            ->get();

        return view('teacher.question_banks.create', compact('subjects', 'cycles', 'partials', 'activeCycle'));
    }

    public function store(Request $request)
    {
        $teacher = auth()->user()->teacher;
        abort_if(! $teacher, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'school_cycle_id' => ['nullable', 'integer', 'exists:school_cycles,id'],
            'cycle_partial_id' => ['required', 'integer', 'exists:cycle_partials,id'],
        ]);

        $partial = CyclePartial::query()->find((int) $data['cycle_partial_id']);
        $data['school_cycle_id'] = $partial?->school_cycle_id ?? ($data['school_cycle_id'] ?? null);

        $cycles = $this->teacherCycles((int) $teacher->id);
        $selectedCycle = $cycles->firstWhere('id', (int) ($data['school_cycle_id'] ?? 0));

        if (! $selectedCycle) {
            throw ValidationException::withMessages([
                'school_cycle_id' => 'Selecciona un ciclo en el que tengas asignaciones activas.',
            ]);
        }

        $partialBelongsToCycle = CyclePartial::query()
            ->whereKey((int) $data['cycle_partial_id'])
            ->where('school_cycle_id', $selectedCycle->id)
            ->exists();

        if (! $partialBelongsToCycle) {
            throw ValidationException::withMessages([
                'cycle_partial_id' => 'El parcial seleccionado no pertenece al ciclo elegido.',
            ]);
        }

        $subjectBelongsToCycle = TeachingAssignment::query()
            ->where('teacher_id', $teacher->id)
            ->where('subject_id', (int) $data['subject_id'])
            ->where('is_active', true)
            ->whereHas('subject', fn ($query) => $query->where('is_active', true))
            ->whereHas('schoolCycleGroup', fn ($query) => $query->where('school_cycle_id', $selectedCycle->id)->where('is_active', true))
            ->exists();

        if (! $subjectBelongsToCycle) {
            throw ValidationException::withMessages([
                'subject_id' => 'La materia seleccionada no pertenece a tus asignaciones del ciclo elegido.',
            ]);
        }

        $bank = QuestionBank::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $data['subject_id'],
            'school_cycle_id' => $selectedCycle->id,
                'cycle_partial_id' => (int) $data['cycle_partial_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => true,
        ]);

        return redirect()
            ->route('teacher.question-banks.show', $bank)
            ->with('info', 'Banco creado. Ahora agrega preguntas.');
    }

    public function edit(Request $request, QuestionBank $questionBank)
    {
        $teacher = auth()->user()->teacher;
        abort_if(! $teacher || (int) $questionBank->teacher_id !== (int) $teacher->id, 403);

        $cycles = $this->teacherCycles((int) $teacher->id);
        $selectedCycleId = (int) $request->query('school_cycle_id', $questionBank->school_cycle_id);
        $activeCycle = $cycles->firstWhere('id', $selectedCycleId) ?: $questionBank->schoolCycle ?: $cycles->first();
        $subjects = $activeCycle
            ? $this->teacherSubjectsForCycle((int) $teacher->id, (int) $activeCycle->id)
            : collect();
        $partials = $activeCycle
            ? CyclePartial::query()
                ->where('school_cycle_id', (int) $activeCycle->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
            : collect();

        $questionBank->load(['subject', 'schoolCycle', 'partial']);

        return view('teacher.question_banks.edit', compact('questionBank', 'subjects', 'cycles', 'partials', 'activeCycle'));
    }

    public function update(Request $request, QuestionBank $questionBank)
    {
        $teacher = auth()->user()->teacher;
        abort_if(! $teacher || (int) $questionBank->teacher_id !== (int) $teacher->id, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'school_cycle_id' => ['required', 'integer', 'exists:school_cycles,id'],
            'cycle_partial_id' => ['required', 'integer', 'exists:cycle_partials,id'],
        ]);

        $cycles = $this->teacherCycles((int) $teacher->id);
        $selectedCycle = $cycles->firstWhere('id', (int) $data['school_cycle_id']);
        if (! $selectedCycle) {
            throw ValidationException::withMessages([
                'school_cycle_id' => 'Selecciona un ciclo en el que tengas asignaciones activas.',
            ]);
        }

        $partialBelongsToCycle = CyclePartial::query()
            ->whereKey((int) $data['cycle_partial_id'])
            ->where('school_cycle_id', (int) $selectedCycle->id)
            ->exists();

        if (! $partialBelongsToCycle) {
            throw ValidationException::withMessages([
                'cycle_partial_id' => 'El parcial seleccionado no pertenece al ciclo elegido.',
            ]);
        }

        $subjectBelongsToCycle = TeachingAssignment::query()
            ->where('teacher_id', $teacher->id)
            ->where('subject_id', (int) $data['subject_id'])
            ->where('is_active', true)
            ->whereHas('subject', fn ($query) => $query->where('is_active', true))
            ->whereHas('schoolCycleGroup', fn ($query) => $query->where('school_cycle_id', $selectedCycle->id)->where('is_active', true))
            ->exists();

        if (! $subjectBelongsToCycle) {
            throw ValidationException::withMessages([
                'subject_id' => 'La materia seleccionada no pertenece a tus asignaciones del ciclo elegido.',
            ]);
        }

        $hasConfiguredExam = PaperExam::query()
            ->where('school_cycle_id', (int) $questionBank->school_cycle_id)
            ->where('cycle_partial_id', (int) $questionBank->cycle_partial_id)
            ->whereHas('examQuestions.question', fn ($query) => $query->where('question_bank_id', (int) $questionBank->id))
            ->exists();

        if ($hasConfiguredExam && (
            (int) $questionBank->subject_id !== (int) $data['subject_id']
            || (int) $questionBank->school_cycle_id !== (int) $selectedCycle->id
            || (int) $questionBank->cycle_partial_id !== (int) $data['cycle_partial_id']
        )) {
            return back()
                ->withInput()
                ->withErrors(['cycle_partial_id' => 'Este banco ya esta ligado a un examen configurado. Solo puedes cambiar nombre y descripcion.']);
        }

        $questionBank->update([
            'subject_id' => (int) $data['subject_id'],
            'school_cycle_id' => (int) $selectedCycle->id,
            'cycle_partial_id' => (int) $data['cycle_partial_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        return redirect()
            ->route('teacher.question-banks.show', $questionBank)
            ->with('info', 'Banco actualizado correctamente.');
    }

    public function destroy(QuestionBank $questionBank)
    {
        $teacher = auth()->user()->teacher;
        abort_if(! $teacher || (int) $questionBank->teacher_id !== (int) $teacher->id, 403);

        $hasConfiguredExam = PaperExam::query()
            ->whereHas('examQuestions.question', fn ($query) => $query->where('question_bank_id', (int) $questionBank->id))
            ->exists();

        if ($hasConfiguredExam) {
            return back()->withErrors(['bank' => 'No se puede eliminar un banco que ya esta ligado a un examen configurado.']);
        }

        $questionBank->delete();

        return redirect()
            ->route('teacher.question-banks.index')
            ->with('info', 'Banco eliminado correctamente.');
    }

    private function teacherCycles(int $teacherId)
    {
        $activeCampusId = $this->activeCampusId();
        $activeCycle = $this->activeCycle();

        return SchoolCycle::query()
            ->where('is_active', true)
            ->when($activeCycle, fn ($query) => $query->whereKey($activeCycle->id))
            ->when(! $activeCycle, fn ($query) => $query->whereRaw('1 = 0'))
            ->whereHas('cycleGroups', function ($query) use ($teacherId, $activeCampusId) {
                $query->where('is_active', true)
                    ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId))
                    ->whereHas('assignments', function ($assignmentQuery) use ($teacherId) {
                        $assignmentQuery->where('teacher_id', $teacherId)
                            ->where('is_active', true);
                    });
            })
            ->orderByDesc('start_date')
            ->orderBy('name')
            ->get();
    }

    private function selectedTeacherCycle(Request $request, $cycles): ?SchoolCycle
    {
        $selectedCycleId = (int) $request->query('school_cycle_id', old('school_cycle_id', 0));

        return $cycles->firstWhere('id', $selectedCycleId) ?: $cycles->first();
    }

    private function teacherSubjectsForCycle(int $teacherId, int $schoolCycleId)
    {
        return TeachingAssignment::query()
            ->with('subject')
            ->where('teacher_id', $teacherId)
            ->where('is_active', true)
            ->whereHas('subject', fn ($query) => $query->where('is_active', true))
            ->whereHas('schoolCycleGroup', fn ($query) => $query->where('school_cycle_id', $schoolCycleId)->where('is_active', true))
            ->get()
            ->pluck('subject')
            ->filter()
            ->unique('id')
            ->sortBy(fn ($subject) => mb_strtolower((string) $subject->name))
            ->values();
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

    private function activeCycle(): ?SchoolCycle
    {
        $activeCampusId = $this->activeCampusId();

        return app(CurrentSchoolCycle::class)->get(auth()->user(), $activeCampusId);
    }

    public function show(QuestionBank $questionBank)
    {
        $teacher = auth()->user()->teacher;
        abort_if(! $teacher || (int) $questionBank->teacher_id !== (int) $teacher->id, 403);

        $questionBank->load([
            'subject',
            'schoolCycle',
            'partial',
            'questions.options',
            'questions.matchingPairs',
            'questions.fillBlanks',
        ]);

        return view('teacher.question_banks.show', compact('questionBank'));
    }

    public function configureExam(QuestionBank $questionBank)
    {
        $teacher = auth()->user()->teacher;
        abort_if(! $teacher || (int) $questionBank->teacher_id !== (int) $teacher->id, 403);

        if (! $questionBank->cycle_partial_id) {
            return redirect()
                ->route('teacher.question-banks.edit', $questionBank)
                ->withErrors(['cycle_partial_id' => 'Asigna un parcial al banco antes de configurar el examen.']);
        }

        $questionBank->load([
            'subject',
            'partial',
            'questions' => fn ($query) => $query
                ->where('is_active', true)
                ->orderBy('sort_order'),
        ]);

        $exams = PaperExam::query()
            ->with([
                'assignment.group',
                'assignment.subject',
                'partial',
                'examQuestions',
            ])
            ->withCount('examQuestions')
            ->where('is_active', true)
            ->whereHas('assignment', function ($query) use ($teacher, $questionBank) {
                $query->where('teacher_id', $teacher->id)
                    ->where('subject_id', $questionBank->subject_id);
            })
            ->when($questionBank->cycle_partial_id, fn ($query) => $query->where('cycle_partial_id', $questionBank->cycle_partial_id))
            ->when($questionBank->school_cycle_id, fn ($query) => $query->where('school_cycle_id', $questionBank->school_cycle_id))
            ->orderByDesc('online_available_from')
            ->orderByDesc('id')
            ->get();

        $eligibleAssignments = $this->eligibleAssignmentsForBank($teacher->id, $questionBank);
        $examTargets = $this->examTargetsForBank($eligibleAssignments, $exams, $questionBank);

        return view('teacher.question_banks.configure_exam', compact('questionBank', 'exams', 'examTargets'));
    }

    public function updateExamConfiguration(Request $request, QuestionBank $questionBank)
    {
        $teacher = auth()->user()->teacher;
        abort_if(! $teacher || (int) $questionBank->teacher_id !== (int) $teacher->id, 403);

        if (! $questionBank->cycle_partial_id) {
            return redirect()
                ->route('teacher.question-banks.edit', $questionBank)
                ->withErrors(['cycle_partial_id' => 'Asigna un parcial al banco antes de configurar el examen.']);
        }

        $data = $request->validate([
            'exam_target' => ['required', 'string', 'max:80'],
            'apply_scope' => ['nullable', 'in:selected,all_groups'],
            'question_ids' => ['required', 'array', 'min:1'],
            'question_ids.*' => ['integer', 'exists:questions,id'],
        ]);

        $questionIds = collect($data['question_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $validQuestionIds = Question::query()
            ->where('question_bank_id', $questionBank->id)
            ->where('is_active', true)
            ->whereIn('id', $questionIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        if ($validQuestionIds->count() !== $questionIds->count()) {
            return back()
                ->withInput()
                ->withErrors(['question_ids' => 'Solo puedes seleccionar preguntas activas de este banco.']);
        }

        $targets = collect([$data['exam_target']]);

        if (($data['apply_scope'] ?? 'selected') === 'all_groups') {
            $targets = $this->examTargetsForBank(
                $this->eligibleAssignmentsForBank($teacher->id, $questionBank),
                PaperExam::query()
                    ->with(['assignment.group', 'assignment.subject', 'partial', 'examQuestions'])
                    ->withCount('examQuestions')
                    ->where('is_active', true)
                    ->whereHas('assignment', function ($query) use ($teacher, $questionBank) {
                        $query->where('teacher_id', $teacher->id)
                            ->where('subject_id', $questionBank->subject_id);
                    })
                    ->when($questionBank->cycle_partial_id, fn ($query) => $query->where('cycle_partial_id', $questionBank->cycle_partial_id))
                    ->when($questionBank->school_cycle_id, fn ($query) => $query->where('school_cycle_id', $questionBank->school_cycle_id))
                    ->get(),
                $questionBank
            )->pluck('value');
        }

        $paperExams = $targets
            ->map(fn ($target) => $this->resolveExamTarget((string) $target, $teacher->id, $questionBank))
            ->unique('id')
            ->values();

        $lockedExam = $paperExams->first(fn (PaperExam $exam) => $exam->attempts()->exists());
        if ($lockedExam) {
            return back()
                ->withInput()
                ->withErrors(['question_ids' => "No se pueden modificar preguntas porque el examen {$lockedExam->title} ya tiene intentos registrados."]);
        }

        DB::transaction(function () use ($paperExams, $questionIds) {
            $paperExams->each(function (PaperExam $paperExam) use ($questionIds) {
                $paperExam->examQuestions()->delete();

                $questionIds->each(function ($questionId, $index) use ($paperExam) {
                    $paperExam->examQuestions()->create([
                        'question_id' => $questionId,
                        'sort_order' => $index + 1,
                    ]);
                });
            });
        });

        return redirect()
            ->route('teacher.question-banks.exam.configure', $questionBank)
            ->with('info', 'Preguntas configuradas correctamente en ' . $paperExams->count() . ' examen(es).');
    }

    private function eligibleAssignmentsForBank(int $teacherId, QuestionBank $questionBank)
    {
        return TeachingAssignment::query()
            ->with(['group', 'subject', 'schoolCycleGroup'])
            ->where('teacher_id', $teacherId)
            ->where('subject_id', $questionBank->subject_id)
            ->where('is_active', true)
            ->whereHas('subject', fn ($query) => $query->where('is_active', true))
            ->whereHas('schoolCycleGroup', function ($query) use ($questionBank) {
                $query->where('is_active', true)
                    ->where('school_cycle_id', $questionBank->school_cycle_id)
                    ->when($this->activeCampusId() > 0, fn ($q) => $q->where('campus_id', $this->activeCampusId()));
            })
            ->whereHas('schedules', function ($query) use ($questionBank) {
                $query->where('is_active', true)
                    ->where('school_cycle_id', $questionBank->school_cycle_id);
            })
            ->orderBy('group_id')
            ->orderBy('section_number')
            ->get()
            ->unique(fn (TeachingAssignment $assignment) => (int) $assignment->group_id)
            ->values();
    }

    private function examTargetsForBank($eligibleAssignments, $exams, QuestionBank $questionBank)
    {
        $examsByGroup = $exams
            ->filter(fn (PaperExam $exam) => $exam->assignment)
            ->groupBy(fn (PaperExam $exam) => (int) $exam->assignment->group_id)
            ->map(fn ($groupExams) => $groupExams->sortByDesc('id')->first());

        return $eligibleAssignments->map(function (TeachingAssignment $assignment) use ($examsByGroup, $questionBank) {
            $exam = $examsByGroup->get((int) $assignment->group_id);

            if ($exam) {
                return [
                    'value' => 'exam:' . $exam->id,
                    'label' => $exam->title
                        . ' / Grupo ' . ($assignment->group->name ?? 'N/D')
                        . ($exam->online_available_from ? ' / ' . $exam->online_available_from->format('d/m/Y H:i') : '')
                        . ' / ' . $exam->exam_questions_count . ' pregunta(s)',
                    'question_ids' => $exam->examQuestions->pluck('question_id')->map(fn ($id) => (int) $id)->values()->all(),
                    'is_new' => false,
                ];
            }

            $title = $this->defaultExamTitle($assignment, $questionBank);

            return [
                'value' => 'new:' . $assignment->id,
                'label' => $title . ' / Crear nuevo',
                'question_ids' => [],
                'is_new' => true,
            ];
        })->values();
    }

    private function resolveExamTarget(string $target, int $teacherId, QuestionBank $questionBank): PaperExam
    {
        if (str_starts_with($target, 'exam:')) {
            $paperExamId = (int) str_replace('exam:', '', $target);

            return PaperExam::query()
                ->with('assignment')
                ->whereKey($paperExamId)
                ->whereHas('assignment', function ($query) use ($teacherId, $questionBank) {
                    $query->where('teacher_id', $teacherId)
                        ->where('subject_id', $questionBank->subject_id);
                })
                ->when($questionBank->cycle_partial_id, fn ($query) => $query->where('cycle_partial_id', $questionBank->cycle_partial_id))
                ->when($questionBank->school_cycle_id, fn ($query) => $query->where('school_cycle_id', $questionBank->school_cycle_id))
                ->firstOrFail();
        }

        if (! str_starts_with($target, 'new:')) {
            throw ValidationException::withMessages([
                'exam_target' => 'Selecciona un examen valido.',
            ]);
        }

        $assignmentId = (int) str_replace('new:', '', $target);
        $assignment = $this->eligibleAssignmentsForBank($teacherId, $questionBank)
            ->firstWhere('id', $assignmentId);

        if (! $assignment) {
            throw ValidationException::withMessages([
                'exam_target' => 'La asignacion seleccionada no pertenece al ciclo activo.',
            ]);
        }

        $paperExam = PaperExam::query()
            ->where('school_cycle_id', $questionBank->school_cycle_id)
            ->where('cycle_partial_id', $questionBank->cycle_partial_id)
            ->whereHas('assignment', function ($query) use ($assignment, $teacherId, $questionBank) {
                $query->where('teacher_id', $teacherId)
                    ->where('subject_id', $questionBank->subject_id)
                    ->where('group_id', $assignment->group_id);
            })
            ->orderByDesc('id')
            ->first();

        if (! $paperExam) {
            $paperExam = PaperExam::create([
                'created_by' => auth()->id(),
                'teaching_assignment_id' => $assignment->id,
                'school_cycle_id' => $questionBank->school_cycle_id,
                'cycle_partial_id' => $questionBank->cycle_partial_id,
                'title' => $this->defaultExamTitle($assignment, $questionBank),
                'instructions' => null,
                'duration_minutes' => 50,
                'is_online_enabled' => false,
                'online_available_from' => null,
                'online_available_until' => null,
                'online_max_attempts' => 1,
                'online_show_result' => false,
                'is_active' => true,
            ]);
        }

        if (! $paperExam->is_active) {
            $paperExam->update([
                'is_active' => true,
                'title' => $paperExam->title ?: $this->defaultExamTitle($assignment, $questionBank),
                'duration_minutes' => $paperExam->duration_minutes ?: 50,
                'online_max_attempts' => $paperExam->online_max_attempts ?: 1,
            ]);
        }

        return $paperExam;
    }

    private function defaultExamTitle(TeachingAssignment $assignment, QuestionBank $questionBank): string
    {
        $partial = $questionBank->partial?->name ?: 'Examen';
        $subject = $assignment->subject?->name ?: $questionBank->subject?->name ?: 'Materia';
        $group = $assignment->group?->name ?: 'Grupo';

        return "{$partial} - {$subject} - Grupo {$group}";
    }

    public function storeQuestion(Request $request, QuestionBank $questionBank)
    {
        $teacher = auth()->user()->teacher;
        abort_if(! $teacher || (int) $questionBank->teacher_id !== (int) $teacher->id, 403);

        $data = $request->validate([
            'type' => ['required', 'in:open,multiple_choice,matching,fill_blank'],
            'prompt' => ['required', 'string'],
            'points' => ['required', 'numeric', 'min:0.1', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
            'support_title' => ['nullable', 'string', 'max:150'],
            'support_text' => ['nullable', 'string'],
            'support_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'support_image_url' => ['nullable', 'url', 'max:2048'],
            'options' => ['nullable', 'array'],
            'options.*.text' => ['nullable', 'string', 'max:500'],
            'options.*.is_correct' => ['nullable', 'boolean'],
            'pairs' => ['nullable', 'array'],
            'pairs.*.left' => ['nullable', 'string', 'max:500'],
            'pairs.*.right' => ['nullable', 'string', 'max:500'],
            'blanks' => ['nullable', 'array'],
            'blanks.*' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($questionBank, $data, $request) {
            $question = Question::create([
                'question_bank_id' => $questionBank->id,
                'type' => $data['type'],
                'prompt' => $data['prompt'],
                'points' => $data['points'],
                'sort_order' => $data['sort_order'] ?? ((int) $questionBank->questions()->max('sort_order') + 1),
                'is_active' => true,
                'meta' => $this->supportMetaFromRequest($request),
            ]);

            if ($data['type'] === 'multiple_choice') {
                collect($data['options'] ?? [])
                    ->filter(fn ($option) => ! empty(trim((string) ($option['text'] ?? ''))))
                    ->values()
                    ->each(function ($option, $index) use ($question) {
                        QuestionOption::create([
                            'question_id' => $question->id,
                            'option_text' => trim((string) $option['text']),
                            'is_correct' => (bool) ($option['is_correct'] ?? false),
                            'sort_order' => $index + 1,
                        ]);
                    });
            }

            if ($data['type'] === 'matching') {
                collect($data['pairs'] ?? [])
                    ->filter(function ($pair) {
                        return ! empty(trim((string) ($pair['left'] ?? '')))
                            && ! empty(trim((string) ($pair['right'] ?? '')));
                    })
                    ->values()
                    ->each(function ($pair, $index) use ($question) {
                        QuestionMatchingPair::create([
                            'question_id' => $question->id,
                            'left_text' => trim((string) $pair['left']),
                            'right_text' => trim((string) $pair['right']),
                            'sort_order' => $index + 1,
                        ]);
                    });
            }

            if ($data['type'] === 'fill_blank') {
                collect($data['blanks'] ?? [])
                    ->filter(fn ($blank) => ! empty(trim((string) $blank)))
                    ->values()
                    ->each(function ($blank, $index) use ($question) {
                        QuestionFillBlank::create([
                            'question_id' => $question->id,
                            'expected_answer' => trim((string) $blank),
                            'sort_order' => $index + 1,
                        ]);
                    });
            }
        });

        return redirect()
            ->route('teacher.question-banks.show', $questionBank)
            ->with('info', 'Pregunta agregada correctamente.');
    }

    public function destroyQuestion(QuestionBank $questionBank, Question $question)
    {
        $teacher = auth()->user()->teacher;
        abort_if(! $teacher || (int) $questionBank->teacher_id !== (int) $teacher->id, 403);
        abort_if((int) $question->question_bank_id !== (int) $questionBank->id, 404);

        $question->delete();

        return redirect()
            ->route('teacher.question-banks.show', $questionBank)
            ->with('info', 'Pregunta eliminada.');
    }

    public function editQuestion(QuestionBank $questionBank, Question $question)
    {
        $teacher = auth()->user()->teacher;
        abort_if(! $teacher || (int) $questionBank->teacher_id !== (int) $teacher->id, 403);
        abort_if((int) $question->question_bank_id !== (int) $questionBank->id, 404);

        $question->load(['options', 'matchingPairs', 'fillBlanks']);

        return view('teacher.question_banks.edit_question', compact('questionBank', 'question'));
    }

    public function updateQuestion(Request $request, QuestionBank $questionBank, Question $question)
    {
        $teacher = auth()->user()->teacher;
        abort_if(! $teacher || (int) $questionBank->teacher_id !== (int) $teacher->id, 403);
        abort_if((int) $question->question_bank_id !== (int) $questionBank->id, 404);

        $data = $request->validate([
            'type' => ['required', 'in:open,multiple_choice,matching,fill_blank'],
            'prompt' => ['required', 'string'],
            'points' => ['required', 'numeric', 'min:0.1', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
            'support_title' => ['nullable', 'string', 'max:150'],
            'support_text' => ['nullable', 'string'],
            'support_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'support_image_url' => ['nullable', 'url', 'max:2048'],
            'remove_support_image' => ['nullable', 'boolean'],
            'options' => ['nullable', 'array'],
            'options.*.text' => ['nullable', 'string', 'max:500'],
            'options.*.is_correct' => ['nullable', 'boolean'],
            'pairs' => ['nullable', 'array'],
            'pairs.*.left' => ['nullable', 'string', 'max:500'],
            'pairs.*.right' => ['nullable', 'string', 'max:500'],
            'blanks' => ['nullable', 'array'],
            'blanks.*' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($question, $data, $request) {
            $question->update([
                'type' => $data['type'],
                'prompt' => $data['prompt'],
                'points' => $data['points'],
                'sort_order' => $data['sort_order'] ?? $question->sort_order,
                'meta' => $this->supportMetaFromRequest($request, $question),
            ]);

            $question->options()->delete();
            $question->matchingPairs()->delete();
            $question->fillBlanks()->delete();

            if ($data['type'] === 'multiple_choice') {
                collect($data['options'] ?? [])
                    ->filter(fn ($option) => ! empty(trim((string) ($option['text'] ?? ''))))
                    ->values()
                    ->each(function ($option, $index) use ($question) {
                        QuestionOption::create([
                            'question_id' => $question->id,
                            'option_text' => trim((string) $option['text']),
                            'is_correct' => (bool) ($option['is_correct'] ?? false),
                            'sort_order' => $index + 1,
                        ]);
                    });
            }

            if ($data['type'] === 'matching') {
                collect($data['pairs'] ?? [])
                    ->filter(function ($pair) {
                        return ! empty(trim((string) ($pair['left'] ?? '')))
                            && ! empty(trim((string) ($pair['right'] ?? '')));
                    })
                    ->values()
                    ->each(function ($pair, $index) use ($question) {
                        QuestionMatchingPair::create([
                            'question_id' => $question->id,
                            'left_text' => trim((string) $pair['left']),
                            'right_text' => trim((string) $pair['right']),
                            'sort_order' => $index + 1,
                        ]);
                    });
            }

            if ($data['type'] === 'fill_blank') {
                collect($data['blanks'] ?? [])
                    ->filter(fn ($blank) => ! empty(trim((string) $blank)))
                    ->values()
                    ->each(function ($blank, $index) use ($question) {
                        QuestionFillBlank::create([
                            'question_id' => $question->id,
                            'expected_answer' => trim((string) $blank),
                            'sort_order' => $index + 1,
                        ]);
                    });
            }
        });

        return redirect()
            ->route('teacher.question-banks.show', $questionBank)
            ->with('info', 'Pregunta actualizada correctamente.');
    }

    public function previewQuestion(QuestionBank $questionBank, Question $question)
    {
        $teacher = auth()->user()->teacher;
        abort_if(! $teacher || (int) $questionBank->teacher_id !== (int) $teacher->id, 403);
        abort_if((int) $question->question_bank_id !== (int) $questionBank->id, 404);

        $question->load(['options', 'matchingPairs', 'fillBlanks']);

        return view('teacher.question_banks.preview_question', compact('questionBank', 'question'));
    }

    public function downloadTemplate(?QuestionBank $questionBank = null)
    {
        $teacher = auth()->user()->teacher;
        abort_if(! $teacher, 403);
        if ($questionBank && (int) $questionBank->teacher_id !== (int) $teacher->id) {
            abort(403);
        }
        $questionBank?->loadMissing(['subject', 'schoolCycle', 'partial']);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Preguntas');
        $sheet->setCellValue('A1', 'tipo');
        $sheet->setCellValue('B1', 'enunciado');
        $sheet->setCellValue('C1', 'puntos');
        $sheet->setCellValue('D1', 'orden');
        $sheet->setCellValue('E1', 'opciones');
        $sheet->setCellValue('F1', 'correctas');
        $sheet->setCellValue('G1', 'pares_relacion');
        $sheet->setCellValue('H1', 'respuestas_completado');
        $sheet->setCellValue('I1', 'titulo_apoyo');
        $sheet->setCellValue('J1', 'texto_apoyo');
        $sheet->setCellValue('K1', 'imagen_apoyo_url');

        $sheet->setCellValue('A2', 'multiple_choice');
        $sheet->setCellValue('B2', '¿Capital de México?');
        $sheet->setCellValue('C2', '1');
        $sheet->setCellValue('D2', '1');
        $sheet->setCellValue('E2', 'CDMX|Guadalajara|Monterrey');
        $sheet->setCellValue('F2', '1');

        $sheet->setCellValue('A3', 'matching');
        $sheet->setCellValue('B3', 'Relaciona país y capital');
        $sheet->setCellValue('C3', '2');
        $sheet->setCellValue('D3', '2');
        $sheet->setCellValue('G3', 'México=>CDMX|Francia=>París');

        $sheet->setCellValue('A4', 'fill_blank');
        $sheet->setCellValue('B4', 'Completa: La fórmula del agua es ____');
        $sheet->setCellValue('C4', '1');
        $sheet->setCellValue('D4', '3');
        $sheet->setCellValue('H4', 'H2O');

        $sheet->setCellValue('A5', 'open');
        $sheet->setCellValue('B5', 'Explica la fotosíntesis.');
        $sheet->setCellValue('C5', '3');
        $sheet->setCellValue('D5', '4');

        $sheet->setCellValue('I5', 'Lectura de apoyo');
        $sheet->setCellValue('J5', "Lee el siguiente fragmento y responde con tus propias palabras.\nPuedes pegar aqui textos largos de 2 o 3 cuartillas.");

        $sheet->setCellValue('A6', 'open');
        $sheet->setCellValue('B6', 'Observa la imagen y describe sus elementos principales.');
        $sheet->setCellValue('C6', '3');
        $sheet->setCellValue('D6', '5');
        $sheet->setCellValue('I6', 'Imagen de apoyo');
        $sheet->setCellValue('K6', 'https://ejemplo.com/imagen.jpg');

        foreach (range('A', 'K') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $sheet->getColumnDimension('B')->setWidth(60);
        $sheet->getColumnDimension('J')->setWidth(80);
        $sheet->getStyle('B:J')->getAlignment()->setWrapText(true);

        $context = $spreadsheet->createSheet();
        $context->setTitle('Contexto');
        $context->setCellValue('A1', 'Campo');
        $context->setCellValue('B1', 'Valor');
        $context->setCellValue('A2', 'Materia');
        $context->setCellValue('B2', $questionBank?->subject?->name ?? 'Selecciona el banco antes de importar');
        $context->setCellValue('A3', 'Ciclo');
        $context->setCellValue('B3', $questionBank?->schoolCycle?->name ?? 'Selecciona el banco antes de importar');
        $context->setCellValue('A4', 'Parcial');
        $context->setCellValue('B4', $questionBank?->partial?->name ?? 'El parcial se define en el banco');
        $context->setCellValue('A6', 'Importante');
        $context->setCellValue('B6', 'El parcial NO se captura por pregunta. Todas las filas de la hoja Preguntas se importan al parcial del banco seleccionado.');
        $context->setCellValue('A7', 'Flujo recomendado');
        $context->setCellValue('B7', 'Crea o edita el banco con materia, ciclo y parcial correctos; despues descarga esta plantilla desde ese banco e importa el archivo completo.');
        $context->getColumnDimension('A')->setWidth(24);
        $context->getColumnDimension('B')->setWidth(90);
        $context->getStyle('B')->getAlignment()->setWrapText(true);
        $spreadsheet->setActiveSheetIndex(0);

        $path = storage_path('app/question_bank_template.xlsx');
        (new Xlsx($spreadsheet))->save($path);

        return response()->download($path, 'plantilla_preguntas.xlsx')->deleteFileAfterSend(true);
    }

    public function importQuestions(Request $request, QuestionBank $questionBank)
    {
        $teacher = auth()->user()->teacher;
        abort_if(! $teacher || (int) $questionBank->teacher_id !== (int) $teacher->id, 403);

        $questionBank->loadMissing(['subject', 'schoolCycle', 'partial']);
        if (! $questionBank->cycle_partial_id) {
            return redirect()
                ->route('teacher.question-banks.edit', $questionBank)
                ->withErrors(['cycle_partial_id' => 'Asigna el parcial del banco antes de importar preguntas.']);
        }

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        $spreadsheet = IOFactory::load($data['file']->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();
        $imported = 0;

        DB::transaction(function () use ($questionBank, $sheet, $highestRow, &$imported) {
            for ($row = 2; $row <= $highestRow; $row++) {
                $type = trim((string) $sheet->getCell("A{$row}")->getCalculatedValue());
                $prompt = trim((string) $sheet->getCell("B{$row}")->getCalculatedValue());
                $points = (float) $sheet->getCell("C{$row}")->getCalculatedValue();
                $sortOrder = (int) $sheet->getCell("D{$row}")->getCalculatedValue();
                $optionsRaw = trim((string) $sheet->getCell("E{$row}")->getCalculatedValue());
                $correctRaw = trim((string) $sheet->getCell("F{$row}")->getCalculatedValue());
                $pairsRaw = trim((string) $sheet->getCell("G{$row}")->getCalculatedValue());
                $blanksRaw = trim((string) $sheet->getCell("H{$row}")->getCalculatedValue());
                $supportTitle = trim((string) $sheet->getCell("I{$row}")->getCalculatedValue());
                $supportText = trim((string) $sheet->getCell("J{$row}")->getCalculatedValue());
                $supportImageUrl = trim((string) $sheet->getCell("K{$row}")->getCalculatedValue());

                if ($type === '' || $prompt === '') {
                    continue;
                }

                if (! in_array($type, ['open', 'multiple_choice', 'matching', 'fill_blank'], true)) {
                    continue;
                }

                $question = Question::create([
                    'question_bank_id' => $questionBank->id,
                    'type' => $type,
                    'prompt' => $prompt,
                    'points' => $points > 0 ? $points : 1,
                    'sort_order' => $sortOrder > 0 ? $sortOrder : ((int) $questionBank->questions()->max('sort_order') + 1),
                    'is_active' => true,
                    'meta' => $this->supportMetaFromValues($supportTitle, $supportText, $supportImageUrl),
                ]);

                if ($type === 'multiple_choice' && $optionsRaw !== '') {
                    $options = collect(explode('|', $optionsRaw))
                        ->map(fn ($value) => trim((string) $value))
                        ->filter()
                        ->values();
                    $correctIndexes = collect(explode(',', $correctRaw))
                        ->map(fn ($value) => (int) trim((string) $value))
                        ->filter(fn ($value) => $value > 0)
                        ->values();

                    $options->each(function ($optionText, $index) use ($question, $correctIndexes) {
                        QuestionOption::create([
                            'question_id' => $question->id,
                            'option_text' => $optionText,
                            'is_correct' => $correctIndexes->contains($index + 1),
                            'sort_order' => $index + 1,
                        ]);
                    });
                }

                if ($type === 'matching' && $pairsRaw !== '') {
                    collect(explode('|', $pairsRaw))
                        ->map(fn ($value) => trim((string) $value))
                        ->filter()
                        ->values()
                        ->each(function ($pair, $index) use ($question) {
                            $chunks = explode('=>', $pair, 2);
                            $left = trim((string) ($chunks[0] ?? ''));
                            $right = trim((string) ($chunks[1] ?? ''));
                            if ($left === '' || $right === '') {
                                return;
                            }
                            QuestionMatchingPair::create([
                                'question_id' => $question->id,
                                'left_text' => $left,
                                'right_text' => $right,
                                'sort_order' => $index + 1,
                            ]);
                        });
                }

                if ($type === 'fill_blank' && $blanksRaw !== '') {
                    collect(explode('|', $blanksRaw))
                        ->map(fn ($value) => trim((string) $value))
                        ->filter()
                        ->values()
                        ->each(function ($blank, $index) use ($question) {
                            QuestionFillBlank::create([
                                'question_id' => $question->id,
                                'expected_answer' => $blank,
                                'sort_order' => $index + 1,
                            ]);
                        });
                }

                $imported++;
            }
        });

        return redirect()
            ->route('teacher.question-banks.show', $questionBank)
            ->with('info', "Carga masiva completada. Preguntas importadas al parcial {$questionBank->partial?->name}: {$imported}.");
    }

    private function supportMetaFromRequest(Request $request, ?Question $question = null): ?array
    {
        $existing = is_array($question?->meta) ? $question->meta : [];
        $title = trim((string) $request->input('support_title', ''));
        $text = trim((string) $request->input('support_text', ''));
        $imageUrl = trim((string) $request->input('support_image_url', ''));
        $imagePath = (string) ($existing['support_image_path'] ?? '');
        $imageMeta = is_array($existing['support_image_meta'] ?? null) ? $existing['support_image_meta'] : [];

        if ($request->boolean('remove_support_image') && $imagePath !== '') {
            Storage::disk('public')->delete($imagePath);
            $imagePath = '';
            $imageUrl = '';
            $imageMeta = [];
        }

        if ($request->hasFile('support_image')) {
            if ($imagePath !== '') {
                Storage::disk('public')->delete($imagePath);
            }

            $optimized = $this->storeOptimizedSupportImage($request->file('support_image'));
            $imagePath = $optimized['path'];
            $imageUrl = $optimized['url'];
            $imageMeta = collect($optimized)->except(['path', 'url'])->all();
        } elseif ($imageUrl === '' && ! $request->boolean('remove_support_image')) {
            $imageUrl = (string) ($existing['support_image_url'] ?? '');
        } elseif ($imageUrl !== '' && $imagePath !== '') {
            Storage::disk('public')->delete($imagePath);
            $imagePath = '';
            $imageMeta = [];
        }

        return $this->supportMetaFromValues($title, $text, $imageUrl, $imagePath, $imageMeta);
    }

    private function storeOptimizedSupportImage(UploadedFile $file): array
    {
        $disk = Storage::disk('public');
        $directory = 'question-support';
        $mime = (string) $file->getMimeType();
        $originalSize = (int) $file->getSize();

        if ($mime === 'image/gif' || ! function_exists('imagewebp')) {
            $path = $file->store($directory, 'public');

            return [
                'path' => $path,
                'url' => '/storage/' . ltrim($path, '/'),
                'original_size' => $originalSize,
                'optimized_size' => $originalSize,
                'format' => $file->extension() ?: 'gif',
            ];
        }

        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $source) {
            $path = $file->store($directory, 'public');

            return [
                'path' => $path,
                'url' => '/storage/' . ltrim($path, '/'),
                'original_size' => $originalSize,
                'optimized_size' => $originalSize,
                'format' => $file->extension() ?: 'original',
            ];
        }

        $source = $this->orientImage($source, $file);
        $width = imagesx($source);
        $height = imagesy($source);
        $maxDimension = 1600;
        $ratio = min(1, $maxDimension / max($width, $height));
        $targetWidth = max(1, (int) round($width * $ratio));
        $targetHeight = max(1, (int) round($height * $ratio));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $transparent);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        $relativePath = $directory . '/' . Str::uuid()->toString() . '.webp';
        ob_start();
        imagewebp($canvas, null, 82);
        $contents = (string) ob_get_clean();
        $stored = $contents !== '' && $disk->put($relativePath, $contents);
        imagedestroy($source);
        imagedestroy($canvas);

        if (! $stored) {
            $path = $file->store($directory, 'public');

            return [
                'path' => $path,
                'url' => '/storage/' . ltrim($path, '/'),
                'original_size' => $originalSize,
                'optimized_size' => $originalSize,
                'format' => $file->extension() ?: 'original',
            ];
        }

        return [
            'path' => $relativePath,
            'url' => '/storage/' . ltrim($relativePath, '/'),
            'original_size' => $originalSize,
            'optimized_size' => $contents !== '' ? strlen($contents) : $originalSize,
            'width' => $targetWidth,
            'height' => $targetHeight,
            'format' => 'webp',
        ];
    }

    private function orientImage(\GdImage $image, UploadedFile $file): \GdImage
    {
        if (! function_exists('exif_read_data') || ! in_array($file->getMimeType(), ['image/jpeg', 'image/jpg'], true)) {
            return $image;
        }

        $exif = @exif_read_data($file->getRealPath());
        $orientation = (int) ($exif['Orientation'] ?? 1);

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated ?: $image;
    }

    private function supportMetaFromValues(string $title = '', string $text = '', string $imageUrl = '', string $imagePath = '', array $imageMeta = []): ?array
    {
        $meta = [
            'support_title' => trim($title),
            'support_text' => trim($text),
            'support_image_url' => trim($imageUrl),
            'support_image_path' => trim($imagePath),
            'support_image_meta' => $imageMeta,
        ];

        $meta = array_filter($meta, fn ($value) => $value !== '' && $value !== []);

        return $meta ?: null;
    }
}
