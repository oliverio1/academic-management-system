<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\DidacticPlan;
use App\Models\SchoolCycle;
use App\Models\TeachingAssignment;

$result = [];
foreach (SchoolCycle::where('is_active', true)->get() as $cycle) {
    $assignments = TeachingAssignment::query()
        ->whereHas('schedules', fn($q) => $q->where('is_active', true)->where('school_cycle_id', (int)$cycle->id))
        ->get()
        ->unique(fn($a) => ((int)$a->teacher_id).'-'.((int)$a->group_id).'-'.((int)$a->subject_id));

    $missing = 0;
    foreach ($assignments as $assignment) {
        $has = DidacticPlan::where('teaching_assignment_id', (int)$assignment->id)
            ->where('school_cycle_id', (int)$cycle->id)
            ->exists();
        if (!$has) $missing++;
    }

    $result[] = [
        'cycle_id' => (int)$cycle->id,
        'cycle_name' => $cycle->name,
        'assignments' => $assignments->count(),
        'missing_plans' => $missing,
    ];
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
