<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $bachilleratoId = $this->ensureModality('BACHILLERATO');
        $preparatoriaId = $this->ensureModality('PREPARATORIA');

        foreach (['PRIMERO', 'SEGUNDO', 'TERCERO', 'CUARTO', 'QUINTO', 'SEXTO'] as $levelName) {
            $this->ensureLevel($bachilleratoId, $levelName);
        }

        foreach (['CUARTO', 'QUINTO', 'SEXTO'] as $levelName) {
            $this->ensureLevel($preparatoriaId, $levelName);
        }
    }

    public function down(): void
    {
        // Catalog data is intentionally kept. Removing levels could orphan groups, subjects, and cycles.
    }

    private function ensureModality(string $name): int
    {
        $modality = DB::table('modalities')
            ->whereRaw('UPPER(name) = ?', [$name])
            ->first();

        if ($modality) {
            DB::table('modalities')->where('id', $modality->id)->update([
                'name' => $name,
                'is_active' => true,
                'updated_at' => now(),
            ]);

            return (int) $modality->id;
        }

        return (int) DB::table('modalities')->insertGetId([
            'name' => $name,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ensureLevel(int $modalityId, string $name): void
    {
        $level = DB::table('levels')
            ->where('modality_id', $modalityId)
            ->whereRaw('UPPER(name) = ?', [$name])
            ->first();

        if ($level) {
            DB::table('levels')->where('id', $level->id)->update([
                'name' => $name,
                'is_active' => true,
                'deleted_at' => null,
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('levels')->insert([
            'modality_id' => $modalityId,
            'name' => $name,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
