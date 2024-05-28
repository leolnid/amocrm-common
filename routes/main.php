<?php

use Illuminate\Support\Facades\Route;
use Spatie\Health\Http\Controllers\HealthCheckJsonResultsController;
use Spatie\Health\Http\Controllers\HealthCheckResultsController;

/**
 * #########################################################################
 *
 * Web Routes Group
 *
 * #########################################################################
 */
Route::middleware('web')->group(function () {
    Route::get('health', HealthCheckResultsController::class)->name('health.index');
});

/**
 * #########################################################################
 *
 * API Routes Group
 *
 * #########################################################################
 */
Route::middleware('api')->prefix('api')->group(function () {
    Route::get('health', HealthCheckJsonResultsController::class);
});
