<?php

namespace App\Services;

use App\Models\Practice;
use App\Models\Activity;
use App\Models\EvaluationCriterion;
use App\Models\AcademicPeriod;
use App\Models\TeachingAssignment;
use Illuminate\Support\Facades\DB;

class PracticeActivityService
{
    public function createForAssignment(
        TeachingAssignment $assignment,
        array $data
    ): Practice {
        return DB::transaction(function () use ($assignment, $data) {

            // 1️⃣ Crear la práctica
            $practice = $assignment->practices()->create($data);

            // 2️⃣ Obtener o crear criterio "Prácticas"
            $criterion = EvaluationCriterion::firstOrCreate(
                [
                    'teaching_assignment_id' => $assignment->id,
                    'name' => 'Prácticas',
                ],
                [
                    'percentage' => 0,
                ]
            );

            // 3️⃣ Obtener periodo activo
            $period = AcademicPeriod::where(
                'modality_id',
                $assignment->group->level->modality_id
            )
            ->where('is_active', 1)
            ->firstOrFail();

            // 4️⃣ Crear activity asociada
            $activity = Activity::create([
                'teaching_assignment_id' => $assignment->id,
                'evaluation_criterion_id' => $criterion->id,
                'academic_period_id' => $period->id,
                'title' => 'Práctica ' . $practice->number . ': ' . $practice->title,
                'description' => $practice->instructions,
                'max_score' => 10,
            ]);

            // 5️⃣ Vincular práctica ↔ activity
            $practice->update([
                'activity_id' => $activity->id,
            ]);

            return $practice;
        });
    }
}