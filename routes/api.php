<?php

use App\Http\Controllers\Api\BackupApiController;
use App\Http\Controllers\Api\LicenseApiController;
use Illuminate\Support\Facades\Route;

Route::post('/licenses/validate', [LicenseApiController::class, 'validateLicense']);
Route::post('/backups/latest', [BackupApiController::class, 'uploadLatest']);
