<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Planeacion didactica DGIRE</title>
    <style>
        @page { margin: 32px 48px 28px 48px; }
        body { font-family: Arial, DejaVu Sans, sans-serif; font-size: 11.6px; line-height: 1.45; letter-spacing: 1.22px; color: #000; }
        table { width: 100%; border-collapse: collapse; }
        td, th { border: 1.2px solid #0070C0; padding: 5px 7px; vertical-align: middle; }
        th { font-weight: bold; text-align: center; }
        .no-border, .no-border td { border: none !important; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .dgire-header { text-align: center; margin-top: 6px; margin-bottom: 28px; }
        .dgire-header .line-1 { font-size: 13px; font-weight: bold; }
        .dgire-header .line-2 { font-size: 14px; font-weight: bold; }
        .dgire-header .green-line { color: #0070C0; font-size: 16px; font-weight: bold; }
        .dgire-header .plan-line { font-size: 14px; }
        .section-title { color: #0070C0; font-size: 15px; font-weight: bold; margin: 18px 0 12px; text-transform: uppercase; }
        .subsection-title { color: #0070C0; font-size: 14px; font-weight: bold; margin: 17px 0 5px; }
        .section, .subsection { color: #0070C0; font-weight: bold; background: transparent; border: none !important; padding: 0 0 4px 0; }
        .subtle { font-size: 8px; font-style: italic; font-weight: normal; }
        .green-text { color: #0070C0; font-weight: bold; }
        .green-fill { background: #D9EAF7; }
        .blue-fill { background: #D9EAF7; }
        .director-signature { height: 72px; }
        .schedule-line { display: block; margin: 0 0 3px 0; }
        .small { font-size: 9px; }
        .tiny { font-size: 8px; }
        .big-cell { min-height: 54px; }
        .mb-4 { margin-bottom: 4px; }
        .mb-8 { margin-bottom: 8px; }
        .mb-12 { margin-bottom: 12px; }
        .page-break { page-break-before: always; }
        .top { vertical-align: top; }
        .middle { vertical-align: middle; }
        .objective-row th, .objective-row td { height: 94px; }
        ul.compact { margin: 0; padding-left: 16px; }
    </style>
</head>
<body>
@php
    $assignment->loadMissing([
        'teacher.user',
        'subject',
        'group.level.modality',
        'schedules',
    ]);

    $subject = $assignment->subject;
    $isTheoreticalPractical = ($subject->type ?? \App\Models\Subject::TYPE_THEORETICAL) === \App\Models\Subject::TYPE_THEORETICAL_PRACTICAL;
    $subjectTypeLabel = $isTheoreticalPractical ? 'ASIGNATURA TEÓRICO-PRÁCTICA' : 'ASIGNATURA TEÓRICA';
    $subjectCharacter = $plan->subject_character ?: ($subject->subject_character ?? '-');
    $subjectKey = $plan->subject_key ?: ($subject->subject_key ?? '-');
    $totalAnnualHours = $plan->total_annual_hours ?: ($subject->annual_hours ?? null);
    $formatTeacherName = function (?string $name): string {
        $parts = preg_split('/\s+/u', trim((string) $name)) ?: [];
        $parts = array_values(array_filter($parts, fn ($part) => $part !== ''));

        if (count($parts) < 2) {
            return $name ?: '-';
        }

        if (count($parts) === 2) {
            return $parts[1] . ', ' . $parts[0];
        }

        if (count($parts) === 3) {
            return $parts[1] . ', ' . $parts[0];
        }

        $surnames = array_slice($parts, -2);
        $givenNames = array_slice($parts, 0, -2);

        return implode(' ', $surnames) . ', ' . implode(' ', $givenNames);
    };
    $teacherDisplayName = $formatTeacherName($assignment->teacher->user->name ?? null);

    $activeSchedules = collect($pdf_schedules ?? ($assignment->schedules ?? []))->where('is_active', true);
    $dayLabels = [
        'monday' => 'Lunes',
        'tuesday' => 'Martes',
        'wednesday' => 'Miércoles',
        'thursday' => 'Jueves',
        'friday' => 'Viernes',
    ];

    $practiceScheduleMatcher = function ($schedule): bool {
        $sectionType = mb_strtolower(trim((string) ($schedule->section_type ?? '')), 'UTF-8');

        if ($sectionType === 'lab_taller') {
            return true;
        }

        if ($sectionType === 'english') {
            return false;
        }

        $value = mb_strtolower(trim((string) ($schedule->type ?? '')), 'UTF-8');

        return str_contains($value, 'lab')
            || str_contains($value, 'laboratorio')
            || str_contains($value, 'taller')
            || str_contains($value, 'practica')
            || str_contains($value, 'práctica');
    };

    $practiceScheduleLabel = function ($schedule): string {
        $section = trim((string) ($schedule->section_label ?? ''));
        if ($section === '' && !empty($schedule->section_number)) {
            $section = match ((int) $schedule->section_number) {
                2 => 'B',
                3 => 'C',
                default => 'A',
            };
        }

        return trim('Practica' . ($section !== '' ? ' - seccion ' . $section : ''));
    };

    $scheduleByDay = collect($dayLabels)->mapWithKeys(function ($label, $dayKey) use ($activeSchedules, $isTheoreticalPractical, $practiceScheduleMatcher, $practiceScheduleLabel) {
        $items = $activeSchedules
            ->where('day_of_week', $dayKey)
            ->sortBy('start_time')
            ->map(function ($schedule) use ($isTheoreticalPractical, $practiceScheduleMatcher, $practiceScheduleLabel) {
                $start = $schedule->start_time ? substr((string) $schedule->start_time, 0, 5) : '';
                $end = $schedule->end_time ? substr((string) $schedule->end_time, 0, 5) : '';
                $time = trim($start . '-' . $end, '-');

                if ($isTheoreticalPractical && $practiceScheduleMatcher($schedule)) {
                    return trim($time . ' ' . $practiceScheduleLabel($schedule));
                }

                return trim($time . ($isTheoreticalPractical ? ' Teoria' : ''));
            })
            ->filter()
            ->unique()
            ->values();

        return [$dayKey => $items];
    });

    $practiceSchedulesByDay = $activeSchedules
        ->filter($practiceScheduleMatcher)
        ->groupBy('day_of_week');

    $extractKey = function (?string $value): string {
        $value = trim((string) $value);
        if ($value === '') {
            return '-';
        }
        if (preg_match('/^([0-9]+(?:\.[0-9]+)*)/u', $value, $matches) === 1) {
            return $matches[1];
        }
        return '-';
    };

    $stripLeadingKey = function (?string $value): string {
        $value = trim((string) $value);
        $value = preg_replace('/\s*\|\s*Objetivo\s+especifico\s*:.*$/ui', '', $value) ?: $value;
        $value = preg_replace('/^[0-9]+(?:\.[0-9]+)*\s*/u', '', $value) ?: $value;

        return trim($value);
    };

    $extractObjective = function (?string $value): string {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/Objetivo\s+especifico\s*:\s*(.*)$/ui', $value, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }

        return '';
    };

    $temarioPointText = function ($point) {
        $label = trim((string) ($point->label ?? ''));
        $content = trim((string) ($point->content ?? ''));
        if ($label !== '' && $content !== '') {
            return trim($label . ' ' . $content);
        }

        return $label !== '' ? $label : $content;
    };

    $contentTypeBucket = function (?string $type): string {
        $type = mb_strtolower(trim((string) $type), 'UTF-8');
        $type = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $type);

        if (str_contains($type, 'proced')) {
            return 'procedural';
        }

        if (str_contains($type, 'actitud')) {
            return 'attitudinal';
        }

        return 'conceptual';
    };

    $rowsCollection = collect($rows ?? []);
    $dgireMetadata = collect($plan->dgire_metadata ?? []);
    $metadataUnits = collect($dgireMetadata->get('units', []));
    $metadataUnitsByNumber = $metadataUnits->keyBy(fn ($unit) => (string) ($unit['number'] ?? ''));
    $metadataEncadre = collect($dgireMetadata->get('encuadre', []));
    $unitBlocks = $rowsCollection
        ->groupBy(fn ($row) => (string) ($row['field_training'] ?? '-'))
        ->map(function ($unitRows, $unitName) use ($extractKey, $stripLeadingKey, $metadataUnitsByNumber) {
            $first = $unitRows->first();
            $unitNumber = $extractKey($unitName);
            $metadata = collect($metadataUnitsByNumber->get($unitNumber, []));
            $contentNumbers = $unitRows
                ->flatMap(function ($row) use ($extractKey) {
                    $values = [$extractKey($row['topic'] ?? '')];
                    $subtopics = preg_split('/[;,]+/u', (string) ($row['subtopics'] ?? '')) ?: [];
                    foreach ($subtopics as $subtopic) {
                        $key = $extractKey($subtopic);
                        if ($key !== '-') {
                            $values[] = $key;
                        }
                    }
                    return $values;
                })
                ->filter(fn ($value) => $value !== '-')
                ->unique()
                ->values();

            return [
                'name' => $stripLeadingKey($unitName) ?: '-',
                'number' => $unitNumber,
                'objective' => $first['objective'] ?? '-',
                'contents' => $contentNumbers->implode(', '),
                'hours' => (int) ($metadata->get('hours') ?? $unitRows->count()),
                'start_date' => $metadata->get('start_date'),
                'end_date' => $metadata->get('end_date'),
                'rows' => $unitRows->values(),
            ];
        })
        ->values();

    $temarioUnitsByNumber = collect($assignment->subject->temarios ?? [])
        ->flatMap(fn ($temario) => collect($temario->points ?? []))
        ->filter(fn ($point) => (int) ($point->level ?? 0) === 1)
        ->mapWithKeys(function ($point) use ($extractKey, $stripLeadingKey, $extractObjective) {
            $label = trim((string) ($point->label ?? ''));
            $content = trim((string) ($point->content ?? ''));
            $combined = trim($label . ' ' . $content);
            $number = $extractKey($label !== '' ? $label : $combined);

            if ($number === '-') {
                return [];
            }

            $objective = $extractObjective($content) ?: $extractObjective($combined);

            return [$number => [
                'number' => $number,
                'name' => $stripLeadingKey($content !== '' ? $content : $combined),
                'objective' => $objective,
            ]];
        });

    $temarioContentsByKey = collect($assignment->subject->temarios ?? [])
        ->flatMap(fn ($temario) => collect($temario->points ?? []))
        ->filter(fn ($point) => (int) ($point->level ?? 0) > 1)
        ->mapWithKeys(function ($point) use ($extractKey, $temarioPointText, $contentTypeBucket) {
            $key = $extractKey(trim((string) ($point->label ?? '')) ?: $temarioPointText($point));
            if ($key === '-') {
                return [];
            }

            $unitNumber = explode('.', $key)[0] ?? '';
            if ($unitNumber === '') {
                return [];
            }

            return [$key => [
                'key' => $key,
                'unit_number' => $unitNumber,
                'type' => $contentTypeBucket($point->type ?? ''),
                'text' => $temarioPointText($point),
            ]];
        });

    $temarioContentsByUnit = $temarioContentsByKey
        ->groupBy('unit_number')
        ->map(function ($contents) {
            return collect([
                'conceptual' => collect($contents)->where('type', 'conceptual')->pluck('text')->unique()->values(),
                'procedural' => collect($contents)->where('type', 'procedural')->pluck('text')->unique()->values(),
                'attitudinal' => collect($contents)->where('type', 'attitudinal')->pluck('text')->unique()->values(),
            ]);
        });

    $unitBlocks = $unitBlocks
        ->map(function ($unit) use ($temarioUnitsByNumber) {
            $temarioUnit = collect($temarioUnitsByNumber->get((string) ($unit['number'] ?? ''), []));
            if (($unit['objective'] ?? '-') === '-' && $temarioUnit->get('objective')) {
                $unit['objective'] = $temarioUnit->get('objective');
            }
            if (($unit['name'] ?? '-') === '-' && $temarioUnit->get('name')) {
                $unit['name'] = $temarioUnit->get('name');
            }

            return $unit;
        })
        ->values();

    $parsePdfDate = function ($value) {
        try {
            $value = trim((string) $value);
            if ($value === '' || $value === '-') {
                return null;
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/u', $value) === 1) {
                return \Carbon\Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
            }

            return \Carbon\Carbon::createFromFormat('d/m/Y', $value)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    };
    $allStartDates = $rowsCollection->pluck('start_date')->map($parsePdfDate)->filter()->sortBy(fn ($date) => $date->timestamp)->values();
    $allEndDates = $rowsCollection->pluck('end_date')->map($parsePdfDate)->filter()->sortBy(fn ($date) => $date->timestamp)->values();
    $periodRange = ($allStartDates->first()?->format('d/m/Y') ?: '-') . ' a ' . ($allEndDates->last()?->format('d/m/Y') ?: '-');
    $sessionDates = $allStartDates->merge($allEndDates)->filter()->unique(fn ($date) => $date->format('Y-m-d'))->map(fn ($date) => $date->format('d/m/Y'))->implode(', ');
    $allSessionDates = $allStartDates
        ->merge($allEndDates)
        ->filter()
        ->unique(fn ($date) => $date->format('Y-m-d'))
        ->sortBy(fn ($date) => $date->timestamp)
        ->values();
    $practiceSessionDetails = $allSessionDates
        ->map(function ($date) use ($practiceSchedulesByDay, $practiceScheduleLabel) {
            $dayKey = mb_strtolower($date->format('l'), 'UTF-8');
            $matchingSchedules = collect($practiceSchedulesByDay->get($dayKey, collect()));

            if ($matchingSchedules->isEmpty()) {
                return null;
            }

            $labels = $matchingSchedules
                ->map(fn ($schedule) => $practiceScheduleLabel($schedule))
                ->filter()
                ->unique()
                ->implode(', ');

            return trim(($labels !== '' ? $labels . ': ' : '') . $date->format('d/m/Y'));
        })
        ->filter()
        ->values();
    $practiceSessionDates = $practiceSessionDetails->implode('; ');
    $theorySessionDates = $allSessionDates
        ->map(fn ($date) => $date->format('d/m/Y'))
        ->implode(', ');
    $evaluationItems = $rowsCollection->pluck('evaluation')->filter(fn ($value) => $value && $value !== '-')->unique()->implode('; ');
    $resourceMaterials = trim((string) ($plan->general_resources ?: ''));
    $lastEvaluationTypeByPeriod = [];
    $periodEvaluationRows = collect(preg_split('/\r\n|\r|\n/u', (string) ($plan->evaluation_instruments ?? '')) ?: [])
        ->map(function ($line) use (&$lastEvaluationTypeByPeriod) {
            $line = trim((string) $line);
            if ($line === '') {
                return null;
            }

            $period = 'Periodo 1';
            if (preg_match('/^(Periodo\s+[0-9]+)\s*-\s*(.*)$/u', $line, $periodMatches) === 1) {
                $period = trim($periodMatches[1] ?? 'Periodo 1');
                $line = trim($periodMatches[2] ?? '');
            }

            if (preg_match('/^(.*?):\s*(.*?)(?:\s*\(([^)]*)\))?(?:\s*-\s*(.*))?$/u', $line, $matches) === 1) {
                $type = trim($matches[1] ?? '');
                if ($type === '') {
                    $type = $lastEvaluationTypeByPeriod[$period] ?? '1. Evaluación continua';
                } else {
                    $lastEvaluationTypeByPeriod[$period] = $type;
                }

                return [
                    'period' => $period,
                    'type' => $type,
                    'element' => trim($matches[2] ?? ''),
                    'weight' => trim($matches[3] ?? ''),
                    'notes' => trim($matches[4] ?? ''),
                ];
            }

            return ['period' => $period, 'type' => '1. Evaluación continua', 'element' => $line, 'weight' => '', 'notes' => ''];
        })
        ->filter()
        ->values();
    $evaluationRowsByPeriod = $periodEvaluationRows
        ->reject(function ($row) {
            $type = mb_strtolower((string) ($row['type'] ?? ''), 'UTF-8');
            return str_contains($type, 'criterios de exencion')
                || str_contains($type, 'asignacion de calificaciones');
        })
        ->groupBy(fn ($row) => (string) ($row['period'] ?? 'Periodo 1'));
    $continuousEvaluationRows = $periodEvaluationRows
        ->filter(fn ($row) => str_contains(mb_strtolower($row['type'] ?? '', 'UTF-8'), 'continua'))
        ->values();
    $finalEvaluationRows = $periodEvaluationRows
        ->filter(fn ($row) => str_contains(mb_strtolower($row['type'] ?? '', 'UTF-8'), 'final'))
        ->values();
    $unitPeriodLabels = $unitBlocks
        ->map(fn ($unit) => $unit['number'] !== '-' ? 'Unidad ' . $unit['number'] : null)
        ->filter()
        ->values();
    $metadataPeriodRows = collect($dgireMetadata->get('periods', []))
        ->map(fn ($period) => [
            $period['period'] ?? '',
            $period['unit_text'] ?? '',
            $period['theory_dates'] ?? '',
            $period['practices'] ?? '',
        ])
        ->filter(fn ($period) => collect($period)->filter()->isNotEmpty())
        ->values();
    $periodRows = $metadataPeriodRows->isNotEmpty()
        ? $metadataPeriodRows->all()
        : ($isTheoreticalPractical
        ? [
            ['Periodo 1', $unitPeriodLabels->get(0, 'Unidad 1'), $theorySessionDates ?: ($sessionDates ?: 'Por definir'), $practiceSessionDates ?: 'Por definir'],
            ['Periodo 2', $unitPeriodLabels->get(0, 'Unidad 1'), 'Por definir', 'Por definir'],
            ['Periodo 3', $unitPeriodLabels->get(1, 'Unidad 2'), 'Por definir', 'Por definir'],
            ['Periodo 4', $unitPeriodLabels->get(2, 'Unidad 3'), 'Por definir', 'Por definir'],
        ]
        : [
            ['Periodo 1', $unitPeriodLabels->get(0, 'Unidad 1'), $periodRange !== '- a -' ? $periodRange : 'Por definir'],
            ['Periodo 2', $unitPeriodLabels->get(0, 'Unidad 1'), 'Por definir'],
            ['Periodo 3', $unitPeriodLabels->get(1, 'Unidad 2'), 'Por definir'],
            ['Periodo 4', $unitPeriodLabels->get(2, 'Unidad 3'), 'Por definir'],
        ]);

    $parseDateListFromText = function (?string $value) {
        preg_match_all('/\b\d{1,2}\/\d{1,2}\/\d{4}\b/u', (string) $value, $matches);

        return collect($matches[0] ?? [])
            ->map(function ($dateText) {
                try {
                    return \Carbon\Carbon::createFromFormat('d/m/Y', $dateText)->startOfDay();
                } catch (\Throwable $e) {
                    return null;
                }
            })
            ->filter()
            ->unique(fn ($date) => $date->format('Y-m-d'))
            ->sortBy(fn ($date) => $date->timestamp)
            ->values();
    };

    $extractUnitNumbersFromText = function (?string $value) {
        preg_match_all('/\b(?:Unidad\s*)?(\d+)\b/ui', (string) $value, $matches);

        return collect($matches[1] ?? [])
            ->map(fn ($number) => (string) $number)
            ->unique()
            ->values();
    };

    $practiceHoursByUnitNumber = collect();

    if ($isTheoreticalPractical) {
        $unitDateRanges = $unitBlocks
            ->map(function ($unit) use ($parsePdfDate) {
                return [
                    'number' => (string) ($unit['number'] ?? ''),
                    'start' => $parsePdfDate($unit['start_date'] ?? null),
                    'end' => $parsePdfDate($unit['end_date'] ?? null),
                ];
            })
            ->filter(fn ($unit) => $unit['number'] !== '')
            ->values();

        foreach ($periodRows as $periodRow) {
            $unitNumbers = $extractUnitNumbersFromText($periodRow[1] ?? '');
            $practiceText = trim((string) ($periodRow[3] ?? ''));

            if ($practiceText === '' || mb_strtolower($practiceText, 'UTF-8') === 'por definir') {
                continue;
            }

            $practiceDates = $parseDateListFromText($practiceText);

            if ($practiceDates->isNotEmpty()) {
                foreach ($practiceDates as $practiceDate) {
                    $matchingUnit = $unitDateRanges
                        ->first(fn ($unit) => $unit['start'] && $unit['end'] && $practiceDate->betweenIncluded($unit['start'], $unit['end']));
                    $targetUnitNumber = (string) ($matchingUnit['number'] ?? $unitNumbers->first());

                    if ($targetUnitNumber !== '') {
                        $practiceHoursByUnitNumber[$targetUnitNumber] = (float) ($practiceHoursByUnitNumber->get($targetUnitNumber, 0)) + 0.5;
                    }
                }

                continue;
            }

            foreach ($unitNumbers as $unitNumber) {
                $practiceHoursByUnitNumber[$unitNumber] = (float) ($practiceHoursByUnitNumber->get($unitNumber, 0)) + 0.5;
            }
        }
    }

    $periodPlanningBlocks = collect($periodRows)
        ->map(function ($periodRow, $periodIndex) use ($rowsCollection, $unitBlocks, $extractKey, $parsePdfDate, $parseDateListFromText, $extractUnitNumbersFromText, $temarioContentsByKey, $temarioContentsByUnit) {
            $periodName = (string) ($periodRow[0] ?? ('Periodo ' . ($periodIndex + 1)));
            $periodUnitText = (string) ($periodRow[1] ?? '');
            $periodDateText = (string) ($periodRow[2] ?? '');
            $periodDates = $parseDateListFromText($periodDateText);
            $periodStart = $periodDates->first();
            $periodEnd = $periodDates->last();

            $periodRowsCollection = $rowsCollection
                ->filter(function ($row) use ($periodDates, $periodStart, $periodEnd, $parsePdfDate) {
                    $rowDate = $parsePdfDate($row['start_date'] ?? null);
                    if (! $rowDate) {
                        return false;
                    }

                    if ($periodDates->contains(fn ($date) => $date->isSameDay($rowDate))) {
                        return true;
                    }

                    return $periodStart && $periodEnd && $rowDate->betweenIncluded($periodStart, $periodEnd);
                })
                ->values();

            if ($periodRowsCollection->isEmpty()) {
                $unitNumbersFromText = $extractUnitNumbersFromText($periodUnitText);

                if ($unitNumbersFromText->isNotEmpty()) {
                    $periodRowsCollection = $rowsCollection
                        ->filter(fn ($row) => $unitNumbersFromText->contains((string) $extractKey($row['field_training'] ?? '')))
                        ->values();
                }
            }

            $unitNumbers = $periodRowsCollection
                ->map(fn ($row) => (string) $extractKey($row['field_training'] ?? ''))
                ->filter(fn ($number) => $number !== '-')
                ->unique()
                ->values();

            $units = $unitBlocks
                ->filter(fn ($unit) => $unitNumbers->contains((string) ($unit['number'] ?? '')))
                ->map(function ($unit) use ($periodRowsCollection, $extractKey, $temarioContentsByKey, $temarioContentsByUnit) {
                    $unitNumber = (string) ($unit['number'] ?? '');
                    $unitRows = $periodRowsCollection
                        ->filter(fn ($row) => (string) $extractKey($row['field_training'] ?? '') === $unitNumber)
                        ->values();
                    $contentKeys = $unitRows
                        ->flatMap(function ($row) use ($extractKey) {
                            $values = [$extractKey($row['topic'] ?? '')];
                            $subtopics = preg_split('/[;,]+/u', (string) ($row['subtopics'] ?? '')) ?: [];
                            foreach ($subtopics as $subtopic) {
                                $values[] = $extractKey($subtopic);
                            }

                            return $values;
                        })
                        ->filter(fn ($key) => $key !== '-')
                        ->unique()
                        ->values();
                    $periodContents = collect([
                        'conceptual' => collect(),
                        'procedural' => collect(),
                        'attitudinal' => collect(),
                    ]);

                    foreach ($contentKeys as $contentKey) {
                        $content = collect($temarioContentsByKey->get((string) $contentKey, []));
                        if ($content->isEmpty()) {
                            continue;
                        }

                        $type = $content->get('type', 'conceptual');
                        $periodContents[$type] = collect($periodContents[$type] ?? [])->push($content->get('text'));
                    }

                    $hasSpecificContents = $periodContents
                        ->flatMap(fn ($items) => collect($items))
                        ->filter()
                        ->isNotEmpty();
                    $fallbackContents = collect($temarioContentsByUnit->get($unitNumber, []));
                    $fallbackPeriodContents = collect([
                        'conceptual' => collect($fallbackContents->get('conceptual', []))->filter()->unique()->values(),
                        'procedural' => collect($fallbackContents->get('procedural', []))->filter()->unique()->values(),
                        'attitudinal' => collect($fallbackContents->get('attitudinal', []))->filter()->unique()->values(),
                    ]);
                    $specificPeriodContents = $periodContents->map(fn ($items) => collect($items)->filter()->unique()->values());

                    $unit['period_contents'] = $hasSpecificContents
                        ? collect([
                            'conceptual' => collect($specificPeriodContents->get('conceptual', []))->isNotEmpty()
                                ? collect($specificPeriodContents->get('conceptual', []))
                                : collect($fallbackPeriodContents->get('conceptual', [])),
                            'procedural' => collect($specificPeriodContents->get('procedural', []))->isNotEmpty()
                                ? collect($specificPeriodContents->get('procedural', []))
                                : collect($fallbackPeriodContents->get('procedural', [])),
                            'attitudinal' => collect($specificPeriodContents->get('attitudinal', []))->isNotEmpty()
                                ? collect($specificPeriodContents->get('attitudinal', []))
                                : collect($fallbackPeriodContents->get('attitudinal', [])),
                        ])
                        : $fallbackPeriodContents;

                    return $unit;
                })
                ->values();

            return [
                'name' => $periodName,
                'units' => $units,
                'rows' => $periodRowsCollection,
            ];
        })
        ->filter(fn ($period) => collect($period['units'] ?? [])->isNotEmpty() || collect($period['rows'] ?? [])->isNotEmpty())
        ->values();

    if ($periodPlanningBlocks->isEmpty()) {
        $periodPlanningBlocks = collect([[
            'name' => 'Periodo 1',
            'units' => $unitBlocks,
            'rows' => $rowsCollection,
        ]]);
    }
@endphp

<div class="dgire-header">
    <div class="line-2">Formato para la planeación didáctica</div>
    <div class="green-line">{{ $subjectTypeLabel }}</div>
    <div class="green-line">ESCUELA NACIONAL PREPARATORIA</div>
    <div class="plan-line">Plan de estudios 1996</div>
    <div class="plan-line">(actualizado a 2016)</div>
</div>

<div class="section-title">1. Datos de identificación</div>
<div class="subsection-title">1.1. Datos de la Institución del Sistema Incorporado</div>
<table class="mb-8">
    <tr>
        <th style="width:18%;">Nombre de la ISI</th>
        <td class="center" colspan="5">Universidad Latinoamericana</td>
    </tr>
    <tr>
        <th style="width:32%;">Clave de incorporación a la UNAM</th>
        <td class="center" style="width:12%;">{{ $plan->unam_incorporation_key ?: '-' }}</td>
        <th style="width:26%;">Ciclo escolar</th>
        <td class="center" colspan="3">{{ $ciclo_escolar_label ?? ($plan->schoolCycle->name ?? '-') }}</td>
    </tr>
</table>

<div class="subsection-title">1.2. Datos del (de la) docente</div>
<table class="mb-8">
    <tr>
        <th style="width:23%;">Nombre del (de la) docente</th>
        <td class="center" style="width:37%;">{{ $teacherDisplayName }}</td>
        <th style="width:24%;">No. de Expediente<br>DGIRE-UNAM</th>
        <td class="center" style="width:16%;">{{ $plan->teacher_dgire_file ?: '-' }}</td>
    </tr>
    <tr>
        <th>Fecha de elaboración</th>
        <td class="center">{{ optional($plan->created_at)->format('d/m/Y') ?: now()->format('d/m/Y') }}</td>
        <td rowspan="2" colspan="2" class="center bold director-signature">Miriam Paola Pérez Luna<br>Dirección Técnica</td>
    </tr>
    <tr>
        <th>Fecha de revisión de la DT</th>
        <td class="center">{{ optional($plan->technical_review_date)->format('d/m/Y') ?: '-' }}</td>
    </tr>
</table>

<div class="subsection-title">1.3. Datos del programa indicativo/analítico de la asignatura</div>
<table class="mb-8">
    @if($isTheoreticalPractical)
        <tr>
            <th style="width:22%;">Nombre de la asignatura</th>
            <td class="center" colspan="5">{{ $assignment->subject->name ?? '-' }}</td>
        </tr>
        <tr>
            <th>Caracter de la asignatura<br><span class="subtle">(Obligatoria, Optativa general u Obligatoria de eleccion)</span></th>
            <td class="center" style="width:14%;">{{ $subjectCharacter ?: '-' }}</td>
            <th style="width:18%;">Clave de la asignatura</th>
            <td class="center" colspan="3">{{ $subjectKey ?: '-' }}</td>
        </tr>
        <tr class="blue-fill">
            <th>Horas anuales</th>
            <td class="center">{{ $totalAnnualHours ?: '-' }}</td>
            <th>Horas teóricas</th>
            <td class="center">{{ $subject->annual_theory_hours ?: '-' }}</td>
            <th style="width:18%;">Horas prácticas</th>
            <td class="center" style="width:14%;">{{ $subject->annual_practice_hours ?: '-' }}</td>
        </tr>
        <tr class="blue-fill">
            <th>Horas por semana</th>
            <td class="center">{{ $assignment->subject->hours_per_week ?? '-' }}</td>
            <th>Horas teóricas</th>
            <td class="center">{{ $subject->weekly_theory_hours ?: '-' }}</td>
            <th>Horas prácticas</th>
            <td class="center">{{ $subject->weekly_practice_hours ?: '-' }}</td>
        </tr>
        <tr class="objective-row">
            <th>Objetivo general de la asignatura:<br><br><span class="subtle">(Para consultar el programa indicativo/analítico oficial de su asignatura, remitase a la Dirección Técnica de su ISI. Asimismo, puede consultarlo en el sitio web de la DGIRE o en el sitio web de la ENP)</span></th>
            <td colspan="5" class="top big-cell">{{ $plan->objective ?: '-' }}</td>
        </tr>
    @else
        <tr>
            <th style="width:30%;">Nombre de la asignatura</th>
            <td class="center" colspan="3">{{ $assignment->subject->name ?? '-' }}</td>
        </tr>
        <tr>
            <th>Caracter de la asignatura<br><span class="subtle">(Obligatoria, Optativa general u Obligatoria de elección)</span></th>
            <td class="center" style="width:20%;">{{ $subjectCharacter ?: '-' }}</td>
            <th style="width:25%;">Clave de la asignatura</th>
            <td class="center" style="width:25%;">{{ $subjectKey ?: '-' }}</td>
        </tr>
        <tr class="blue-fill">
            <th>Horas anuales</th>
            <td class="center">{{ $totalAnnualHours ?: '-' }}</td>
            <th>Horas por semana</th>
            <td class="center">{{ $assignment->subject->hours_per_week ?? '-' }}</td>
        </tr>
        <tr class="objective-row">
            <th>Objetivo general de la asignatura<br><br><span class="subtle">(Para consultar el programa indicativo/analítico oficial de su asignatura, remitase a la Dirección Técnica de su ISI. Asimismo, puede consultarlo en el sitio web de la DGIRE o en el sitio web de la ENP)</span></th>
            <td colspan="3" class="top big-cell">{{ $plan->objective ?: '-' }}</td>
        </tr>
    @endif
</table>

<div class="page-break"></div>
<div class="subsection-title">1.4. Grupo(s) y horario(s) de la asignatura</div>
<table class="mb-8">
    <colgroup>
        <col style="width:16%;">
        <col style="width:16%;">
        <col style="width:17%;">
        <col style="width:17%;">
        <col style="width:17%;">
        <col style="width:17%;">
    </colgroup>
    <tr>
        <th colspan="2">Número de grupos</th>
        <td class="center">1</td>
        <td colspan="3" class="no-border"></td>
    </tr>
    <tr>
        <th>Clave de grupo<br><span class="subtle">(Dado de alta ante DGIRE)</span></th>
        @foreach($dayLabels as $dayLabel)
            <th>{{ $dayLabel }}</th>
        @endforeach
    </tr>
    <tr>
        <td class="center">{{ $assignment->group->name ?? '-' }}</td>
        @foreach(array_keys($dayLabels) as $dayKey)
            <td class="center">
                @forelse($scheduleByDay->get($dayKey, collect()) as $scheduleLine)
                    <span class="schedule-line">{{ $scheduleLine }}</span>
                @empty
                    -
                @endforelse
            </td>
        @endforeach
    </tr>
</table>

<div class="subsection-title">1.5. Cantidad de horas para el encuadre didáctico y las unidades</div>
<table class="mb-8">
    @if($isTheoreticalPractical)
        <tr class="center bold">
            <th rowspan="2" style="width:40%;">Unidades</th>
            <th colspan="2" style="width:30%;">Horas señaladas por el programa indicativo</th>
            <th colspan="2" style="width:30%;">Horas establecidas por el (la) docente</th>
        </tr>
        <tr class="center bold blue-fill">
            <th style="width:15%;">Teoría</th>
            <th style="width:15%;">Práctica</th>
            <th style="width:15%;">Teoría</th>
            <th style="width:15%;">Práctica</th>
        </tr>
    @else
        <tr class="center bold">
            <th style="width:50%;">Unidades</th>
            <th style="width:25%;">Horas señaladas por el programa indicativo</th>
            <th style="width:25%;">Horas establecidas por el (la) docente</th>
        </tr>
    @endif
    <tr>
        <td class="big-cell">Encuadre didáctico</td>
        @if($isTheoreticalPractical)
            <td class="center">N/A</td>
            <td class="center">N/A</td>
            <td class="center">{{ (int) ($metadataEncadre->get('hours') ?? 0) ?: '' }}</td>
            <td class="center"></td>
        @else
            <td class="center">N/A</td>
            <td class="center">{{ (int) ($metadataEncadre->get('hours') ?? 0) ?: '' }}</td>
        @endif
    </tr>
    @php
        $unitCountForProgramHours = max(1, $unitBlocks->count());
        $programTheoryHoursPerUnit = $isTheoreticalPractical && !empty($subject->annual_theory_hours)
            ? (int) round(((int) $subject->annual_theory_hours) / $unitCountForProgramHours)
            : null;
        $programPracticeHoursPerUnit = $isTheoreticalPractical && !empty($subject->annual_practice_hours)
            ? (int) round(((int) $subject->annual_practice_hours) / $unitCountForProgramHours)
            : null;
        $programHoursPerUnit = !$isTheoreticalPractical && !empty($totalAnnualHours)
            ? (int) round(((int) $totalAnnualHours) / $unitCountForProgramHours)
            : null;
        $docenteTheoryTotal = (int) ($metadataEncadre->get('hours') ?? 0) + $unitBlocks->sum(fn ($unit) => (int) ($unit['hours'] ?? 0));
        $docentePracticeTotal = $practiceHoursByUnitNumber->sum();
    @endphp
    @forelse($unitBlocks as $unit)
        <tr>
            <td class="big-cell">Unidad {{ $unit['number'] }}. {{ $unit['name'] }}</td>
            @if($isTheoreticalPractical)
                <td class="center">{{ $programTheoryHoursPerUnit ?: '' }}</td>
                <td class="center">{{ $programPracticeHoursPerUnit ?: '' }}</td>
                <td class="center">{{ (int) ($unit['hours'] ?? 0) ?: '' }}</td>
                <td class="center">{{ ($value = (float) ($practiceHoursByUnitNumber->get((string) ($unit['number'] ?? ''), 0))) > 0 ? rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.') : '' }}</td>
            @else
                <td class="center">{{ $programHoursPerUnit ?: '' }}</td>
                <td class="center">{{ (int) ($unit['hours'] ?? 0) ?: '' }}</td>
            @endif
        </tr>
    @empty
        <tr><td colspan="{{ $isTheoreticalPractical ? 5 : 3 }}" class="center">Sin unidades registradas.</td></tr>
    @endforelse
    <tr>
        <th>TOTALES</th>
        @if($isTheoreticalPractical)
            <td class="center">{{ $subject->annual_theory_hours ?: '' }}</td>
            <td class="center">{{ $subject->annual_practice_hours ?: '' }}</td>
            <td class="center">{{ $docenteTheoryTotal ?: '' }}</td>
            <td class="center">{{ $docentePracticeTotal ?: '' }}</td>
        @else
            <td class="center">{{ $totalAnnualHours ?: '' }}</td>
            <td class="center">{{ $docenteTheoryTotal ?: '' }}</td>
        @endif
    </tr>
    <tr>
        <td colspan="{{ $isTheoreticalPractical ? 5 : 3 }}" style="height:52px;"><span class="bold">Observaciones</span><br>{{ $plan->notes ?: 'Las unidades se distribuiran en 4 periodos de evaluacion.' }}</td>
    </tr>
</table>

<div class="subsection-title">
    1.6. Calendarizacion global de periodos de evaluación
    <span class="subtle">(Este apartado debe alinearse con el reglamento interno de la ISI y lo reportado en la DGIRE)</span>
</div>
<table class="mb-8">
    <tr class="center bold">
        <th style="width:14%;">Periodos<br><span class="subtle">(Añada o elimine tantos periodos requiera, de acuerdo con lo reportado en la DGIRE)</span></th>
        <th style="width:20%;">Unidad y/o fracción de unidad que comprende el periodo</th>
        @if($isTheoreticalPractical)
            <th style="width:20%;">Fechas de las sesiones teóricas de clase comprendidas en el periodo</th>
            <th style="width:46%;">Número de práctica, sección, etapa de la práctica y fecha(s) de la(s) sesión(es)</th>
        @else
            <th style="width:66%;">Fechas que comprende el periodo<br><span class="subtle">Ejemplo: Del 5 de agosto al 27 de septiembre de 2024</span></th>
        @endif
    </tr>
    @foreach($periodRows as $periodRow)
        <tr>
            <td class="center">{{ $periodRow[0] }}</td>
            <td class="center">{{ $periodRow[1] }}</td>
            <td class="center">{{ $periodRow[2] }}</td>
            @if($isTheoreticalPractical)
                <td class="center">{{ $periodRow[3] }}</td>
            @endif
        </tr>
    @endforeach
</table>

<div class="subsection-title">1.7. Evaluación correspondiente a cada periodo <span class="subtle">(Si los periodos se evaluan de manera diferente, agregue un nuevo cuadro para cada caso)</span></div>
@forelse($evaluationRowsByPeriod as $periodName => $evaluationRows)
    @php
        $evaluationRows = collect($evaluationRows)->values();
        $typeGroups = $evaluationRows
            ->groupBy(fn ($row) => trim((string) ($row['type'] ?? '')))
            ->filter(fn ($rows, $type) => $type !== '')
            ->values();
        $periodRowspan = max(1, $evaluationRows->count());
    @endphp
    <table class="mb-8">
        <tr class="center bold">
            <th rowspan="{{ $periodRowspan + 1 }}" class="blue-fill middle" style="width:20%;">{{ $periodName }}</th>
            <th style="width:35%;">Tipo de evaluación</th>
            <th style="width:30%;">Elementos por evaluar</th>
            <th style="width:15%;">Ponderación (%)</th>
        </tr>
        @foreach($typeGroups as $typeRows)
            @php
                $typeRows = collect($typeRows)->values();
                $type = trim((string) ($typeRows->first()['type'] ?? ''));
                $typeRowspan = max(1, $typeRows->count());
            @endphp
            @foreach($typeRows as $evaluationRow)
                <tr>
                    @if($loop->first)
                        <td rowspan="{{ $typeRowspan }}" class="center bold middle">{{ $type }}</td>
                    @endif
                    <td class="center">{{ $evaluationRow['element'] }}{{ !empty($evaluationRow['notes']) ? ' - ' . $evaluationRow['notes'] : '' }}</td>
                    <td class="center">{{ $evaluationRow['weight'] ?: '' }}</td>
                </tr>
            @endforeach
        @endforeach
    </table>
@empty
    <table class="mb-8">
        <tr class="center bold">
            <th rowspan="3" class="blue-fill middle" style="width:20%;">Periodo 1</th>
            <th style="width:35%;">Tipo de evaluación</th>
            <th style="width:30%;">Elementos por evaluar</th>
            <th style="width:15%;">Ponderación (%)</th>
        </tr>
        <tr>
            <td class="center bold">1. Evaluación continua</td>
            <td class="center">{{ $evaluationItems ?: '-' }}</td>
            <td class="center"></td>
        </tr>
        <tr>
            <td class="center bold">2. Examen de periodo</td>
            <td class="center"></td>
            <td class="center"></td>
        </tr>
    </table>
@endforelse

<div class="subsection-title">1.8. Criterios de exención y de asignación de calificaciones</div>
<table class="mb-8">
    <tr>
        <td style="width:38%;">Criterios de exención (en su caso)</td>
        <td>80% de asistencias.<br>90% de las actividades y trabajos entregados.<br>Promedio de 8.5 en los cuatro bimestres.</td>
    </tr>
    <tr>
        <td>Asignación de calificaciones</td>
        <td>Cuando se exenta, la calificación final será el promedio obtenido en los cuatro parciales. En caso de no exentar, el promedio de las calificaciones bimestrales representa el 50% de la calificación final, el restante 50% corresponde al examen final primera o segunda vuelta.</td>
    </tr>
</table>

<div class="page-break"></div>
<div class="section-title">2. Planeación del periodo</div>
@foreach($periodPlanningBlocks as $periodBlock)
@php
    $periodNumber = preg_match('/\d+/u', (string) ($periodBlock['name'] ?? ''), $periodMatches) === 1
        ? ($periodMatches[0] ?? $periodBlock['name'])
        : ($periodBlock['name'] ?? '-');
@endphp
<table class="mb-4">
    <tr class="blue-fill bold">
        <td colspan="5">Número de periodo: {{ $periodNumber }}</td>
    </tr>
    @forelse($periodBlock['units'] as $unit)
        <tr>
            <th style="width:25%; text-align:left;">Número(s) y título(s) de<br>la(s) unidad(es)</th>
            <td colspan="4">Unidad {{ $unit['number'] }}. {{ $unit['name'] }}</td>
        </tr>
        <tr>
            <th style="text-align:left;">Objetivo(s) específico(s) de<br>la(s) unidad(es)</th>
            <td colspan="4" class="big-cell">{{ $unit['objective'] }}</td>
        </tr>
        <tr>
            <th colspan="5" class="green-fill">Contenidos de la(s) unidad(es) que se revisarán en este periodo<br><span class="subtle">Indique solo los contenidos que se revisarán en este periodo</span></th>
        </tr>
        <tr>
            <th colspan="2" class="blue-fill" style="width:40%;">Conceptuales</th>
            <th colspan="2" class="blue-fill" style="width:40%;">Procedimentales</th>
            <th class="blue-fill" style="width:20%;">Actitudinales</th>
        </tr>
        <tr>
            @php
                $periodContents = collect($unit['period_contents'] ?? []);
                $conceptualContents = collect($periodContents->get('conceptual', []))->filter()->values();
                $proceduralContents = collect($periodContents->get('procedural', []))->filter()->values();
                $attitudinalContents = collect($periodContents->get('attitudinal', []))->filter()->values();
            @endphp
            <td colspan="2" class="top">
                @if($conceptualContents->isNotEmpty())
                    {!! nl2br(e($conceptualContents->implode("\n"))) !!}
                @else
                    -
                @endif
            </td>
            <td colspan="2" class="top">
                @if($proceduralContents->isNotEmpty())
                    {!! nl2br(e($proceduralContents->implode("\n"))) !!}
                @else
                    -
                @endif
            </td>
            <td class="top">
                @if($attitudinalContents->isNotEmpty())
                    {!! nl2br(e($attitudinalContents->implode("\n"))) !!}
                @else
                    -
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="5" class="center">Sin unidades registradas.</td></tr>
    @endforelse
</table>

<table class="mb-12" style="table-layout: fixed;">
    <colgroup>
        <col style="width:10%;">
        <col style="width:10%;">
        <col style="width:10%;">
        <col style="width:10%;">
        <col style="width:60%;">
    </colgroup>
    <tr>
        <th colspan="5" class="blue-fill">Planeación por sesión</th>
    </tr>
    <tr class="center bold">
        <th style="width:10%;">No. de<br>sesión</th>
        <th style="width:10%;">Clave de<br>grupo(s)</th>
        <th style="width:10%;">Fecha<br>programada</th>
        <th style="width:10%;">Contenidos<br><span class="subtle">(Solo los numerales)</span></th>
        <th style="width:60%;">Estrategias de<br>enseñanza-aprendizaje</th>
    </tr>
    @forelse($periodBlock['rows'] as $row)
        @php
            $topicKey = $extractKey($row['topic'] ?? '');
            $subtopicKeys = collect(preg_split('/[;,]+/u', (string) ($row['subtopics'] ?? '')) ?: [])
                ->map(fn ($value) => $extractKey($value))
                ->filter(fn ($value) => $value !== '-')
                ->values();
            $contentKeys = $subtopicKeys->isNotEmpty()
                ? $subtopicKeys->unique()->implode('; ')
                : collect([$topicKey])->filter(fn ($value) => $value !== '-')->unique()->implode('; ');
            $activities = collect([$row['opening'] ?? null, $row['development'] ?? null, $row['closing'] ?? null])
                ->filter(fn ($value) => $value && $value !== '-')
                ->implode("\n\n");
        @endphp
        <tr>
            <td class="center">{{ $row['num'] ?? $loop->iteration }}</td>
            <td>{{ $assignment->group->name ?? '-' }}</td>
            <td>{{ $row['start_date'] ?? '-' }}</td>
            <td>{{ $contentKeys ?: '-' }}</td>
            <td>{!! nl2br(e($activities ?: '-')) !!}</td>
        </tr>
    @empty
        <tr><td colspan="5" class="center">Sin sesiones registradas.</td></tr>
    @endforelse
</table>
@endforeach

<table class="mb-12">
    <tr><td class="subsection" colspan="2">2.3. Recursos, materiales y bibliografía a utilizar en el periodo</td></tr>
    <tr>
        <td class="bold" style="width:35%;">Recursos y materiales didácticos a utilizar en el periodo</td>
        <td>{{ $resourceMaterials !== '' ? $resourceMaterials : '-' }}</td>
    </tr>
    <tr>
        <td class="bold">Bibliografía básica y de consulta del periodo</td>
        <td>
            @if(!empty($references_list))
                {{ implode('; ', $references_list) }}
            @else
                {{ $plan->bibliography ?: '-' }}
            @endif
        </td>
    </tr>
</table>

<table class="no-border" style="margin-top: 18px;">
    <tr>
        <td class="center" style="width:50%;">
            _______________________________<br>
            Firma del docente
        </td>
        <td class="center" style="width:50%;">
            _______________________________<br>
            Vo. Bo. Dirección Teécnica / Coordinación
        </td>
    </tr>
</table>
</body>
</html>
