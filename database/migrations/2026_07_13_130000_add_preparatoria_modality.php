<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('modalities')
            ->whereRaw('UPPER(name) = ?', ['PREPARATORIA'])
            ->exists();

        if (! $exists) {
            DB::table('modalities')->insert([
                'name' => 'PREPARATORIA',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $modality = DB::table('modalities')
            ->whereRaw('UPPER(name) = ?', ['PREPARATORIA'])
            ->first();

        if (! $modality) {
            return;
        }

        $hasLevels = DB::table('levels')->where('modality_id', $modality->id)->exists();
        $hasCycles = DB::table('school_cycles')->where('modality_id', $modality->id)->exists();

        if (! $hasLevels && ! $hasCycles) {
            DB::table('modalities')->where('id', $modality->id)->delete();
        }
    }
};
