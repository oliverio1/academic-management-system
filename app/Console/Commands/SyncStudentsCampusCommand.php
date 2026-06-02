<?php

namespace App\Console\Commands;

use App\Models\Campus;
use App\Models\SchoolCycle;
use App\Models\SchoolCycleGroup;
use App\Models\Student;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncStudentsCampusCommand extends Command
{
    protected $signature = 'students:sync-campus
        {--campus_id= : ID del campus destino}
        {--dry-run : Solo muestra conteos, sin guardar}
        {--include-guardians : Tambien asigna default_campus_id a tutores/guardianes vinculados}';

    protected $description = 'Sincroniza default_campus_id de alumnos (y opcionalmente tutores) por campus segun grupos del ciclo activo.';

    public function handle(): int
    {
        $campusId = (int) ($this->option('campus_id') ?? 0);
        $dryRun = (bool) $this->option('dry-run');
        $includeGuardians = (bool) $this->option('include-guardians');

        if ($campusId <= 0) {
            $this->error('Debes indicar --campus_id.');
            return self::FAILURE;
        }

        $campus = Campus::query()->find($campusId);
        if (! $campus) {
            $this->error("Campus {$campusId} no existe.");
            return self::FAILURE;
        }

        $activeCycleId = SchoolCycle::query()
            ->where('campus_id', $campusId)
            ->where('is_active', true)
            ->orderByDesc('start_date')
            ->value('id');

        if (! $activeCycleId) {
            $this->error("No hay ciclo activo para campus {$campus->name}.");
            return self::FAILURE;
        }

        $groupIds = SchoolCycleGroup::query()
            ->where('school_cycle_id', (int) $activeCycleId)
            ->where('campus_id', $campusId)
            ->where('is_active', true)
            ->pluck('group_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($groupIds->isEmpty()) {
            $this->warn("No hay grupos activos en el ciclo {$activeCycleId} para {$campus->name}.");
            return self::SUCCESS;
        }

        $studentUserIds = Student::query()
            ->whereIn('group_id', $groupIds->all())
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $guardiansUserIds = collect();
        if ($includeGuardians) {
            $guardiansUserIds = Student::query()
                ->whereIn('group_id', $groupIds->all())
                ->whereNotNull('guardian_user_id')
                ->pluck('guardian_user_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();
        }

        $this->info("Campus: {$campus->name} (#{$campusId})");
        $this->line("Ciclo activo: {$activeCycleId}");
        $this->line('Grupos activos detectados: ' . $groupIds->count());
        $this->line('Alumnos a sincronizar: ' . $studentUserIds->count());
        if ($includeGuardians) {
            $this->line('Tutores/guardianes a sincronizar: ' . $guardiansUserIds->count());
        }

        if ($dryRun) {
            $this->warn('Modo dry-run: no se aplicaron cambios.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($studentUserIds, $guardiansUserIds, $includeGuardians, $campusId) {
            if ($studentUserIds->isNotEmpty()) {
                User::query()
                    ->whereIn('id', $studentUserIds->all())
                    ->update(['default_campus_id' => $campusId]);
            }

            if ($includeGuardians && $guardiansUserIds->isNotEmpty()) {
                User::query()
                    ->whereIn('id', $guardiansUserIds->all())
                    ->update(['default_campus_id' => $campusId]);
            }
        });

        $this->info('Sincronizacion completada.');
        return self::SUCCESS;
    }
}

