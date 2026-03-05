<?php

namespace App\Imports\Attendance;

use App\Services\Imports\AttendanceImportService;
use App\Services\Imports\ImportResult;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithTitle;

class AttendanceSheetImport implements ToCollection, WithTitle
{
    protected AttendanceImportService $service;
    protected int $academicPeriodId;
    protected ImportResult $result;
    protected string $sheetName;

    public function __construct(
        AttendanceImportService $service,
        int $academicPeriodId,
        ImportResult $result,
        string $sheetName
    ) {
        $this->service = $service;
        $this->academicPeriodId = $academicPeriodId;
        $this->result = $result;
        $this->sheetName = $sheetName;
    }

    public function title(): string
    {
        return $this->sheetName;
    }

    public function collection(Collection $rows)
    {
        $this->service->importSheet(
            $rows,
            $this->sheetName,
            $this->academicPeriodId,
            $this->result
        );
    }
}
