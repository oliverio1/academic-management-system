<?php

namespace App\Imports\Attendance;

use App\Services\Imports\AttendanceImportService;
use App\Services\Imports\ImportResult;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Collection;

class AttendanceSheetsImport implements WithMultipleSheets
{
    protected AttendanceImportService $service;
    protected int $academicPeriodId;
    protected ImportResult $result;

    public function __construct(
        AttendanceImportService $service,
        int $academicPeriodId,
        ImportResult $result
    ) {
        $this->service = $service;
        $this->academicPeriodId = $academicPeriodId;
        $this->result = $result;
    }

    public function sheets(): array
    {
        // Se llama automáticamente por cada hoja
        return [
            '*' => new class(
                $this->service,
                $this->academicPeriodId,
                $this->result
            ) implements ToCollection {

                protected AttendanceImportService $service;
                protected int $academicPeriodId;
                protected ImportResult $result;

                public function __construct($service, $academicPeriodId, $result)
                {
                    $this->service = $service;
                    $this->academicPeriodId = $academicPeriodId;
                    $this->result = $result;
                }

                public function collection(Collection $rows)
                {
                    // El nombre real de la hoja
                    $sheetName = $rows->getHeading() ?? 'SIN_NOMBRE';

                    $this->service->importSheet(
                        $rows,
                        $sheetName,
                        $this->academicPeriodId,
                        $this->result
                    );
                }
            }
        ];
    }
}
