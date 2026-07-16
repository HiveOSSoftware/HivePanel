<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\Workers\SftpAuthController;
use App\Http\Controllers\Api\Workers\WorkerRegistrationController;
use App\Http\Controllers\Api\Workers\WorkerHeartbeatController;
use App\Http\Controllers\Admin\AdminCombImportController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('worker')
    ->middleware([
        'worker.auth',
        'throttle:120,1',
    ])
    ->group(function () {
        Route::post('/sftp/auth', SftpAuthController::class)
            ->name('api.worker.sftp.auth');
    });

Route::post('/worker/register', WorkerRegistrationController::class)->name('worker.register');
Route::post('/worker/heartbeat', WorkerHeartbeatController::class)->name('worker.heartbeat');

Route::get('/registry/combs', function (RegistryService $registry) {
    return $registry->getCombs();
});

Route::post('/registry/combs/{id}/import', [AdminCombImportController::class, 'import']);
Route::post('/registry/combs/sync', [AdminCombImportController::class, 'sync']);