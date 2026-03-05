<?php

namespace App\Services\AdminAlerts\Generators;

use App\DTOs\AdminAlert;
use App\Models\Attendance;
use App\Services\AdminAlerts\Contracts\AlertGenerator;
use Carbon\Carbon;
use App\Services\AcademicCalendarService;

class StudentsWithConsecutiveAbsencesAlert implements AlertGenerator
{
    protected int $modalityId;

    public function __construct(protected AcademicCalendarService $calendar) {}

    public function forModality(int $modalityId): self {
        $this->modalityId = $modalityId;
        return $this;
    }

    public function generate(): ?AdminAlert
    {
        $limit = 3;
        $modalityId = $this->modalityId;
        $endDate = $this->calendar->getLastSchoolDay(now(), $modalityId);
        $since = $this->calendar->subtractSchoolDays($endDate,14,$modalityId);

        $students = Attendance::selectRaw('COUNT(DISTINCT student_id) as total')
            ->where('status', 'absent')
            ->whereBetween('class_date', [$since, $endDate])
            ->havingRaw('COUNT(*) >= ?', [$limit])
            ->value('total');

        if ($students === 0) {
            return null;
        }

        $from = $since->translatedFormat('d M Y');
        $to = $endDate->translatedFormat('d M Y');

        $dateRange = "Del {$from} al {$to}";

        return new AdminAlert(
            icon: '<i class="fas fa-user-times text-danger mr-2"></i>',
            message: "{$students} alumnos con {$limit}+ faltas recientes",
            url: route('admin.alerts.attendance', ['modality' => $modalityId]),
            level: 'danger',
            dateRange: $dateRange
        );
    }
}
