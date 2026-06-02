<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_cycle_modality', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modality_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['school_cycle_id', 'modality_id'], 'school_cycle_modality_unique');
        });

        $now = now();
        $rows = DB::table('school_cycles')
            ->select('id', 'modality_id')
            ->whereNotNull('modality_id')
            ->get()
            ->map(fn ($row) => [
                'school_cycle_id' => (int) $row->id,
                'modality_id' => (int) $row->modality_id,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if (! empty($rows)) {
            DB::table('school_cycle_modality')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('school_cycle_modality');
    }
};

