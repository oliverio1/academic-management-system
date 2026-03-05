<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AcademicPeriod;
use App\Models\Modality;
use Carbon\Carbon;

class AcademicPeriodsSeeder extends Seeder
{
    public function run(): void
    {
        $sep  = Modality::where('name', 'Bachillerato SEP 26-2')->first();
        if (!$sep) {
            $this->command->error('No se encontraron las modalidades');
            return;
        }
        $periods = [
            [
                'modality_id' => $sep->id,
                'name' => 'Primer Periodo',
                'code' => 'SEP-P1',
                'start_date' => '2026-01-19',
                'end_date' => '2026-02-27',
            ],
            [
                'modality_id' => $sep->id,
                'name' => 'Segundo Periodo',
                'code' => 'SEP-P2',
                'start_date' => '2026-03-02',
                'end_date' => '2026-04-17',
            ],
        ];
        foreach ($periods as $period) {
            AcademicPeriod::updateOrCreate(
                ['code' => $period['code']],
                [
                    'modality_id' => $period['modality_id'],
                    'name' => $period['name'],
                    'start_date' => Carbon::parse($period['start_date']),
                    'end_date' => Carbon::parse($period['end_date']),
                    'is_active' => true,
                ]
            );
        }
    }
}