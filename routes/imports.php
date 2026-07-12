<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Imports\AttendanceImportController;
use App\Http\Controllers\Imports\GradesImportController;
use App\Http\Controllers\Imports\MasterScheduleImportController;

/*
|--------------------------------------------------------------------------
| Import Routes
|--------------------------------------------------------------------------
| Rutas para importación de información histórica (Excel, CSV, etc.)
| Accesibles solo para admin y coordinación
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:coordinator|admin', 'tenant.domain', 'tenant.prevent-central', 'campus.access'])
    ->prefix('imports')
    ->as('imports.')
    ->group(function () {
        Route::get('master-schedule', [MasterScheduleImportController::class, 'create'])
            ->name('master-schedule.create');

        Route::post('master-schedule/preview', [MasterScheduleImportController::class, 'preview'])
            ->name('master-schedule.preview');

        Route::post('master-schedule/import', [MasterScheduleImportController::class, 'import'])
            ->name('master-schedule.import');

        /*
        |--------------------------------------------------------------------------
        | Asistencias
        |--------------------------------------------------------------------------
        */

        Route::get('attendances', [AttendanceImportController::class, 'create'])
            ->name('attendances.create');

        Route::post('attendances', [AttendanceImportController::class, 'store'])
            ->name('attendances.store');

        Route::get('grades', [GradesImportController::class, 'create'])
            ->name('grades.create');

        Route::post('grades', [GradesImportController::class, 'store'])
            ->name('grades.store');

    });
