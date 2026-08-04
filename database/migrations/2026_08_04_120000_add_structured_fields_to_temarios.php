<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('temarios', function (Blueprint $table) {
            if (! Schema::hasColumn('temarios', 'program_key')) {
                $table->string('program_key', 50)->nullable()->after('subject_id');
            }
            if (! Schema::hasColumn('temarios', 'area')) {
                $table->string('area', 20)->nullable()->after('program_key');
            }
            if (! Schema::hasColumn('temarios', 'area_label')) {
                $table->string('area_label')->nullable()->after('area');
            }
            if (! Schema::hasColumn('temarios', 'general_objective')) {
                $table->text('general_objective')->nullable()->after('description');
            }
            if (! Schema::hasColumn('temarios', 'weekly_hours')) {
                $table->unsignedSmallInteger('weekly_hours')->nullable()->after('general_objective');
            }
            if (! Schema::hasColumn('temarios', 'annual_hours')) {
                $table->unsignedSmallInteger('annual_hours')->nullable()->after('weekly_hours');
            }
            if (! Schema::hasColumn('temarios', 'source_filename')) {
                $table->string('source_filename')->nullable()->after('annual_hours');
            }
        });

        Schema::table('temario_points', function (Blueprint $table) {
            if (! Schema::hasColumn('temario_points', 'parent_id')) {
                $table->foreignId('parent_id')
                    ->nullable()
                    ->after('temario_id')
                    ->constrained('temario_points')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('temario_points', 'title')) {
                $table->text('title')->nullable()->after('type');
            }
            if (! Schema::hasColumn('temario_points', 'objective')) {
                $table->text('objective')->nullable()->after('title');
            }
            if (! Schema::hasColumn('temario_points', 'sort_key')) {
                $table->string('sort_key', 50)->nullable()->after('label');
            }
        });

        $this->backfillTemarioMetadata();
        $this->backfillPointStructure();
    }

    public function down(): void
    {
        Schema::table('temario_points', function (Blueprint $table) {
            if (Schema::hasColumn('temario_points', 'parent_id')) {
                $table->dropConstrainedForeignId('parent_id');
            }
            foreach (['sort_key', 'objective', 'title'] as $column) {
                if (Schema::hasColumn('temario_points', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('temarios', function (Blueprint $table) {
            foreach (['source_filename', 'annual_hours', 'weekly_hours', 'general_objective', 'area_label', 'area', 'program_key'] as $column) {
                if (Schema::hasColumn('temarios', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function backfillTemarioMetadata(): void
    {
        DB::table('temarios')
            ->orderBy('id')
            ->get(['id', 'description'])
            ->each(function ($temario) {
                $description = (string) ($temario->description ?? '');
                $updates = [];

                if (preg_match('/Objetivo general:\s*(.+?)(?:\R[A-ZÁÉÍÓÚÑ][^\r\n:]{1,80}:|\z)/su', $description, $matches) === 1) {
                    $updates['general_objective'] = trim((string) $matches[1]);
                }

                if (preg_match('/Clave:\s*([^\r\n]+)/u', $description, $matches) === 1) {
                    $updates['program_key'] = trim((string) $matches[1]);
                }

                if (preg_match('/Horas por semana:\s*([0-9]+)/u', $description, $matches) === 1) {
                    $updates['weekly_hours'] = (int) $matches[1];
                }

                if (preg_match('/Horas al a(?:ñ|n)o:\s*([0-9]+)/u', $description, $matches) === 1) {
                    $updates['annual_hours'] = (int) $matches[1];
                }

                if (! empty($updates)) {
                    DB::table('temarios')->where('id', $temario->id)->update($updates);
                }
            });
    }

    private function backfillPointStructure(): void
    {
        DB::table('temario_points')
            ->orderBy('temario_id')
            ->orderBy('position')
            ->get(['id', 'temario_id', 'label', 'level', 'content'])
            ->groupBy('temario_id')
            ->each(function ($points) {
                $parentsByKey = [];

                foreach ($points as $point) {
                    [$title, $objective] = $this->splitObjective((string) $point->content);
                    $key = $this->labelKey((string) $point->label);
                    $parentId = $this->parentIdForKey($key, $parentsByKey);

                    DB::table('temario_points')
                        ->where('id', $point->id)
                        ->update([
                            'parent_id' => $parentId,
                            'sort_key' => $key !== '' ? $key : null,
                            'title' => $title !== '' ? $title : null,
                            'objective' => $objective !== '' ? $objective : null,
                        ]);

                    if ($key !== '') {
                        $parentsByKey[$key] = $point->id;
                    }
                }
            });
    }

    private function splitObjective(string $content): array
    {
        if (preg_match('/^(.*?)\s*\|\s*Objetivo\s+espec[ií]fico:\s*(.+)$/uis', trim($content), $matches) === 1) {
            return [
                trim((string) $matches[1]),
                trim((string) $matches[2]),
            ];
        }

        return [trim($content), ''];
    }

    private function labelKey(string $label): string
    {
        if (preg_match('/([0-9]+(?:\.(?:[0-9]+|[a-zA-Z]))*)/u', $label, $matches) === 1) {
            return rtrim((string) $matches[1], '.');
        }

        return '';
    }

    private function parentIdForKey(string $key, array $parentsByKey): ?int
    {
        $parts = explode('.', $key);
        if (count($parts) <= 1) {
            return null;
        }

        array_pop($parts);
        while (! empty($parts)) {
            $parentKey = implode('.', $parts);
            if (isset($parentsByKey[$parentKey])) {
                return (int) $parentsByKey[$parentKey];
            }
            array_pop($parts);
        }

        return null;
    }
};
