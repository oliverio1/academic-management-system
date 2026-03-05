<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Imports\AttendanceImportController;
use App\Http\Controllers\Imports\GradesImportController;

/*
|--------------------------------------------------------------------------
| Import Routes
|--------------------------------------------------------------------------
| Rutas para importación de información histórica (Excel, CSV, etc.)
| Accesibles solo para admin y coordinación
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:admin|coordination'])
    ->prefix('imports')
    ->as('imports.')
    ->group(function () {

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