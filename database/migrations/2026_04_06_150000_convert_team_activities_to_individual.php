<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $teamActivities = DB::table('activities')
            ->where('evaluation_mode', 'team')
            ->pluck('id');

        foreach ($teamActivities as $activityId) {
            $teamGrades = DB::table('team_grades')
                ->where('activity_id', $activityId)
                ->get();

            foreach ($teamGrades as $teamGrade) {
                $studentIds = DB::table('team_student')
                    ->where('team_id', $teamGrade->team_id)
                    ->pluck('student_id');

                foreach ($studentIds as $studentId) {
                    DB::table('grades')->updateOrInsert(
                        [
                            'activity_id' => $activityId,
                            'student_id' => $studentId,
                        ],
                        [
                            'score' => $teamGrade->score,
                        ]
                    );
                }
            }
        }

        DB::table('activities')
            ->where('evaluation_mode', 'team')
            ->update([
                'evaluation_mode' => 'individual',
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reversible transformation.
    }
};
