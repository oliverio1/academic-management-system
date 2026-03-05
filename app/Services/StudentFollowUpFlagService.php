<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentFollowUp;
use Carbon\Carbon;
use App\Services\AttendanceService;
use App\Models\TeachingAssignment;
use App\Services\AcademicPerformanceService;

class StudentFollowUpFlagService
{
    public function flagsFor(Student $student): array
    {
        $flags = [];

        if ($this->hasAcademicRisk($student)) {
            $flags[] = 'academic_risk';
        }

        if ($this->hasLowAttendance($student)) {
            $flags[] = 'low_attendance';
        }

        if ($this->hasRecentGroupChange($student)) {
            $flags[] = 'group_change';
        }

        if ($this->hasBehavioralFollowUp($student)) {
            $flags[] = 'behavioral';
        }

        return $flags;
    }

    public function priorityFor(Student $student): string
    {
        $flags = $this->flagsFor($student);
        $count = count($flags);

        if (in_array('academic_risk', $flags)) {
            if (
                in_array('low_attendance', $flags) ||
                in_array('group_change', $flags) ||
                $count >= 3
            ) {
                return 'high';
            }

            return 'medium';
        }

        if (
            in_array('low_attendance', $flags) ||
            in_array('behavioral', $flags)
        ) {
            return 'medium';
        }

        if (in_array('group_change', $flags)) {
            return 'low';
        }

        return 'none';
    }

    protected function hasLowAttendance(Student $student): bool
    {
        $assignments = TeachingAssignment::where('group_id', $student->group_id)->get();
    
        if ($assignments->isEmpty()) {
            return false;
        }
    
        $attendanceService = app(AttendanceService::class);
    
        $sum = 0;
        $count = 0;
    
        foreach ($assignments as $assignment) {
            $percentage = $attendanceService
                ->attendancePercentage($assignment, $student);
    
            if ($percentage !== null) {
                $sum += $percentage;
                $count++;
            }
        }
    
        if ($count === 0) {
            return false;
        }
    
        $generalAttendance = $sum / $count;
    
        return $generalAttendance < 80; // umbral institucional
    }

    protected function hasRecentGroupChange(Student $student): bool
    {
        return $student->groupHistories
            ->sortByDesc('start_date')
            ->first()?->start_date
            ?->greaterThan(now()->subWeeks(6)) ?? false;
    }

    protected function hasBehavioralFollowUp(Student $student): bool
    {
        return StudentFollowUp::where('student_id', $student->id)
            ->whereIn('type', ['behavioral', 'mixed'])
            ->where('status', 'closed')
            ->exists();
    }

    protected function hasAcademicRisk(Student $student): bool
    {
        return app(AcademicPerformanceService::class)
            ->studentHasCriticalSubject($student);
    }
}