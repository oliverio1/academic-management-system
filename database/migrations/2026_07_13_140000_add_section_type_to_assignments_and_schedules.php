<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('teaching_assignments', 'section_type')) {
            Schema::table('teaching_assignments', function (Blueprint $table) {
                $table->string('section_type', 30)->nullable()->after('section_number');
                $table->string('section_label', 30)->nullable()->after('section_type');
            });
        }

        if (! Schema::hasColumn('schedules', 'section_type')) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->string('section_type', 30)->nullable()->after('section_number');
                $table->string('section_label', 30)->nullable()->after('section_type');
            });
        }

        $this->backfillSectionTypes();
    }

    public function down(): void
    {
        if (Schema::hasColumn('schedules', 'section_type')) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->dropColumn(['section_type', 'section_label']);
            });
        }

        if (Schema::hasColumn('teaching_assignments', 'section_type')) {
            Schema::table('teaching_assignments', function (Blueprint $table) {
                $table->dropColumn(['section_type', 'section_label']);
            });
        }
    }

    private function backfillSectionTypes(): void
    {
        DB::table('teaching_assignments')
            ->leftJoin('subjects', 'subjects.id', '=', 'teaching_assignments.subject_id')
            ->select('teaching_assignments.id', 'teaching_assignments.section_number', 'subjects.name as subject_name')
            ->orderBy('teaching_assignments.id')
            ->get()
            ->each(function ($assignment): void {
                $scheduleType = (string) DB::table('schedules')
                    ->where('teaching_assignment_id', $assignment->id)
                    ->whereNotNull('type')
                    ->value('type');
                $descriptor = $this->sectionDescriptor(
                    (int) $assignment->section_number,
                    (string) $assignment->subject_name,
                    $scheduleType
                );

                DB::table('teaching_assignments')
                    ->where('id', $assignment->id)
                    ->update([
                        'section_type' => $descriptor['type'],
                        'section_label' => $descriptor['label'],
                    ]);
            });

        DB::table('schedules')
            ->join('teaching_assignments', 'teaching_assignments.id', '=', 'schedules.teaching_assignment_id')
            ->update([
                'schedules.section_type' => DB::raw('teaching_assignments.section_type'),
                'schedules.section_label' => DB::raw('teaching_assignments.section_label'),
            ]);
    }

    private function sectionDescriptor(int $sectionNumber, string $subjectName, string $scheduleType): array
    {
        $subjectKey = $this->normalizeKey($subjectName);
        $typeKey = $this->normalizeKey($scheduleType);

        if (str_contains($subjectKey, 'ingles') || str_contains($subjectKey, 'english')) {
            return [
                'type' => 'english',
                'label' => $sectionNumber >= 2 ? 'AVANZADO' : 'BASICO',
            ];
        }

        if (
            str_contains($subjectKey, 'laboratorio')
            || str_contains($subjectKey, 'lab')
            || str_contains($subjectKey, 'taller')
            || str_contains($typeKey, 'laboratorio')
            || str_contains($typeKey, 'lab')
            || str_contains($typeKey, 'taller')
            || str_contains($typeKey, 'dividid')
        ) {
            return [
                'type' => 'lab_taller',
                'label' => match ($sectionNumber) {
                    2 => 'B',
                    3 => 'C',
                    default => 'A',
                },
            ];
        }

        return [
            'type' => null,
            'label' => null,
        ];
    }

    private function normalizeKey(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', trim($value)) ?: $value;
        $value = mb_strtolower($value);
        $value = preg_replace('/\s+/', ' ', $value) ?? '';

        return trim($value);
    }
};
