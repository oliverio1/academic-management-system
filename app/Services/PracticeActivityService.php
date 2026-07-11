<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\EvaluationCriterion;
use App\Models\Practice;
use App\Models\TeachingAssignment;
use Illuminate\Support\Facades\DB;

class PracticeActivityService
{
    public function createForAssignment(
        TeachingAssignment $assignment,
        array $data
    ): Practice {
        return DB::transaction(function () use ($assignment, $data) {
            $criterion = EvaluationCriterion::query()
                ->where('teaching_assignment_id', $assignment->id)
                ->findOrFail((int) $data['evaluation_criterion_id']);

            $practiceData = $data;
            unset($practiceData['evaluation_criterion_id']);

            $practice = $assignment->practices()->create($practiceData);

            $period = $criterion->cyclePartial?->academicPeriod
                ?: AcademicPeriod::where('modality_id', $assignment->group->level->modality_id)
                    ->where('is_active', 1)
                    ->firstOrFail();

            $activity = Activity::create([
                'teaching_assignment_id' => $assignment->id,
                'evaluation_criterion_id' => $criterion->id,
                'academic_period_id' => $period->id,
                'title' => $practice->kind_label . ' ' . $practice->number . ': ' . $practice->title,
                'description' => $practice->instructions,
                'max_score' => 10,
                'due_date' => $practice->due_date,
            ]);

            $practice->update([
                'activity_id' => $activity->id,
            ]);

            return $practice;
        });
    }
}
