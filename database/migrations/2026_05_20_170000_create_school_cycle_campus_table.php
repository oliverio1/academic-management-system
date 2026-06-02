<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_cycle_campus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_cycle_id')->constrained('school_cycles')->cascadeOnDelete();
            $table->foreignId('campus_id')->constrained('campuses')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['school_cycle_id', 'campus_id'], 'school_cycle_campus_unique');
        });

        $rows = DB::table('school_cycles')
            ->whereNotNull('campus_id')
            ->select('id', 'campus_id')
            ->get();

        $now = now();
        foreach ($rows as $row) {
            DB::table('school_cycle_campus')->updateOrInsert(
                [
                    'school_cycle_id' => (int) $row->id,
                    'campus_id' => (int) $row->campus_id,
                ],
                [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('school_cycle_campus');
    }
};

