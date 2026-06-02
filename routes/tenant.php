<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;

Route::middleware([
    'web',
    InitializeTenancyByRequestData::class . ':tenant',
])->prefix('tenant-demo')->group(function () {
    Route::get('/', function () {
        return response()->json([
            'tenant_id' => tenant('id'),
            'message' => 'Tenancy inicializada por request data (?tenant=...)',
        ]);
    });
});

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
])->prefix('tenant-check')->group(function () {
    Route::get('/', function () {
        return response()->json([
            'tenant_id' => tenant('id'),
            'domain' => request()->getHost(),
            'message' => 'Tenancy inicializada por dominio',
        ]);
    });
});
