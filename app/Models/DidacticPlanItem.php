<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DidacticPlanItem extends Model
{
    protected $fillable = [
        'didactic_plan_id',
        'position',
        'field_training_point_id',
        'objective',
        'temario_point_id',
        'temario_subtopic_ids',
        'opening',
        'development',
        'closing',
        'resources',
        'evaluation',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'temario_subtopic_ids' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function didacticPlan()
    {
        return $this->belongsTo(DidacticPlan::class);
    }

    public function temarioPoint()
    {
        return $this->belongsTo(TemarioPoint::class);
    }

    public function fieldTrainingPoint()
    {
        return $this->belongsTo(TemarioPoint::class, 'field_training_point_id');
    }
}
