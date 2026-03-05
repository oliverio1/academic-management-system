<?php

namespace App\Services;

use App\Models\Student;

class StudentPriorityService
{
    protected array $priorityOrder = [
        'high' => 0,
        'medium' => 0,
        'low' => 0,
        'none' => 0
    ];
    
    public function prioritize($students, $flagService) {
        $students->each(function($student) use ($flagService) {
            $student->followup_flags = $flagService->flagsFor($student);
            $student->priority = $flagService->priorityFor($student);
            $student->has_active_follow_up = $student->followUps()->where('status','open')->exists();
        });
        return $students->sortBy(fn($student) => $this->priorityOrder[$student->priority] ?? 99)->values();
    }
}
