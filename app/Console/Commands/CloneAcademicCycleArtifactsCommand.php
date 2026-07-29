<?php

namespace App\Console\Commands;

use App\Models\SchoolCycle;
use App\Models\Teacher;
use App\Services\AcademicCycleArtifactCloneService;
use Illuminate\Console\Command;

class CloneAcademicCycleArtifactsCommand extends Command
{
    protected $signature = 'academic:clone-cycle-artifacts
        {--source-cycle= : ID o codigo del ciclo origen}
        {--target-cycle= : ID o codigo del ciclo destino}
        {--teacher-id= : ID del docente}
        {--teacher-email= : Email del usuario docente}
        {--teacher-name= : Texto del nombre del docente}
        {--artifacts=rubrics,plans,exams,documents : Lista separada por comas}
        {--replace : Reemplaza artefactos destino cuando sea posible}
        {--commit : Ejecuta cambios reales. Sin esta opcion solo simula}';

    protected $description = 'Clona rubros, planeaciones, examenes y documentos editables entre ciclos para un docente.';

    public function handle(AcademicCycleArtifactCloneService $cloner): int
    {
        $sourceCycle = $this->cycleFromOption((string) $this->option('source-cycle'), 'source-cycle', 'origen');
        $targetCycle = $this->cycleFromOption((string) $this->option('target-cycle'), 'target-cycle', 'destino');
        $teacher = $this->teacherFromOptions();

        if (! $sourceCycle || ! $targetCycle || ! $teacher) {
            return self::FAILURE;
        }

        if ((int) $sourceCycle->id === (int) $targetCycle->id) {
            $this->error('El ciclo origen y destino deben ser diferentes.');
            return self::FAILURE;
        }

        $artifacts = collect(explode(',', (string) $this->option('artifacts')))
            ->map(fn ($artifact) => trim($artifact))
            ->filter()
            ->values()
            ->all();

        $invalid = array_diff($artifacts, ['rubrics', 'plans', 'exams', 'documents']);
        if (! empty($invalid)) {
            $this->error('Artefactos no validos: '.implode(', ', $invalid));
            return self::FAILURE;
        }

        $dryRun = ! (bool) $this->option('commit');
        $summary = $cloner->clone($sourceCycle, $targetCycle, $teacher, [
            'artifacts' => $artifacts,
            'dry_run' => $dryRun,
            'replace' => (bool) $this->option('replace'),
        ]);

        $this->info($dryRun ? 'SIMULACION: no se guardaron cambios.' : 'CLONACION EJECUTADA.');
        $this->line('Docente: '.$summary['teacher']);
        $this->line('Origen: '.$summary['source_cycle']);
        $this->line('Destino: '.$summary['target_cycle']);
        $this->line('Asignaciones destino revisadas: '.$summary['targets']);

        $this->table(['Artefacto', 'Creados', 'Omitidos'], collect(['rubrics', 'plans', 'exams', 'documents'])
            ->filter(fn ($artifact) => in_array($artifact, $artifacts, true))
            ->map(fn ($artifact) => [
                $artifact,
                $summary[$artifact]['created'] ?? 0,
                count($summary[$artifact]['skipped'] ?? []),
            ])
            ->all());

        foreach (['rubrics', 'plans', 'exams', 'documents'] as $artifact) {
            $skipped = $summary[$artifact]['skipped'] ?? [];
            if (! in_array($artifact, $artifacts, true) || empty($skipped)) {
                continue;
            }

            $this->warn("Omitidos {$artifact}:");
            foreach (array_slice($skipped, 0, 20) as $message) {
                $this->line('- '.$message);
            }
            if (count($skipped) > 20) {
                $this->line('- ... '.(count($skipped) - 20).' mas');
            }
        }

        if ($dryRun) {
            $this->comment('Para ejecutar cambios reales agrega --commit.');
        }

        return self::SUCCESS;
    }

    private function cycleFromOption(string $value, string $optionName, string $label): ?SchoolCycle
    {
        $value = trim($value);
        if ($value === '') {
            $this->error("Indica --{$optionName}.");
            return null;
        }

        $cycle = SchoolCycle::query()
            ->where('id', is_numeric($value) ? (int) $value : 0)
            ->orWhere('code', $value)
            ->first();

        if (! $cycle) {
            $this->error("No encontre el ciclo {$label}: {$value}");
            return null;
        }

        return $cycle;
    }

    private function teacherFromOptions(): ?Teacher
    {
        $query = Teacher::query()->with('user');

        if ($this->option('teacher-id')) {
            $query->whereKey((int) $this->option('teacher-id'));
        } elseif ($this->option('teacher-email')) {
            $email = (string) $this->option('teacher-email');
            $query->whereHas('user', fn ($user) => $user->where('email', $email));
        } elseif ($this->option('teacher-name')) {
            $name = (string) $this->option('teacher-name');
            $query->whereHas('user', fn ($user) => $user->where('name', 'like', '%'.$name.'%'));
        } else {
            $this->error('Indica --teacher-id, --teacher-email o --teacher-name.');
            return null;
        }

        $teachers = $query->get();
        if ($teachers->count() !== 1) {
            $this->error($teachers->isEmpty()
                ? 'No encontre el docente solicitado.'
                : 'La busqueda del docente regreso mas de un resultado. Usa --teacher-id.');
            return null;
        }

        return $teachers->first();
    }
}
