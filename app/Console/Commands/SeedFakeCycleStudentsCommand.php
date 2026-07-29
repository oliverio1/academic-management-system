<?php

namespace App\Console\Commands;

use App\Models\SchoolCycleGroup;
use App\Models\Student;
use App\Models\StudentGroupHistory;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class SeedFakeCycleStudentsCommand extends Command
{
    protected $signature = 'fake:cycle-students
        {--per-group=25 : Alumnos a crear por grupo activo}
        {--password=123123123 : Contrasena inicial para alumnos y tutores}
        {--cleanup : Elimina alumnos/tutores ficticios creados por este comando}';

    protected $description = 'Crea alumnos y tutores ficticios para los grupos activos del ciclo actual.';

    private array $firstNames = [
        'Adrian', 'Alejandra', 'Andrea', 'Bruno', 'Camila', 'Carlos', 'Daniela', 'Diego', 'Emiliano', 'Fernanda',
        'Gabriel', 'Isabella', 'Javier', 'Jimena', 'Leonardo', 'Lucia', 'Mariana', 'Mateo', 'Natalia', 'Pablo',
        'Regina', 'Rodrigo', 'Santiago', 'Sofia', 'Valeria', 'Ximena', 'Sebastian', 'Renata', 'Mauricio', 'Paula',
    ];

    private array $lastNames = [
        'Aguilar', 'Alvarez', 'Cabrera', 'Campos', 'Castillo', 'Cruz', 'Delgado', 'Flores', 'Garcia', 'Gomez',
        'Hernandez', 'Juarez', 'Lopez', 'Martinez', 'Medina', 'Morales', 'Navarro', 'Ortega', 'Ramirez', 'Reyes',
        'Rivera', 'Rojas', 'Sanchez', 'Torres', 'Vargas', 'Vega', 'Zamora', 'Pineda', 'Mendoza', 'Salazar',
    ];

    public function handle(): int
    {
        if ($this->option('cleanup')) {
            return $this->cleanup();
        }

        $perGroup = max(1, min(60, (int) $this->option('per-group')));
        $password = Hash::make((string) $this->option('password'));

        Role::findOrCreate('student');
        Role::findOrCreate('guardian');
        Role::findOrCreate('tutor');

        $cycleGroups = SchoolCycleGroup::query()
            ->with(['group', 'campus'])
            ->where('is_active', true)
            ->whereHas('group')
            ->orderBy('group_id')
            ->get();

        if ($cycleGroups->isEmpty()) {
            $this->error('No hay grupos activos de ciclo para poblar.');
            return self::FAILURE;
        }

        $createdStudents = 0;
        $updatedStudents = 0;
        $createdGuardians = 0;
        $sectionLinks = 0;

        DB::transaction(function () use ($cycleGroups, $perGroup, $password, &$createdStudents, &$updatedStudents, &$createdGuardians, &$sectionLinks) {
            foreach ($cycleGroups as $cycleGroup) {
                $group = $cycleGroup->group;
                $groupName = (string) $group->name;

                for ($index = 1; $index <= $perGroup; $index++) {
                    $seed = ((int) preg_replace('/\D+/', '', $groupName)) * 100 + $index;
                    $firstName = $this->firstNames[$seed % count($this->firstNames)];
                    $lastName1 = $this->lastNames[($seed + 7) % count($this->lastNames)];
                    $lastName2 = $this->lastNames[($seed + 13) % count($this->lastNames)];
                    $studentName = "{$firstName} {$lastName1} {$lastName2}";
                    $guardianName = 'Tutor ' . $lastName1 . ' ' . $lastName2;
                    $enrollment = 'ST' . $groupName . str_pad((string) $index, 2, '0', STR_PAD_LEFT);
                    $studentEmail = Str::lower("alumno.{$groupName}.{$index}@stress.local");
                    $guardianEmail = Str::lower("tutor.{$groupName}.{$index}@stress.local");

                    $guardian = User::query()->firstOrCreate(
                        ['email' => $guardianEmail],
                        [
                            'name' => $guardianName,
                            'password' => $password,
                            'default_campus_id' => $cycleGroup->campus_id,
                        ]
                    );

                    if ($guardian->wasRecentlyCreated) {
                        $createdGuardians++;
                    }

                    $guardian->assignRole('guardian');
                    $guardian->assignRole('tutor');
                    if ($cycleGroup->campus_id) {
                        $guardian->campuses()->syncWithoutDetaching([(int) $cycleGroup->campus_id]);
                    }

                    $user = User::query()->firstOrCreate(
                        ['email' => $studentEmail],
                        [
                            'name' => $studentName,
                            'password' => $password,
                            'default_campus_id' => $cycleGroup->campus_id,
                        ]
                    );

                    $user->assignRole('student');
                    if ($cycleGroup->campus_id) {
                        $user->campuses()->syncWithoutDetaching([(int) $cycleGroup->campus_id]);
                    }

                    $student = Student::query()->where('enrollment_number', $enrollment)->first();

                    if (! $student) {
                        $student = Student::query()->create([
                            'user_id' => $user->id,
                            'guardian_user_id' => $guardian->id,
                            'group_id' => $group->id,
                            'enrollment_number' => $enrollment,
                            'phone' => '55' . str_pad((string) (10000000 + $seed), 8, '0', STR_PAD_LEFT),
                            'address' => 'Domicilio ficticio ' . $index . ', Col. Florida',
                            'is_active' => true,
                        ]);
                        $createdStudents++;
                    } else {
                        $student->update([
                            'user_id' => $user->id,
                            'guardian_user_id' => $guardian->id,
                            'group_id' => $group->id,
                            'phone' => '55' . str_pad((string) (10000000 + $seed), 8, '0', STR_PAD_LEFT),
                            'address' => 'Domicilio ficticio ' . $index . ', Col. Florida',
                            'is_active' => true,
                        ]);
                        $updatedStudents++;
                    }

                    StudentGroupHistory::query()->updateOrCreate(
                        [
                            'student_id' => $student->id,
                            'group_id' => $group->id,
                            'start_date' => optional($cycleGroup->schoolCycle)->start_date,
                        ],
                        [
                            'end_date' => null,
                            'reason' => '[FAKE] Carga inicial para pruebas',
                        ]
                    );

                    $sectionLinks += $this->syncSectionAssignments($cycleGroup, $student, $index);
                }
            }
        });

        $this->info('Alumnos ficticios listos.');
        $this->table(
            ['Metrica', 'Total'],
            [
                ['Grupos activos procesados', $cycleGroups->count()],
                ['Alumnos creados', $createdStudents],
                ['Alumnos actualizados/reactivados', $updatedStudents],
                ['Tutores creados', $createdGuardians],
                ['Vinculos de seccion creados/verificados', $sectionLinks],
                ['Contrasena inicial', (string) $this->option('password')],
            ]
        );

        return self::SUCCESS;
    }

    private function syncSectionAssignments(SchoolCycleGroup $cycleGroup, Student $student, int $index): int
    {
        $linked = 0;
        $assignments = TeachingAssignment::query()
            ->where('school_cycle_group_id', $cycleGroup->id)
            ->where('is_active', true)
            ->get();

        foreach ($assignments->whereNull('section_type') as $assignment) {
            DB::table('teaching_assignment_student')->updateOrInsert([
                'teaching_assignment_id' => $assignment->id,
                'student_id' => $student->id,
            ], [
                'updated_at' => now(),
                'created_at' => now(),
            ]);
            $linked++;
        }

        foreach (['english', 'lab_taller'] as $type) {
            $sectioned = $assignments
                ->where('section_type', $type)
                ->sortBy('section_label')
                ->values();

            if ($sectioned->isEmpty()) {
                continue;
            }

            $assignment = $sectioned[($index - 1) % $sectioned->count()];
            DB::table('teaching_assignment_student')->updateOrInsert([
                'teaching_assignment_id' => $assignment->id,
                'student_id' => $student->id,
            ], [
                'updated_at' => now(),
                'created_at' => now(),
            ]);
            $linked++;
        }

        return $linked;
    }

    private function cleanup(): int
    {
        $studentIds = Student::query()
            ->where('enrollment_number', 'like', 'ST%')
            ->whereHas('user', fn ($query) => $query->where('email', 'like', '%@stress.local'))
            ->pluck('id');

        $userIds = User::query()
            ->where('email', 'like', '%@stress.local')
            ->pluck('id');

        DB::transaction(function () use ($studentIds, $userIds) {
            DB::table('teaching_assignment_student')->whereIn('student_id', $studentIds)->delete();
            StudentGroupHistory::query()->whereIn('student_id', $studentIds)->delete();
            Student::query()->whereIn('id', $studentIds)->forceDelete();
            DB::table('campus_user')->whereIn('user_id', $userIds)->delete();
            DB::table('model_has_roles')
                ->whereIn('model_id', $userIds)
                ->where('model_type', User::class)
                ->delete();
            User::query()->whereIn('id', $userIds)->delete();
        });

        $this->info('Alumnos/tutores ficticios eliminados.');
        $this->table(
            ['Metrica', 'Total'],
            [
                ['Alumnos eliminados', $studentIds->count()],
                ['Usuarios eliminados', $userIds->count()],
            ]
        );

        return self::SUCCESS;
    }
}
