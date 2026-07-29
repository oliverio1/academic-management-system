<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class ResetSchoolBaselineCommand extends Command
{
    protected $signature = 'ams:reset-baseline
        {--force : Ejecuta el reinicio sin confirmacion interactiva}
        {--backup : Genera un respaldo SQL antes de limpiar}';

    protected $description = 'Vacía datos operativos y deja la base mínima para iniciar carga académica limpia.';

    private array $keepUserIds = [7, 178];

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Esto borrara datos operativos de prueba. Ya tienes respaldo o quieres continuar?')) {
            $this->warn('Cancelado.');
            return self::SUCCESS;
        }

        if ($this->option('backup')) {
            $this->createBackup();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            $this->truncateOperationalTables();
            $campusId = $this->resetCatalogs();
            $directionUser = $this->resetUsers($campusId);
            $this->resetTenantShell();
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->info('Base reiniciada.');
        $this->line('Modalidades: PREPARATORIA, BACHILLERATO');
        $this->line('Campus: FLORIDA');
        $this->line('Niveles: Cuarto, Quinto, Sexto');
        $this->line('Usuarios conservados: 7, 178');
        $this->line('Usuario direccion: ' . $directionUser->email . ' / password temporal: Direccion123!');

        return self::SUCCESS;
    }

    private function createBackup(): void
    {
        $database = (string) config('database.connections.mysql.database');
        $username = (string) config('database.connections.mysql.username');
        $password = (string) config('database.connections.mysql.password');
        $host = (string) config('database.connections.mysql.host');
        $port = (string) config('database.connections.mysql.port');
        $dump = 'C:\\laragon\\bin\\mysql\\mysql-8.4.3-winx64\\bin\\mysqldump.exe';

        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . 'before_reset_' . now()->format('Ymd_His') . '.sql';

        if (! file_exists($dump)) {
            $this->warn('No encontre mysqldump.exe; se omite respaldo automatico.');
            return;
        }

        $command = sprintf(
            '"%s" --column-statistics=0 --host=%s --port=%s --user=%s --password=%s %s > "%s"',
            $dump,
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($database),
            $path
        );

        $exitCode = 0;
        system($command, $exitCode);

        if ($exitCode === 0 && file_exists($path)) {
            $this->info('Respaldo creado: ' . $path);
        } else {
            $this->warn('No se pudo crear respaldo automatico con mysqldump.');
        }
    }

    private function truncateOperationalTables(): void
    {
        $tables = [
            'paper_exam_attempt_answers',
            'paper_exam_attempts',
            'paper_exam_questions',
            'paper_exams',
            'question_fill_blanks',
            'question_matching_pairs',
            'question_options',
            'questions',
            'question_banks',
            'didactic_plan_items',
            'didactic_plans',
            'economic_acta_events',
            'economic_acta_reopen_requests',
            'economic_actas',
            'assignment_remedial_exams',
            'academic_resolutions',
            'grades',
            'team_grades',
            'team_student',
            'teams',
            'practice_submissions',
            'practices',
            'attendance_justifications',
            'attendances',
            'session_activities',
            'activities',
            'academic_sessions',
            'evaluation_criteria',
            'course_weights',
            'teaching_assignment_student',
            'student_assignment_historicals',
            'schedules',
            'teaching_assignments',
            'school_cycle_group_subject',
            'school_cycle_groups',
            'cycle_partials',
            'academic_periods',
            'school_cycle_campus',
            'school_cycle_modality',
            'school_cycles',
            'academic_calendar_days',
            'subject_teacher',
            'group_subject',
            'temario_points',
            'temarios',
            'subjects',
            'student_group_histories',
            'student_follow_up_responses',
            'student_follow_up_teachers',
            'student_follow_ups',
            'student_incident_reports',
            'student_suspensions',
            'teacher_student_reports',
            'prefect_daily_attendances',
            'prefect_incident_reports',
            'teacher_campus_attendances',
            'teacher_document_submissions',
            'teacher_document_request_items',
            'teacher_document_requests',
            'announcement_recipients',
            'announcement_images',
            'announcements',
            'chat_messages',
            'chat_participants',
            'chat_conversations',
            'notifications',
            'finance_payment_applications',
            'finance_payments',
            'finance_charges',
            'finance_concepts',
            'inventory_movements',
            'inventory_items',
            'inventory_locations',
            'inventory_categories',
            'quality_document_events',
            'quality_document_versions',
            'quality_documents',
            'quality_processes',
            'students',
            'teachers',
            'groups',
            'sessions',
            'password_reset_tokens',
            'jobs',
            'job_batches',
            'failed_jobs',
            'cache',
            'cache_locks',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
                $this->line('Vaciada: ' . $table);
            }
        }
    }

    private function resetCatalogs(): int
    {
        if (Schema::hasTable('campus_user')) {
            DB::table('campus_user')->truncate();
        }

        DB::table('levels')->truncate();
        DB::table('modalities')->truncate();
        DB::table('campuses')->truncate();

        $campusId = (int) DB::table('campuses')->insertGetId([
            'name' => 'Universidad Latinoamericana - Campus Florida',
            'code' => 'FLORIDA',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['PREPARATORIA', 'BACHILLERATO'] as $modalityName) {
            $modalityId = (int) DB::table('modalities')->insertGetId([
                'name' => $modalityName,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach (['Cuarto', 'Quinto', 'Sexto'] as $levelName) {
                DB::table('levels')->insert([
                    'modality_id' => $modalityId,
                    'name' => $levelName,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return $campusId;
    }

    private function resetUsers(int $campusId): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'coordinator', 'guard_name' => 'web']);

        $directionUser = User::updateOrCreate(
            ['email' => 'direccion@ula.edu.mx'],
            [
                'name' => 'Direccion',
                'password' => Hash::make('Direccion123!'),
                'default_campus_id' => $campusId,
            ]
        );

        $keepIds = array_values(array_unique(array_merge($this->keepUserIds, [(int) $directionUser->id])));

        DB::table('model_has_permissions')
            ->where('model_type', User::class)
            ->whereNotIn('model_id', $keepIds)
            ->delete();

        DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->whereNotIn('model_id', $keepIds)
            ->delete();

        DB::table('users')->whereNotIn('id', $keepIds)->delete();

        User::whereIn('id', $keepIds)->update(['default_campus_id' => $campusId]);

        if (Schema::hasTable('campus_user')) {
            foreach ($keepIds as $userId) {
                DB::table('campus_user')->updateOrInsert(
                    ['user_id' => $userId, 'campus_id' => $campusId],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }
        }

        User::find(7)?->syncRoles(['coordinator']);
        User::find(178)?->syncRoles(['admin']);
        $directionUser->syncRoles(['admin', 'coordinator']);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return $directionUser;
    }

    private function resetTenantShell(): void
    {
        if (! Schema::hasTable('tenants') || ! Schema::hasTable('domains')) {
            return;
        }

        DB::table('domains')->truncate();
        DB::table('tenants')->truncate();

        DB::table('tenants')->insert([
            'id' => 'escuela',
            'data' => json_encode(['name' => 'Escuela unica']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['ula', 'ula:8000', 'gestion-escolar.text', '127.0.0.1', '127.0.0.1:8000', 'localhost', 'localhost:8000'] as $domain) {
            DB::table('domains')->insert([
                'domain' => $domain,
                'tenant_id' => 'escuela',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
