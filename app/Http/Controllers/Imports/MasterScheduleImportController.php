<?php

namespace App\Http\Controllers\Imports;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\Modality;
use App\Models\SchoolCycle;
use App\Services\CyclePartialDefaultsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MasterScheduleImportController extends Controller
{
    private const SESSION_KEY = 'master_schedule_import';

    public function create()
    {
        $activeCampusId = (int) session('active_campus_id', 0);

        $campuses = Campus::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $modalities = Modality::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $cycles = SchoolCycle::query()
            ->with(['campus:id,code,name', 'modality:id,name'])
            ->when($activeCampusId > 0, fn ($q) => $q->where('campus_id', $activeCampusId))
            ->orderByDesc('start_date')
            ->get();

        return view('imports.master-schedule.create', compact('campuses', 'modalities', 'cycles', 'activeCampusId'));
    }

    public function preview(Request $request, CyclePartialDefaultsService $partialDefaults)
    {
        $tenantId = $this->tenantId();
        $data = $this->validatedPayload($request, preview: true);
        $campus = Campus::query()->findOrFail((int) $data['campus_id']);
        $cycle = $this->resolveCycle($data, $partialDefaults);
        $filePath = $request->file('file')->storeAs(
            'imports/master-schedules',
            Str::uuid() . '.' . $request->file('file')->getClientOriginalExtension()
        );

        $options = $this->importOptions($data);
        $exitCode = $this->runImporter($filePath, $tenantId, $campus, $cycle, $options, dryRun: true);
        $output = Artisan::output();

        session([self::SESSION_KEY => [
            'file_path' => $filePath,
            'tenant_id' => $tenantId,
            'campus_id' => (int) $campus->id,
            'campus_code' => (string) $campus->code,
            'cycle_id' => (int) $cycle->id,
            'cycle_code' => (string) $cycle->code,
            'options' => $options,
        ]]);

        return view('imports.master-schedule.preview', [
            'cycle' => $cycle,
            'campus' => $campus,
            'output' => $output,
            'summary' => $this->parseImportOutput($output),
            'exitCode' => $exitCode,
        ]);
    }

    public function import()
    {
        $payload = session(self::SESSION_KEY);
        abort_if(! is_array($payload), 404);

        $filePath = (string) ($payload['file_path'] ?? '');
        if ($filePath === '' || ! Storage::exists($filePath)) {
            return redirect()
                ->route('imports.master-schedule.create')
                ->withErrors(['file' => 'El archivo temporal ya no esta disponible. Vuelve a cargar el libro maestro.']);
        }

        $campus = Campus::query()->findOrFail((int) $payload['campus_id']);
        $cycle = SchoolCycle::query()->findOrFail((int) $payload['cycle_id']);
        $exitCode = $this->runImporter(
            $filePath,
            (string) $payload['tenant_id'],
            $campus,
            $cycle,
            (array) ($payload['options'] ?? []),
            dryRun: false
        );
        $output = Artisan::output();

        session()->forget(self::SESSION_KEY);

        return view('imports.master-schedule.result', [
            'cycle' => $cycle,
            'campus' => $campus,
            'output' => $output,
            'summary' => $this->parseImportOutput($output),
            'exitCode' => $exitCode,
        ]);
    }

    private function validatedPayload(Request $request, bool $preview): array
    {
        $mode = (string) $request->input('cycle_mode', 'new');

        $rules = [
            'cycle_mode' => ['required', Rule::in(['new', 'existing'])],
            'campus_id' => ['required', 'integer', 'exists:campuses,id'],
            'create_missing_groups' => ['nullable', 'boolean'],
            'create_missing_subjects' => ['nullable', 'boolean'],
            'create_missing_teachers' => ['nullable', 'boolean'],
            'deactivate_existing' => ['nullable', 'boolean'],
        ];

        if ($preview) {
            $rules['file'] = ['required', 'file', 'mimes:xlsx,xls'];
        }

        if ($mode === 'existing') {
            $rules['school_cycle_id'] = ['required', 'integer', 'exists:school_cycles,id'];
        } else {
            $rules += [
                'modality_id' => ['required', 'integer', 'exists:modalities,id'],
                'name' => ['required', 'string', 'max:255'],
                'code' => ['required', 'string', 'max:40', 'unique:school_cycles,code'],
                'start_date' => ['required', 'date'],
                'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            ];
        }

        return $request->validate($rules);
    }

    private function resolveCycle(array $data, CyclePartialDefaultsService $partialDefaults): SchoolCycle
    {
        if (($data['cycle_mode'] ?? 'new') === 'existing') {
            $cycle = SchoolCycle::query()->findOrFail((int) $data['school_cycle_id']);
            abort_unless((int) $cycle->campus_id === (int) $data['campus_id'], 422, 'El ciclo no pertenece al campus seleccionado.');

            return $cycle;
        }

        $cycle = SchoolCycle::create([
            'campus_id' => (int) $data['campus_id'],
            'modality_id' => (int) $data['modality_id'],
            'name' => $data['name'],
            'code' => $data['code'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'is_active' => true,
        ]);
        $cycle->campuses()->sync([(int) $data['campus_id']]);
        $cycle->modalities()->sync([(int) $data['modality_id']]);
        $cycle->loadMissing('modality');
        $partialDefaults->syncForCycle($cycle);

        return $cycle;
    }

    private function importOptions(array $data): array
    {
        return [
            'create_missing_groups' => ! empty($data['create_missing_groups']),
            'create_missing_subjects' => ! empty($data['create_missing_subjects']),
            'create_missing_teachers' => ! empty($data['create_missing_teachers']),
            'deactivate_existing' => ! empty($data['deactivate_existing']),
        ];
    }

    private function runImporter(
        string $filePath,
        string $tenantId,
        Campus $campus,
        SchoolCycle $cycle,
        array $options,
        bool $dryRun
    ): int {
        $arguments = [
            'file' => Storage::path($filePath),
            '--tenant' => $tenantId,
            '--campus-code' => (string) $campus->code,
            '--cycle-code' => (string) $cycle->code,
        ];

        if ($dryRun) {
            $arguments['--dry-run'] = true;
        }
        if ($options['create_missing_groups'] ?? false) {
            $arguments['--create-missing-groups'] = true;
        }
        if ($options['create_missing_subjects'] ?? false) {
            $arguments['--create-missing-subjects'] = true;
        }
        if ($options['create_missing_teachers'] ?? false) {
            $arguments['--create-missing-teachers'] = true;
        }
        if ($options['deactivate_existing'] ?? false) {
            $arguments['--deactivate-existing'] = true;
        }

        return Artisan::call('master-schedule:import', $arguments);
    }

    private function tenantId(): string
    {
        $tenantId = (string) tenant('id');
        abort_if($tenantId === '', 403, 'Tenant no identificado. Usa el dominio del colegio antes de importar.');

        return $tenantId;
    }

    private function parseImportOutput(string $output): array
    {
        $metrics = [];
        $warnings = [];

        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            if (preg_match('/^\|\s*([^|]+?)\s*\|\s*(\d+)\s*\|$/', $line, $matches)) {
                $label = trim($matches[1]);
                if ($label !== 'Metrica') {
                    $metrics[$label] = (int) $matches[2];
                }
                continue;
            }

            if (str_starts_with(trim($line), 'Fila ')) {
                $warnings[] = trim($line);
            }
        }

        return [
            'metrics' => $metrics,
            'warnings' => $warnings,
            'has_warnings' => ! empty($warnings) || (int) ($metrics['Filas omitidas'] ?? 0) > 0,
        ];
    }
}
