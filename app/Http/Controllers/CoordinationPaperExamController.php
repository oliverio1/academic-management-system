<?php

namespace App\Http\Controllers;

use App\Models\PaperExam;
use App\Models\QuestionBank;
use App\Models\SchoolCycle;
use App\Models\TeachingAssignment;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CoordinationPaperExamController extends Controller
{
    public function index()
    {
        $exams = PaperExam::query()
            ->with(['assignment.group', 'assignment.subject', 'partial', 'schoolCycle'])
            ->withCount('examQuestions')
            ->orderByDesc('id')
            ->get();

        return view('coordination.paper_exams.index', compact('exams'));
    }

    public function create()
    {
        $activeCampusId = (int) session('active_campus_id', 0);
        $activeCycle = SchoolCycle::query()
            ->where('is_active', true)
            ->when($activeCampusId > 0, fn ($q) => $this->applyCampusFilterToCycleQuery($q, $activeCampusId))
            ->orderByDesc('start_date')
            ->first();

        $assignments = TeachingAssignment::query()
            ->with(['group', 'subject'])
            ->when(
                $activeCycle,
                fn ($q) => $q->whereHas('schoolCycleGroup', function ($sq) use ($activeCycle, $activeCampusId) {
                    $sq->where('school_cycle_id', $activeCycle->id)
                        ->when($activeCampusId > 0, fn ($nested) => $nested->where('campus_id', $activeCampusId));
                })
            )
            ->where('is_active', true)
            ->orderBy('group_id')
            ->get();

        $banks = QuestionBank::query()
            ->with(['subject', 'teacher.user', 'partial', 'questions'])
            ->where('is_active', true)
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

        $exam = null;
        DB::transaction(function () use ($data, &$exam) {
            $exam = PaperExam::create([
                'created_by' => auth()->id(),
                'teaching_assignment_id' => $data['teaching_assignment_id'],
                'school_cycle_id' => $data['school_cycle_id'] ?? null,
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

            collect($data['question_ids'])
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->each(function ($questionId, $index) use ($exam) {
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
}
