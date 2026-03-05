<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\AcademicCalendarDay;
use Carbon\Carbon;

class AcademicCalendarHolidaySeeder extends Seeder
{
    public function run(): void
    {
        $holidays = [
            // Septiembre 2025
            ['date' => '2025-09-16', 'name' => 'Independencia de México'],
            
            // Noviembre 2025
            ['date' => '2025-11-17', 'name' => 'Revolución'],

            // Febrero 2026
            ['date' => '2026-02-02', 'name' => 'Día de la Constitución'],

            // Marzo 2026
            ['date' => '2026-03-16', 'name' => 'Natalicio de Benito Juárez'],

            // Mayo 2026
            ['date' => '2026-05-01', 'name' => 'Día del Trabajo']
        ];

        foreach ($holidays as $holiday) {
            AcademicCalendarDay::updateOrCreate(
                [
                    'date' => Carbon::parse($holiday['date'])->toDateString(),
                    'modality_id' => null,
                ],
                [
                    'type' => 'holiday',
                    'name' => $holiday['name'],
                    'affects_teachers' => true,
                    'affects_students' => true,
                ]
            );
        }

        $vacationRanges = [
            [
                'name'  => 'Vacaciones de invierno',
                'start' => '2025-12-15',
                'end'   => '2026-01-02',
            ],
            [
                'name'  => 'Vacaciones de Semana Santa',
                'start' => '2026-03-30',
                'end'   => '2026-04-10',
            ],
        ];

        foreach ($vacationRanges as $range) {

            $period = Carbon::parse($range['start'])
                ->daysUntil(Carbon::parse($range['end'])->addDay());

            foreach ($period as $date) {
                AcademicCalendarDay::updateOrCreate(
                    [
                        'date' => $date->toDateString(),
                        'modality_id' => 1,
                    ],
                    [
                        'type' => 'vacation',
                        'name' => $range['name'],
                        'affects_teachers' => true,
                        'affects_students' => true,
                    ]
                );
            }
        }
    }
}
