<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->foreignId('session_activity_id')
                ->nullable()
                ->after('teaching_assignment_id')
                ->constrained('session_activities')
                ->nullOnDelete();

            $table->unique('session_activity_id');
        });

        $sessionActivities = DB::table('session_activities as sa')
            ->join('academic_sessions as s', 's.id', '=', 'sa.academic_session_id')
            ->leftJoin('activities as linked', 'linked.session_activity_id', '=', 'sa.id')
            ->whereNotNull('sa.evaluation_criterion_id')
            ->whereNull('linked.id')
            ->select([
                'sa.id as session_activity_id',
                'sa.title',
                'sa.description',
                'sa.evaluation_criterion_id',
                's.teaching_assignment_id',
                's.academic_period_id',
                's.session_date',
            ])
            ->get();

        foreach ($sessionActivities as $row) {
            $candidate = DB::table('activities')
                ->whereNull('session_activity_id')
                ->where('teaching_assignment_id', $row->teaching_assignment_id)
                ->where('academic_period_id', $row->academic_period_id)
                ->where('evaluation_criterion_id', $row->evaluation_criterion_id)
                ->whereDate('due_date', $row->session_date)
                ->orderBy('id')
                ->first();

            if ($candidate) {
                DB::table('activities')
                    ->where('id', $candidate->id)
                    ->update([
                        'session_activity_id' => $row->session_activity_id,
                        'updated_at' => now(),
                    ]);
                continue;
            }

            DB::table('activities')->insert([
                'teaching_assignment_id' => $row->teaching_assignment_id,
                'session_activity_id' => $row->session_activity_id,
                'evaluation_criterion_id' => $row->evaluation_criterion_id,
                'academic_period_id' => $row->academic_period_id,
                'title' => $row->title,
                'max_score' => 10,
                'due_date' => $row->session_date,
                'description' => $row->description,
                'evaluation_mode' => 'individual',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropUnique(['session_activity_id']);
            $table->dropConstrainedForeignId('session_activity_id');
        });
    }
};
