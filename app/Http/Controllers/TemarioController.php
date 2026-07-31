<?php

namespace App\Http\Controllers;

use App\Models\Temario;
use App\Models\Subject;
use App\Services\Imports\TemarioImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class TemarioController extends Controller
{
    public function teacherIndex()
    {
        $this->ensureCoordinatorAccess();

        $subjects = Subject::query()
            ->where('is_active', true)
            ->with(['level.modality'])
            ->withCount('assignments')
            ->withCount('temarios')
            ->orderBy('name')
            ->get();

        return view('temarios.teacher_index', compact('subjects'));
    }

    public function index(Subject $subject)
    {
        $this->ensureCoordinatorAccess();

        $temarios = $subject->temarios()
            ->with('points')
            ->latest()
            ->get()
            ->unique(function ($temario) {
                return mb_strtolower(trim((string) $temario->title), 'UTF-8');
            })
            ->values();

        return view('temarios.index', compact('subject', 'temarios'));
    }

    public function create(Subject $subject)
    {
        $this->ensureCoordinatorAccess();

        $temario = new Temario();

        return view('temarios.create', compact('subject', 'temario'));
    }

    public function store(Request $request, Subject $subject)
    {
        $this->ensureCoordinatorAccess();

        $data = $this->validateTemario($request, $subject);

        DB::transaction(function () use ($subject, $data) {
            $temario = $subject->temarios()->create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
            ]);

            $this->syncPoints($temario, $data['points']);
        });

        return redirect()
            ->route('temarios.index', $subject)
            ->with('info', 'Temario creado correctamente.');
    }

    public function edit(Temario $temario)
    {
        $temario->loadMissing('subject', 'points');
        $this->ensureCoordinatorAccess();

        $subject = $temario->subject;

        return view('temarios.edit', compact('temario', 'subject'));
    }

    public function update(Request $request, Temario $temario)
    {
        $temario->loadMissing('subject');
        $this->ensureCoordinatorAccess();

        $data = $this->validateTemario($request, $temario->subject, $temario);

        DB::transaction(function () use ($temario, $data) {
            $temario->update([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
            ]);

            $this->syncPoints($temario, $data['points']);
        });

        return redirect()
            ->route('temarios.index', $temario->subject)
            ->with('info', 'Temario actualizado correctamente.');
    }

    public function destroy(Temario $temario)
    {
        $temario->loadMissing('subject');
        $this->ensureCoordinatorAccess();

        $subject = $temario->subject;
        $temario->delete();

        return redirect()
            ->route('temarios.index', $subject)
            ->with('info', 'Temario eliminado correctamente.');
    }

    public function importForm(Subject $subject)
    {
        $this->ensureCoordinatorAccess();

        return view('temarios.import', compact('subject'));
    }

    public function import(Request $request, Subject $subject, TemarioImportService $service)
    {
        $this->ensureCoordinatorAccess();

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,ods',
        ]);

        $result = $service->import($request->file('file'), $subject);

        return view('temarios.import_result', compact('result', 'subject'));
    }

    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('temario');

        $sheet->setCellValue('A1', 'Nombre de la materia');
        $sheet->setCellValue('B1', 'Fisica I');
        $sheet->setCellValue('A2', 'Creditos');
        $sheet->setCellValue('B2', '8');
        $sheet->setCellValue('A4', 'Objetivo general');
        $sheet->setCellValue('B4', 'Comprender los principios basicos del movimiento, fuerzas y energia.');

        $sheet->setCellValue('A5', '1. Cinematica');
        $sheet->setCellValue('B5', 'Analizar el movimiento rectilineo y sus representaciones.');
        $sheet->setCellValue('C5', '12');
        $sheet->setCellValue('D5', 'conceptual');
        $sheet->setCellValue('A6', '1.1. Magnitudes y unidades');
        $sheet->setCellValue('C6', 'conceptual');
        $sheet->setCellValue('A7', '1.2. Movimiento rectilineo uniforme');
        $sheet->setCellValue('C7', 'procedimental');
        $sheet->setCellValue('A8', '1.2.1. Graficas posicion-tiempo');
        $sheet->setCellValue('C8', 'actitudinal');
        $sheet->setCellValue('A9', '2. Dinamica');
        $sheet->setCellValue('B9', 'Aplicar las leyes de Newton para resolver problemas de fuerzas.');
        $sheet->setCellValue('C9', '10');
        $sheet->setCellValue('D9', 'conceptual');
        $sheet->setCellValue('A10', '2.1. Leyes de Newton');
        $sheet->setCellValue('C10', 'conceptual');
        $sheet->setCellValue('A11', '2.1.1. Diagramas de cuerpo libre');
        $sheet->setCellValue('C11', 'procedimental');

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'plantilla_temarios.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function validateTemario(Request $request, Subject $subject, ?Temario $temario = null): array
    {
        $data = $request->validate(
            [
                'title' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('temarios', 'title')
                        ->where(fn ($query) => $query->where('subject_id', $subject->id))
                        ->ignore($temario?->id),
                ],
                'description' => 'nullable|string',
                'units' => 'required|array|min:1',
                'units.*.title' => 'required|string|max:1000',
                'units.*.objective' => 'nullable|string',
                'units.*.hours' => 'nullable|numeric|min:0|max:9999',
                'units.*.points' => 'required|array|min:1',
                'units.*.points.*.label' => 'nullable|string|max:20',
                'units.*.points.*.type' => 'required|in:conceptual,procedimental,actitudinal,otro',
                'units.*.points.*.content' => 'required|string',
            ],
            [
                'title.required' => 'El título del temario es obligatorio.',
                'title.unique' => 'Ya existe un temario con ese título para esta materia.',
                'units.required' => 'Debes capturar al menos una unidad.',
                'units.min' => 'Debes capturar al menos una unidad.',
                'units.*.title.required' => 'Cada unidad debe tener nombre.',
                'units.*.points.required' => 'Cada unidad debe tener al menos un punto.',
                'units.*.points.min' => 'Cada unidad debe tener al menos un punto.',
                'units.*.hours.numeric' => 'Las horas de la unidad deben ser numericas.',
                'units.*.points.*.type.required' => 'Cada punto debe tener tipo.',
                'units.*.points.*.content.required' => 'No puede haber puntos vacíos en el temario.',
            ]
        );

        $flatPoints = [];
        foreach (array_values($data['units'] ?? []) as $unitIndex => $unit) {
            $unitNumber = $unitIndex + 1;
            $unitTitle = trim((string) ($unit['title'] ?? ''));
            $unitObjective = trim((string) ($unit['objective'] ?? ''));
            $unitContent = $unitTitle;
            if ($unitObjective !== '') {
                $unitContent .= ' | Objetivo especifico: ' . $unitObjective;
            }

            $flatPoints[] = [
                'label' => (string) $unitNumber,
                'type' => 'otro',
                'hours' => $unit['hours'] ?? null,
                'content' => $unitContent,
            ];

            foreach (array_values($unit['points'] ?? []) as $pointIndex => $point) {
                $flatPoints[] = [
                    'label' => trim((string) ($point['label'] ?? '')) !== ''
                        ? trim((string) $point['label'])
                        : ($unitNumber . '.' . ($pointIndex + 1)),
                    'type' => $point['type'] ?? 'otro',
                    'hours' => null,
                    'content' => trim((string) ($point['content'] ?? '')),
                ];
            }
        }

        $data['title'] = preg_replace('/\s+/u', ' ', trim((string) $data['title']));
        $data['points'] = $flatPoints;
        return $data;
    }

    private function syncPoints(Temario $temario, array $points): void
    {
        $temario->points()->delete();

        foreach (array_values($points) as $index => $point) {
            $label = $point['label'] ?? null;
            $temario->points()->create([
                'position' => $index + 1,
                'label' => $label,
                'level' => $this->inferLevelFromLabel($label),
                'type' => $point['type'],
                'hours' => $point['hours'] ?? null,
                'content' => $point['content'],
            ]);
        }
    }

    private function inferLevelFromLabel(?string $label): int
    {
        $clean = trim((string) $label);
        if ($clean === '') {
            return 1;
        }

        if (preg_match('/^([0-9]+(?:\.(?:[0-9]+|[a-zA-Z]))*)\.?$/', $clean, $matches) !== 1) {
            return 1;
        }

        $parts = array_values(array_filter(explode('.', $matches[1]), fn ($part) => $part !== ''));
        return max(1, count($parts));
    }

    private function ensureCoordinatorAccess(): void
    {
        $user = auth()->user();
        abort_if(! $user || ! $user->hasRole('coordinator'), 403);
    }
}
