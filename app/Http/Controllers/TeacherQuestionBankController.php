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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class TeacherQuestionBankController extends Controller
{
    public function index()
    {
        $teacher = auth()->user()->teacher;
        abort_if(! $teacher, 403);

        $banks = QuestionBank::query()
            ->where('teacher_id', $teacher->id)
            ->with(['subject', 'schoolCycle', 'partial'])
            ->withCount('questions')
            ->orderByDesc('id')
            ->get();

        return view('teacher.question_banks.index', compact('banks'));
    }

    public function create()
    {
        $teacher = auth()->user()->teacher;
        abort_if(! $teacher, 403);

        $subjects = $teacher->subjects()
            ->where('subjects.is_active', true)
            ->orderBy('name')
            ->get();

        $activeCycle = SchoolCycle::query()
            ->where('is_active', true)
            ->orderByDesc('start_date')
            ->first();

        $cycles = collect($activeCycle ? [$activeCycle] : []);

        $partials = CyclePartial::query()
            ->when($activeCycle, fn ($query) => $query->where('school_cycle_id', $activeCycle->id))
            ->when(! $activeCycle, fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('sort_order')
            ->get();

        return view('teacher.question_banks.create', compact('subjects', 'cycles', 'partials'));
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
            'cycle_partial_id' => ['nullable', 'integer', 'exists:cycle_partials,id'],
        ]);

        if (! empty($data['cycle_partial_id'])) {
            $partial = CyclePartial::query()->find((int) $data['cycle_partial_id']);
            $data['school_cycle_id'] = $partial?->school_cycle_id ?? ($data['school_cycle_id'] ?? null);
        }

        $bank = QuestionBank::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $data['subject_id'],
            'school_cycle_id' => $data['school_cycle_id'] ?? null,
            'cycle_partial_id' => $data['cycle_partial_id'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => true,
        ]);

        return redirect()
            ->route('teacher.question-banks.show', $bank)
            ->with('info', 'Banco creado. Ahora agrega preguntas.');
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

        return view('teacher.question_banks.configure_exam', compact('questionBank', 'exams'));
    }

    public function updateExamConfiguration(Request $request, QuestionBank $questionBank)
    {
        $teacher = auth()->user()->teacher;
        abort_if(! $teacher || (int) $questionBank->teacher_id !== (int) $teacher->id, 403);

        $data = $request->validate([
            'paper_exam_id' => ['required', 'integer', 'exists:paper_exams,id'],
            'question_ids' => ['required', 'array', 'min:1'],
            'question_ids.*' => ['integer', 'exists:questions,id'],
        ]);

        $paperExam = PaperExam::query()
            ->with('assignment')
            ->whereKey((int) $data['paper_exam_id'])
            ->whereHas('assignment', function ($query) use ($teacher, $questionBank) {
                $query->where('teacher_id', $teacher->id)
                    ->where('subject_id', $questionBank->subject_id);
            })
            ->when($questionBank->cycle_partial_id, fn ($query) => $query->where('cycle_partial_id', $questionBank->cycle_partial_id))
            ->when($questionBank->school_cycle_id, fn ($query) => $query->where('school_cycle_id', $questionBank->school_cycle_id))
            ->firstOrFail();

        if ($paperExam->attempts()->exists()) {
            return back()
                ->withInput()
                ->withErrors(['question_ids' => 'No se pueden modificar preguntas porque ya existen intentos registrados.']);
        }

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
            ->route('teacher.question-banks.exam.configure', $questionBank)
            ->with('info', 'Preguntas del examen configuradas correctamente.');
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
            'options' => ['nullable', 'array'],
            'options.*.text' => ['nullable', 'string', 'max:500'],
            'options.*.is_correct' => ['nullable', 'boolean'],
            'pairs' => ['nullable', 'array'],
            'pairs.*.left' => ['nullable', 'string', 'max:500'],
            'pairs.*.right' => ['nullable', 'string', 'max:500'],
            'blanks' => ['nullable', 'array'],
            'blanks.*' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($questionBank, $data) {
            $question = Question::create([
                'question_bank_id' => $questionBank->id,
                'type' => $data['type'],
                'prompt' => $data['prompt'],
                'points' => $data['points'],
                'sort_order' => $data['sort_order'] ?? ((int) $questionBank->questions()->max('sort_order') + 1),
                'is_active' => true,
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
            'options' => ['nullable', 'array'],
            'options.*.text' => ['nullable', 'string', 'max:500'],
            'options.*.is_correct' => ['nullable', 'boolean'],
            'pairs' => ['nullable', 'array'],
            'pairs.*.left' => ['nullable', 'string', 'max:500'],
            'pairs.*.right' => ['nullable', 'string', 'max:500'],
            'blanks' => ['nullable', 'array'],
            'blanks.*' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($question, $data) {
            $question->update([
                'type' => $data['type'],
                'prompt' => $data['prompt'],
                'points' => $data['points'],
                'sort_order' => $data['sort_order'] ?? $question->sort_order,
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

    public function downloadTemplate()
    {
        $sheet = (new Spreadsheet())->getActiveSheet();
        $sheet->setTitle('Preguntas');
        $sheet->setCellValue('A1', 'tipo');
        $sheet->setCellValue('B1', 'enunciado');
        $sheet->setCellValue('C1', 'puntos');
        $sheet->setCellValue('D1', 'orden');
        $sheet->setCellValue('E1', 'opciones');
        $sheet->setCellValue('F1', 'correctas');
        $sheet->setCellValue('G1', 'pares_relacion');
        $sheet->setCellValue('H1', 'respuestas_completado');

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

        $path = storage_path('app/question_bank_template.xlsx');
        (new Xlsx($sheet->getParent()))->save($path);

        return response()->download($path, 'plantilla_preguntas.xlsx')->deleteFileAfterSend(true);
    }

    public function importQuestions(Request $request, QuestionBank $questionBank)
    {
        $teacher = auth()->user()->teacher;
        abort_if(! $teacher || (int) $questionBank->teacher_id !== (int) $teacher->id, 403);

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
            ->with('info', "Carga masiva completada. Preguntas importadas: {$imported}.");
    }
}
