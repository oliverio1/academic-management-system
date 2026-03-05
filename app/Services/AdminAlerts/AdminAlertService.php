<?php

namespace App\Services\AdminAlerts;

use Illuminate\Support\Collection;
use App\Services\AdminAlerts\Generators\StudentsWithConsecutiveAbsencesAlert;
use App\Services\AdminAlerts\Generators\StudentsWithFullDayAbsencesAlert;
use App\Services\AdminAlerts\Generators\StudentsWithPartialAttendanceAlert;
use App\Services\AdminAlerts\Generators\StudentsWithCriticalSubjectAlert;
use App\Services\AcademicCalendarService;
use App\Models\Modality;

class AdminAlertService
{
    public function __construct(
        protected StudentsWithFullDayAbsencesAlert $fullDayAlert,
        protected StudentsWithPartialAttendanceAlert $partialAlert,
        protected StudentsWithCriticalSubjectAlert $criticalSubjectAlert
    ) {}

    public function getAlerts(): Collection {
        $alerts = collect();

        foreach (Modality::all() as $modality) {

            if ($alert = $this->fullDayAlert
                ->forModality($modality->id)
                ->generate()
            ) {
                $alert->message = "{$modality->name}: {$alert->message}";
                $alerts->push($alert);
            }

            if ($alert = $this->partialAlert
                ->forModality($modality->id)
                ->generate()
            ) {
                $alert->message = "{$modality->name}: {$alert->message}";
                $alerts->push($alert);
            }

            // 🔑 AQUÍ EL CAMBIO IMPORTANTE
            if ($alert = $this->criticalSubjectAlert
                ->generate($modality->id)
            ) {
                $alert->message = "{$modality->name}: {$alert->message}";
                $alerts->push($alert);
            }
        }

        return $alerts;
    }
}