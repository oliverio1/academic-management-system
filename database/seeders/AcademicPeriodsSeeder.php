<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AcademicPeriod;
use App\Models\CyclePartial;
use App\Models\Modality;
use App\Models\SchoolCycle;
use Carbon\Carbon;

class AcademicPeriodsSeeder extends Seeder
{
    public function run(): void
    {
        $sep  = Modality::where('name', 'Bachillerato SEP 26-2')->first();
        if (!$sep) {
            $sep = Modality::first();
        }

        if (! $sep) {
            $this->command->error('No hay modalidades para generar ciclos/parciales');
            return;
        }

        $cycle = SchoolCycle::updateOrCreate(
            ['code' => 'SEP-2026-A'],
            [
                'modality_id' => $sep->id,
                'name' => 'Ciclo Escolar SEP 2026-A',
                'start_date' => '2026-01-19',
                'end_date' => '2026-04-17',
                'is_active' => true,
            ]
        );

        $partials = [
            [
                'name' => 'Parcial 1',
                'code' => 'SEP-2026-A-P1',
                'sort_order' => 1,
                'start_date' => '2026-01-19',
                'end_date' => '2026-02-27',
            ],
            [
                'name' => 'Parcial 2',
                'code' => 'SEP-2026-A-P2',
                'sort_order' => 2,
                'start_date' => '2026-03-02',
                'end_date' => '2026-04-17',
            ],
        ];

        foreach ($partials as $partial) {
            $period = AcademicPeriod::updateOrCreate(
                ['code' => $partial['code']],
                [
                    'modality_id' => $sep->id,
                    'name' => $partial['name'],
                    'start_date' => Carbon::parse($partial['start_date']),
                    'end_date' => Carbon::parse($partial['end_date']),
                    'is_active' => true,
                ]
            );

            CyclePartial::updateOrCreate(
                [
                    'school_cycle_id' => $cycle->id,
                    'sort_order' => $partial['sort_order'],
                ],
                [
                    'academic_period_id' => $period->id,
                    'name' => $partial['name'],
                    'code' => $partial['code'],
                    'start_date' => Carbon::parse($partial['start_date']),
                    'end_date' => Carbon::parse($partial['end_date']),
                    'is_active' => true,
                ]
            );
        }
    }
}
