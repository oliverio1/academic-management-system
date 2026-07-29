<?php

namespace App\Console\Commands;

use App\Models\SchoolCycle;
use App\Models\Teacher;
use App\Services\TentativeDidacticPlanGeneratorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateTentativeDidacticPlansCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'didactic-plans:generate-tentative
        {--cycle= : ID, codigo o nombre del ciclo escolar; por defecto usa el ciclo activo mas reciente}
        {--teacher-id= : ID del docente}
        {--teacher-email= : Email del usuario docente}
        {--teacher-name= : Texto del nombre del docente}
        {--assignment-id= : ID de una asignacion especifica}
        {--replace-tentative : Reemplaza planeaciones tentativas existentes del ciclo}
        {--commit : Ejecuta cambios reales. Sin esta opcion solo simula}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera planeaciones tentativas desde temarios y sesiones teoricas del horario.';

    /**
     * Execute the console command.
     */
    public function handle(TentativeDidacticPlanGeneratorService $generator): int
    {
        $cycle = $this->resolveCycle();
        if (! $cycle) {
            return self::FAILURE;
        }

        $teacherId = $this->resolveTeacherId();
        if ($teacherId === false) {
            return self::FAILURE;
        }

        $options = [
            'teacher_id' => $teacherId,
            'assignment_id' => $this->option('assignment-id') ? (int) $this->option('assignment-id') : null,
            'replace_tentative' => (bool) $this->option('replace-tentative'),
        ];

        $commit = (bool) $this->option('commit');

        try {
            DB::beginTransaction();
            $summary = $generator->generateForCycle($cycle, $options);

            if ($commit) {
                DB::commit();
            } else {
                DB::rollBack();
            }
        } catch (\Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        $this->info($commit ? 'PLANEACIONES TENTATIVAS GENERADAS.' : 'SIMULACION: no se guardaron cambios.');
        $this->line('Ciclo: '.$cycle->name.' ('.$cycle->code.')');
        $this->line('Asignaciones revisadas: '.$summary['reviewed']);
        $this->line('Planeaciones tentativas '.($commit ? 'creadas' : 'que se crearian').': '.$summary['created']);
        $this->line('Tentativas reemplazadas: '.$summary['replaced']);

        if (! empty($summary['created_plans'])) {
            $this->table(
                ['Plan', 'Grupo', 'Materia', 'Sesiones'],
                collect($summary['created_plans'])
                    ->map(fn ($row) => [
                        $row['plan_id'] ?? '-',
                        $row['group'] ?? '-',
                        $row['subject'] ?? '-',
                        $row['items'] ?? 0,
                    ])
                    ->all()
            );
        }

        if (! empty($summary['skipped'])) {
            $this->warn('Omitidas:');
            foreach (array_slice($summary['skipped'], 0, 25) as $message) {
                $this->line('- '.$message);
            }
            if (count($summary['skipped']) > 25) {
                $this->line('- ... '.(count($summary['skipped']) - 25).' mas');
            }
        }

        if (! $commit) {
            $this->comment('Para guardar agrega --commit.');
        }

        return self::SUCCESS;
    }

    private function resolveCycle(): ?SchoolCycle
    {
        $value = trim((string) $this->option('cycle'));

        if ($value !== '') {
            $cycle = SchoolCycle::query()
                ->where('id', is_numeric($value) ? (int) $value : 0)
                ->orWhere('code', $value)
                ->orWhere('name', 'like', '%'.$value.'%')
                ->orderByDesc('start_date')
                ->first();

            if (! $cycle) {
                $this->error('No encontre el ciclo: '.$value);
                return null;
            }

            return $cycle;
        }

        $cycle = SchoolCycle::query()
            ->where('is_active', true)
            ->orderByDesc('start_date')
            ->first();

        if (! $cycle) {
            $this->error('No hay ciclo activo.');
        }

        return $cycle;
    }

    private function resolveTeacherId(): int|false|null
    {
        $query = Teacher::query()->with('user');

        if ($this->option('teacher-id')) {
            return (int) $this->option('teacher-id');
        }

        if ($this->option('teacher-email')) {
            $query->whereHas('user', fn ($user) => $user->where('email', (string) $this->option('teacher-email')));
        } elseif ($this->option('teacher-name')) {
            $query->whereHas('user', fn ($user) => $user->where('name', 'like', '%'.((string) $this->option('teacher-name')).'%'));
        } else {
            return null;
        }

        $teachers = $query->get();
        if ($teachers->count() !== 1) {
            $this->error($teachers->isEmpty()
                ? 'No encontre el docente solicitado.'
                : 'La busqueda del docente regreso mas de un resultado. Usa --teacher-id.');
            return false;
        }

        return (int) $teachers->first()->id;
    }
}
