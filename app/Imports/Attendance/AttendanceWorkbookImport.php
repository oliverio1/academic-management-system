<?php

namespace App\Imports\Attendance;

use App\Services\Imports\AttendanceImportService;
use App\Services\Imports\ImportResult;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class AttendanceWorkbookImport implements WithMultipleSheets
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
        // El * indica: todas las hojas
        return [
            '*' => function (string $sheetName) {
                return new AttendanceSheetImport(
                    $this->service,
                    $this->academicPeriodId,
                    $this->result,
                    $sheetName
                );
            }
        ];
    }
}
